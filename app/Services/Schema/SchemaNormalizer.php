<?php

namespace App\Services\Schema;

use Illuminate\Support\Str;

/**
 * Best-effort repair of near-miss schemas coming from the AI layer or the
 * document importers, applied BEFORE strict validation. It never invents
 * content — it coerces shapes: alias field types, missing ids/keys,
 * misplaced root-level fields, stray properties.
 *
 * Everything it changes besides trivial defaults is reported as a warning so
 * the UI can surface what was fixed.
 */
class SchemaNormalizer
{
    private const KNOWN_FIELD_PROPS = [
        'id', 'type', 'key', 'label', 'placeholder', 'help', 'default',
        'required', 'options', 'validation', 'conditions',
    ];

    private const KNOWN_VALIDATION_PROPS = [
        'min', 'max', 'minLength', 'maxLength', 'pattern', 'url', 'integer',
        'mimes', 'maxSizeKb', 'minDate', 'maxDate',
    ];

    /**
     * @return array{schema: array, warnings: list<string>}
     */
    public function normalize(array $raw): array
    {
        $warnings = [];

        $schema = [
            'title' => $this->stringOr($raw['title'] ?? null, 'Untitled form', 200),
            'description' => $this->nullableString($raw['description'] ?? null, 2000),
            'settings' => $this->normalizeSettings($raw['settings'] ?? []),
            'sections' => [],
        ];

        $sections = $raw['sections'] ?? null;

        // Models sometimes emit a flat "fields" array with no sections.
        if (! is_array($sections) || $sections === []) {
            $rootFields = $raw['fields'] ?? [];
            $sections = [[
                'title' => 'Form',
                'fields' => is_array($rootFields) ? $rootFields : [],
            ]];
            if (! isset($raw['sections'])) {
                $warnings[] = 'No sections found — wrapped fields into a single section.';
            }
        }

        $usedKeys = [];
        $usedIds = [];

        foreach ($sections as $rawSection) {
            if (! is_array($rawSection)) {
                $warnings[] = 'Dropped a section that was not an object.';
                continue;
            }

            // A "section" that looks like a field (has type/label but no fields
            // array) gets hoisted into a synthetic section.
            if (! isset($rawSection['fields']) && isset($rawSection['type'])) {
                $rawSection = ['title' => '', 'fields' => [$rawSection]];
            }

            $section = [
                'id' => $this->uniqueId('sec', $rawSection['id'] ?? null, $usedIds),
                'title' => $this->stringOr($rawSection['title'] ?? null, 'Section', 200),
                'description' => $this->nullableString($rawSection['description'] ?? null, 2000),
                'fields' => [],
            ];

            foreach (($rawSection['fields'] ?? []) as $rawField) {
                if (! is_array($rawField)) {
                    $warnings[] = 'Dropped a field that was not an object.';
                    continue;
                }

                $field = $this->normalizeField($rawField, $usedKeys, $usedIds, $warnings);

                if ($field !== null) {
                    $section['fields'][] = $field;
                }
            }

            $schema['sections'][] = $section;
        }

        // Guarantee the structural minimum so validation errors stay semantic.
        if ($schema['sections'] === []) {
            $schema['sections'][] = [
                'id' => $this->uniqueId('sec', null, $usedIds),
                'title' => 'Form',
                'description' => null,
                'fields' => [],
            ];
        }

        return ['schema' => $schema, 'warnings' => $warnings];
    }

