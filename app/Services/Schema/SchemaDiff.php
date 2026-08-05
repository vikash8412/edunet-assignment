<?php

namespace App\Services\Schema;

/**
 * Field-level diff between two schema snapshots, keyed by field key.
 * Powers the version history screen.
 */
class SchemaDiff
{
    private const COMPARED_PROPS = [
        'type', 'label', 'placeholder', 'help', 'default', 'required',
        'options', 'validation', 'conditions',
    ];

    /**
     * @return array{added: list<array>, removed: list<array>, changed: list<array>, title_changed: bool}
     */
    public function diff(array $old, array $new): array
    {
        $oldFields = $this->fieldsByKey($old);
        $newFields = $this->fieldsByKey($new);

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($newFields as $key => $field) {
            if (! isset($oldFields[$key])) {
                $added[] = ['key' => $key, 'label' => $field['label'], 'type' => $field['type']];
            }
        }

        foreach ($oldFields as $key => $field) {
            if (! isset($newFields[$key])) {
                $removed[] = ['key' => $key, 'label' => $field['label'], 'type' => $field['type']];
            }
        }

        foreach ($newFields as $key => $field) {
            if (! isset($oldFields[$key])) {
                continue;
            }

            $changedProps = [];

            foreach (self::COMPARED_PROPS as $prop) {
                $before = $oldFields[$key][$prop] ?? null;
                $after = $field[$prop] ?? null;

                if ($before !== $after) {
                    $changedProps[] = $prop;
                }
            }

            if ($changedProps !== []) {
                $changed[] = [
                    'key' => $key,
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'props' => $changedProps,
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'title_changed' => ($old['title'] ?? null) !== ($new['title'] ?? null),
        ];
    }

    /** @return array<string, array> */
    private function fieldsByKey(array $schema): array
    {
        $fields = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return $fields;
    }
}
