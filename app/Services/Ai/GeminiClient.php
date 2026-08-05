<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    /**
     * One generateContent call in JSON mode.
     *
     * @return array{text: string, prompt_tokens: ?int, completion_tokens: ?int, latency_ms: int, model: string}
     */
    public function generate(string $systemPrompt, string $userPrompt): array
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model');

        if (! $key) {
            throw new RuntimeException(
                'GEMINI_API_KEY is not configured. Add it to your .env file.',
            );
        }

        $startedAt = microtime(true);

        $response = Http::timeout(config('services.gemini.timeout'))
            ->withHeaders(['x-goog-api-key' => $key])
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $userPrompt]],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.2,
                        'maxOutputTokens' => 8192,
                    ],
                ],
            );

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'HTTP '.$response->status();
            throw new RuntimeException("Gemini request failed: {$message}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            $reason = $response->json('candidates.0.finishReason') ?? 'empty response';
            throw new RuntimeException("Gemini returned no text ({$reason}).");
        }

        return [
            'text' => $text,
            'prompt_tokens' => $response->json('usageMetadata.promptTokenCount'),
            'completion_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
            'latency_ms' => $latencyMs,
            'model' => $model,
        ];
    }
}
