<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\Import\AiTypeResolver;
use App\Services\Import\ExcelParser;
use App\Services\Import\WordParser;
use App\Services\Schema\SchemaNormalizer;
use App\Services\Schema\SchemaValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $importId)
    {
    }

    public function handle(
        WordParser $word,
        ExcelParser $excel,
        AiTypeResolver $ai,
        SchemaNormalizer $normalizer,
        SchemaValidator $validator,
    ): void {
        $import = Import::find($this->importId);

        if (! $import || $import->status !== Import::STATUS_QUEUED) {
            return;
        }

        $import->update(['status' => Import::STATUS_PARSING]);

        try {
            $path = Storage::path($import->path);

            // 1. Deterministic parse.
            $parsed = $import->kind === 'docx'
                ? $word->parse($path)
                : $excel->parse($path, $import->original_name);

            $warnings = $parsed['warnings'];

            // 2. AI assist, only for fields the heuristics could not type.
            $resolved = $ai->resolve($parsed['ambiguousKeys'], $parsed['schema']['title'] ?? '');

            if ($resolved['warning'] !== null) {
                $warnings[] = $resolved['warning'];
            }

            if ($resolved['types'] !== []) {
                foreach ($parsed['schema']['sections'] as &$section) {
                    foreach ($section['fields'] as &$field) {
                        if (isset($resolved['types'][$field['label']])) {
                            $field['type'] = $resolved['types'][$field['label']]['type'];
                            $field['validation'] = $resolved['types'][$field['label']]['validation'];
                        }
                    }
                }
                unset($section, $field);
            }

            // 3. Normalize + validate through the same gate as everything else.
            $normalized = $normalizer->normalize($parsed['schema']);
            $warnings = array_merge($warnings, $normalized['warnings']);

            $result = $validator->validate($normalized['schema']);

            if (! $result->valid) {
                // Normalizer output should always validate; belt and braces.
                $import->update([
                    'status' => Import::STATUS_FAILED,
                    'error' => 'Parsed schema failed validation: '.implode(' ', array_slice($result->errors, 0, 5)),
                    'warnings' => $warnings,
                ]);

                return;
            }

            $fieldCount = array_sum(array_map(
                fn ($section) => count($section['fields']),
                $normalized['schema']['sections'],
            ));

            if ($fieldCount === 0) {
                $warnings[] = 'No fields could be detected — check the document layout.';
            }

            $import->update([
                'status' => Import::STATUS_READY,
                'parsed_schema' => $normalized['schema'],
                'warnings' => $warnings,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $import->update([
                'status' => Import::STATUS_FAILED,
                'error' => 'Import crashed: '.$e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Import::where('id', $this->importId)->update([
            'status' => Import::STATUS_FAILED,
            'error' => 'Job crashed: '.($exception?->getMessage() ?? 'unknown error'),
        ]);
    }
}
