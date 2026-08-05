<?php

namespace App\Services\Submissions;

use App\Models\Form;
use App\Models\Submission;
use App\Services\Schema\FieldTypes;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class SubmissionService
{
    /**
     * Persist a validated submission: answer data, uploaded files and the
     * denormalised search text, pinned to the form's current version.
     *
     * @param array $values validated values keyed by field key (files included)
     */
    public function store(Form $form, array $values, ?string $ipHash, ?string $userAgent, ?Carbon $startedAt): Submission
    {
        $files = [];
        $data = [];

        foreach ($values as $key => $value) {
            if ($value instanceof UploadedFile) {
                $files[$key] = $value;
                $data[$key] = $value->getClientOriginalName();
            } else {
                $data[$key] = $value;
            }
        }

        $submission = $form->submissions()->create([
            'form_version_id' => $form->versions()->latest('version')->value('id'),
            'data' => $data,
            'search_text' => $this->searchText($form->schema, $data),
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            'started_at' => $startedAt,
            'created_at' => now(),
        ]);

        foreach ($files as $key => $file) {
            $path = $file->store("submissions/{$form->id}");

            $submission->files()->create([
                'field_key' => $key,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'created_at' => now(),
            ]);
        }

        return $submission;
    }

    /**
     * Flatten answer values (with their labels for choice fields) into one
     * searchable string.
     */
    private function searchText(array $schema, array $data): string
    {
        $parts = [];

        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if (! FieldTypes::isInput($field['type'])) {
                    continue;
                }

                $value = $data[$field['key']] ?? null;

                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                $labels = collect($field['options'] ?? [])
                    ->keyBy(fn ($o) => (string) $o['value']);

                foreach ((array) $value as $item) {
                    $item = (string) $item;
                    $parts[] = $labels->has($item)
                        ? $labels->get($item)['label']
                        : $item;
                }
            }
        }

        return mb_substr(strip_tags(implode(' | ', $parts)), 0, 60000);
    }
}