    private function normalizeField(
        array $raw,
        array &$usedKeys,
        array &$usedIds,
        array &$warnings,
    ): ?array {
        $label = $this->stringOr(
            $raw['label'] ?? $raw['title'] ?? $raw['name'] ?? null,
            'Untitled field',
            255,
        );

        $rawType = is_string($raw['type'] ?? null) ? $raw['type'] : 'text';
        $type = FieldTypes::resolve($rawType);

        if ($type === null) {
            $warnings[] = "Unknown field type \"{$rawType}\" on \"{$label}\" — treated as text.";
            $type = 'text';
        } elseif ($type !== strtolower(trim($rawType))) {
            $warnings[] = "Field type \"{$rawType}\" on \"{$label}\" mapped to \"{$type}\".";
        }

        $field = [
            'id' => $this->uniqueId('fld', $raw['id'] ?? null, $usedIds),
            'type' => $type,
            'key' => $this->uniqueKey($raw['key'] ?? null, $label, $usedKeys),
            'label' => $label,
            'placeholder' => $this->nullableString($raw['placeholder'] ?? null, 255),
            'help' => $this->nullableString($raw['help'] ?? $raw['help_text'] ?? $raw['description'] ?? null, 1000),
            'default' => $raw['default'] ?? null,
            'required' => filter_var($raw['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'options' => [],
            'validation' => $this->normalizeValidation($raw['validation'] ?? []),
            'conditions' => $this->normalizeConditions($raw['conditions'] ?? null),
        ];

        if (FieldTypes::isDisplay($type)) {
            $field['required'] = false;
            $field['default'] = null;
        }

        if ($type === 'file') {
            $field['default'] = null;
        }

        if (FieldTypes::hasOptions($type)) {
            $field['options'] = $this->normalizeOptions($raw['options'] ?? $raw['choices'] ?? []);

            if ($field['options'] === []) {
                $warnings[] = "\"{$label}\" ({$type}) had no options — treated as text.";
                $field['type'] = 'text';
                $field['options'] = [];
            }
        } else {
            $field['options'] = [];
        }

        return $field;
    }

    private function normalizeOptions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        $seen = [];

        foreach ($raw as $item) {
            if (is_string($item) || is_numeric($item)) {
                $label = trim((string) $item);
                $value = Str::slug($label, '_');
            } elseif (is_array($item)) {
                $label = trim((string) ($item['label'] ?? $item['text'] ?? $item['value'] ?? ''));
                $value = trim((string) ($item['value'] ?? Str::slug($label, '_')));
            } else {
                continue;
            }

            if ($label === '') {
                continue;
            }

            $value = $value !== '' ? mb_substr($value, 0, 255) : Str::slug($label, '_');

            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $options[] = ['label' => mb_substr($label, 0, 255), 'value' => $value];
        }

        return $options;
    }

    private function normalizeValidation(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $validation = array_intersect_key($raw, array_flip(self::KNOWN_VALIDATION_PROPS));

        foreach (['minLength', 'maxLength', 'maxSizeKb'] as $intProp) {
            if (isset($validation[$intProp]) && is_numeric($validation[$intProp])) {
                $validation[$intProp] = (int) $validation[$intProp];
            } elseif (isset($validation[$intProp])) {
                unset($validation[$intProp]);
            }
        }

        if (isset($validation['mimes'])) {
            $validation['mimes'] = is_array($validation['mimes'])
                ? array_values(array_filter(array_map(
                    fn ($m) => strtolower(trim(ltrim((string) $m, '.'))),
                    $validation['mimes'],
                ), fn ($m) => preg_match('/^[a-z0-9]{1,10}$/', $m)))
                : null;

            if (empty($validation['mimes'])) {
                unset($validation['mimes']);
            }
        }

        $validation = array_filter($validation, fn ($v) => $v !== null);

        return $validation === [] ? null : $validation;
    }

    private function normalizeConditions(mixed $raw): ?array
    {
        if (! is_array($raw) || ! is_array($raw['rules'] ?? null)) {
            return null;
        }

        $rules = [];

        foreach ($raw['rules'] as $rule) {
            if (! is_array($rule) || ! is_string($rule['field'] ?? null) || ! is_string($rule['operator'] ?? null)) {
                continue;
            }

            $rules[] = [
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => $rule['value'] ?? null,
            ];
        }

        if ($rules === []) {
            return null;
        }

        return [
            'logic' => in_array($raw['logic'] ?? 'all', ['all', 'any'], true) ? ($raw['logic'] ?? 'all') : 'all',
            'rules' => array_slice($rules, 0, 10),
        ];
    }

    private function normalizeSettings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['multi_step' => false];
        }

        return [
            'multi_step' => filter_var($raw['multi_step'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'success_message' => $this->nullableString($raw['success_message'] ?? null, 1000),
            'submit_label' => $this->nullableString($raw['submit_label'] ?? null, 60),
            'max_per_day' => is_numeric($raw['max_per_day'] ?? null) ? max(1, (int) $raw['max_per_day']) : null,
        ];
    }

    private function uniqueId(string $prefix, mixed $candidate, array &$used): string
    {
        $id = is_string($candidate) && preg_match('/^[a-z]+_[a-zA-Z0-9]{4,20}$/', $candidate)
            ? $candidate
            : $prefix.'_'.Str::random(8);

        while (isset($used[$id])) {
            $id = $prefix.'_'.Str::random(8);
        }

        $used[$id] = true;

        return $id;
    }

    private function uniqueKey(mixed $candidate, string $label, array &$used): string
    {
        $key = is_string($candidate) ? Str::slug($candidate, '_') : '';

        if ($key === '' || ! preg_match('/^[a-z]/', $key)) {
            $key = Str::slug($label, '_');
        }

        if ($key === '' || ! preg_match('/^[a-z]/', $key)) {
            $key = 'field';
        }

        $key = mb_substr($key, 0, 60);
        $base = $key;
        $i = 2;

        while (isset($used[$key])) {
            $key = $base.'_'.$i++;
        }

        $used[$key] = true;

        return $key;
    }

    private function stringOr(mixed $value, string $fallback, int $max): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? $fallback : mb_substr($value, 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}
