<?php

namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Services\Schema\FieldTypes;
use App\Services\Schema\SchemaNormalizer;
use App\Services\Schema\SchemaValidator;
use RuntimeException;
use Throwable;

/**
 * The Part B pipeline: prompt -> Gemini (JSON mode) -> parse/repair ->
 * normalize (alias hallucinated types, fix keys) -> strict validation.
 * Invalid output is retried with the validator's errors fed back to the
 * model; a broken schema is never persisted.
 */
class FormGenerator
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly GeminiClient $client,
        private readonly SchemaNormalizer $normalizer,
        private readonly SchemaValidator $validator,
    ) {
    }

    /**
     * Run the full pipeline for a queued generation row, updating it in place.
     */
    public function run(AiGeneration $generation): void
    {
        $generation->update(['status' => AiGeneration::STATUS_RUNNING]);

        $feedback = null;
        $lastError = 'Unknown error.';

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $generation->update(['attempts' => $attempt]);

            try {
                $response = $this->client->generate(
                    $this->systemPrompt(),
                    $this->userPrompt($generation, $feedback),
                );
            } catch (Throwable $e) {
                // Transport/config errors won't improve with model feedback.
                $generation->update([
                    'status' => AiGeneration::STATUS_FAILED,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            $generation->update([
                'model' => $response['model'],
                'prompt_tokens' => ($generation->prompt_tokens ?? 0) + ($response['prompt_tokens'] ?? 0),
                'completion_tokens' => ($generation->completion_tokens ?? 0) + ($response['completion_tokens'] ?? 0),
                'latency_ms' => ($generation->latency_ms ?? 0) + $response['latency_ms'],
            ]);

            try {
                $raw = $this->parseJson($response['text']);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                $feedback = 'Your previous reply was not valid JSON ('.$e->getMessage().'). '
                    .'Reply with ONLY the JSON object, no prose, no markdown fences.';
                continue;
            }

            $normalized = $this->normalizer->normalize($raw);
            $result = $this->validator->validate($normalized['schema']);

            if ($result->valid) {
                $generation->update([
                    'status' => AiGeneration::STATUS_DONE,
                    'result_schema' => $normalized['schema'],
                    'warnings' => $normalized['warnings'],
                    'error' => null,
                ]);

                return;
            }

            $lastError = 'Schema validation failed: '.implode(' ', array_slice($result->errors, 0, 5));
            $feedback = "Your previous schema had these problems:\n- "
                .implode("\n- ", array_slice($result->errors, 0, 10))
                ."\nReturn the corrected FULL schema as JSON only.";
        }

        $generation->update([
            'status' => AiGeneration::STATUS_FAILED,
            'error' => 'Gave up after '.self::MAX_ATTEMPTS." attempts. Last problem: {$lastError}",
        ]);
    }

    private function systemPrompt(): string
    {
        $types = implode(', ', FieldTypes::all());

        return <<<PROMPT
You design data-collection form schemas. Reply with ONLY a JSON object — no prose, no markdown fences.

The JSON object must follow exactly this structure:
{
  "title": "string (1-200 chars)",
  "description": "string or null",
  "settings": {"multi_step": bool, "success_message": "string or null", "submit_label": "string or null"},
  "sections": [
    {
      "id": "sec_<8 random alphanumerics>",
      "title": "string",
      "description": "string or null",
      "fields": [
        {
          "id": "fld_<8 random alphanumerics>",
          "type": "one of: {$types}",
          "key": "snake_case identifier, unique across the WHOLE form, starts with a letter",
          "label": "string",
          "placeholder": "string or null",
          "help": "string or null",
          "default": null,
          "required": bool,
          "options": [{"label": "string", "value": "snake_case string"}],
          "validation": {"min": num, "max": num, "minLength": int, "maxLength": int, "pattern": "regex", "url": bool, "integer": bool, "mimes": ["pdf"], "maxSizeKb": int, "minDate": "YYYY-MM-DD", "maxDate": "YYYY-MM-DD"} or null,
          "conditions": {"logic": "all|any", "rules": [{"field": "other_field_key", "operator": "equals|not_equals|contains|greater_than|less_than|is_empty|is_not_empty", "value": "..."}]} or null
        }
      ]
    }
  ]
}

Rules:
- Use ONLY the listed field types. dropdown/radio/checkbox MUST have at least 2 options with unique values.
- "heading" and "paragraph" are display-only: never required, no options.
- Group related fields into sections. Use several sections for longer forms.
- Choose sensible validation: email fields need no pattern; phone needs none (built in); files should limit mimes and maxSizeKb.
- Include only validation properties that matter — omit the rest or use null.
- Labels and help text in the language the user asked for (default English).
- Keys must be unique across ALL sections combined.
PROMPT;
    }

    private function userPrompt(AiGeneration $generation, ?string $feedback): string
    {
        $parts = [];

        if ($generation->mode === 'edit') {
            $current = json_encode(
                $generation->form?->schema,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            $parts[] = "Here is the current form schema:\n{$current}";
            $parts[] = "Apply this change and return the FULL updated schema "
                ."(keep existing ids and keys for fields you do not change):\n"
                .$generation->prompt;
        } else {
            $parts[] = 'Create a complete form for this request: '.$generation->prompt;
        }

        if ($feedback !== null) {
            $parts[] = $feedback;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Parse model output into an array, tolerating the usual near-JSON: code
     * fences, prose around the object, trailing commas.
     */
    private function parseJson(string $text): array
    {
        $candidates = [];

        $trimmed = trim($text);
        $candidates[] = $trimmed;

        // Strip markdown fences.
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $trimmed, $m)) {
            $candidates[] = $m[1];
        }

        // Widest {...} span, for prose-wrapped replies or truncated tails.
        $first = strpos($trimmed, '{');
        $last = strrpos($trimmed, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $candidates[] = substr($trimmed, $first, $last - $first + 1);
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Remove trailing commas before } or ] and retry.
            $repaired = preg_replace('/,\s*([}\]])/', '$1', $candidate);
            $decoded = json_decode($repaired, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException(json_last_error_msg());
    }
}
