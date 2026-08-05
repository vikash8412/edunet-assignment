<?php

namespace App\Services\Import;

use App\Services\Ai\GeminiClient;
use App\Services\Schema\FieldTypes;
use Throwable;

/**
 * The AI half of the hybrid import: ONE batched Gemini call to type the
 * fields the deterministic parser could not classify. Degrades gracefully —
 * on any failure the fields simply stay text and the user fixes them on the
 * mapping screen.
 */
class AiTypeResolver
{
    public function __construct(private readonly GeminiClient $client)
    {
    }

    /**
     * @param list<string> $labels ambiguous field labels
     * @return array{types: array<string, array{type: string, validation: ?array}>, warning: ?string}
     */
    public function resolve(array $labels, string $documentTitle): array
    {
        if ($labels === [] || ! config('services.gemini.key')) {
            return ['types' => [], 'warning' => null];
        }

        $allowed = implode(', ', FieldTypes::INPUT_TYPES);

        $system = <<<PROMPT
You classify form field labels into input types. Reply with ONLY a JSON object mapping each label EXACTLY as given to {"type": "...", "validation": {...} or null}.
Allowed types: {$allowed}. Prefer "text" when unsure. Use validation only when obvious (e.g. {"url": true} for links, {"integer": true} for counts).
PROMPT;

        $user = "Form: {$documentTitle}\nLabels to classify:\n".json_encode($labels, JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->client->generate($system, $user);
            $decoded = json_decode($response['text'], true);
        } catch (Throwable $e) {
            return ['types' => [], 'warning' => 'AI type inference skipped: '.$e->getMessage()];
        }

        if (! is_array($decoded)) {
            return ['types' => [], 'warning' => 'AI type inference returned unusable output — kept text types.'];
        }

        $types = [];

        foreach ($decoded as $label => $info) {
            $type = FieldTypes::resolve((string) ($info['type'] ?? ''));

            // Choice types without options make no sense here; downgrade.
            if ($type === null || FieldTypes::hasOptions($type) || FieldTypes::isDisplay($type)) {
                continue;
            }

            $types[$label] = [
                'type' => $type,
                'validation' => is_array($info['validation'] ?? null) ? $info['validation'] : null,
            ];
        }

        return ['types' => $types, 'warning' => null];
    }
}
