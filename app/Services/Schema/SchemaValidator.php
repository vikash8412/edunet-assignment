<?php

namespace App\Services\Schema;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * The single gate every form schema passes through before it is persisted —
 * builder saves, raw JSON editor, AI output and document imports alike.
 *
 * Two layers:
 *  1. structural: resources/schema/form-schema.json (JSON Schema 2020-12)
 *  2. semantic:   uniqueness, per-type constraints and condition references
 *                 that JSON Schema cannot express readably
 */
class SchemaValidator
{
    private const MAX_ERRORS = 25;

    public function validate(array $schema): SchemaValidationResult
    {
        $structural = $this->validateStructure($schema);

        if (! $structural->valid) {
            return $structural;
        }

        return $this->validateSemantics($schema);
    }

    private function validateStructure(array $schema): SchemaValidationResult
    {
        $validator = new Validator();
        $validator->setMaxErrors(self::MAX_ERRORS);

        $data = json_decode(json_encode($schema));

        if ($data === null || ! is_object($data)) {
            return SchemaValidationResult::fail(['Schema must be a JSON object.']);
        }

        $contract = file_get_contents(resource_path('schema/form-schema.json'));
        $result = $validator->validate($data, $contract);

        if ($result->isValid()) {
            return SchemaValidationResult::ok();
        }

        $formatted = (new ErrorFormatter())->format($result->error(), true);
        $errors = [];

        foreach ($formatted as $pointer => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = trim($pointer.': '.$message);
            }
        }

        return SchemaValidationResult::fail(array_slice($errors, 0, self::MAX_ERRORS));
    }

    private function validateSemantics(array $schema): SchemaValidationResult
    {
        $errors = [];
        $seenKeys = [];
        $seenIds = [];
        $fieldTypesByKey = [];

        foreach ($schema['sections'] as $s => $section) {
            $sectionPath = "/sections/{$s}";

            if (isset($seenIds[$section['id']])) {
                $errors[] = "{$sectionPath}/id: duplicate id \"{$section['id']}\".";
            }
            $seenIds[$section['id']] = true;

            foreach ($section['fields'] as $f => $field) {
                $path = "{$sectionPath}/fields/{$f}";
                $type = $field['type'];
                $key = $field['key'];

                if (isset($seenIds[$field['id']])) {
                    $errors[] = "{$path}/id: duplicate id \"{$field['id']}\".";
                }
                $seenIds[$field['id']] = true;

                if (isset($seenKeys[$key])) {
                    $errors[] = "{$path}/key: duplicate field key \"{$key}\" (keys must be unique across the form).";
                }
                $seenKeys[$key] = true;
                $fieldTypesByKey[$key] = $type;

                if (FieldTypes::hasOptions($type)) {
                    $options = $field['options'] ?? [];
                    if (count($options) === 0) {
                        $errors[] = "{$path}/options: \"{$type}\" fields need at least one option.";
                    } else {
                        $values = array_map(fn ($o) => (string) $o['value'], $options);
                        if (count($values) !== count(array_unique($values))) {
                            $errors[] = "{$path}/options: option values must be unique.";
                        }
                    }
                }

                if (FieldTypes::isDisplay($type) && ($field['required'] ?? false)) {
                    $errors[] = "{$path}/required: display field \"{$type}\" cannot be required.";
                }

                if ($type === 'file' && isset($field['default']) && $field['default'] !== null) {
                    $errors[] = "{$path}/default: file fields cannot have a default value.";
                }

                if ($type === 'rating') {
                    $max = $field['validation']['max'] ?? null;
                    if ($max !== null && ($max < 1 || $max > 10)) {
                        $errors[] = "{$path}/validation/max: rating scale must be between 1 and 10.";
                    }
                }

                $pattern = $field['validation']['pattern'] ?? null;
                if ($pattern !== null && @preg_match('/'.str_replace('/', '\/', $pattern).'/', '') === false) {
                    $errors[] = "{$path}/validation/pattern: invalid regular expression.";
                }

                $min = $field['validation']['minLength'] ?? null;
                $max = $field['validation']['maxLength'] ?? null;
                if ($min !== null && $max !== null && $min > $max) {
                    $errors[] = "{$path}/validation: minLength cannot exceed maxLength.";
                }
            }
        }

        // Conditions may only reference existing input field keys, not self.
        foreach ($schema['sections'] as $s => $section) {
            foreach ($section['fields'] as $f => $field) {
                $rules = $field['conditions']['rules'] ?? [];
                $path = "/sections/{$s}/fields/{$f}/conditions";

                foreach ($rules as $r => $rule) {
                    $target = $rule['field'];

                    if ($target === $field['key']) {
                        $errors[] = "{$path}/rules/{$r}: a field cannot depend on itself.";
                    } elseif (! isset($fieldTypesByKey[$target])) {
                        $errors[] = "{$path}/rules/{$r}: unknown field key \"{$target}\".";
                    } elseif (FieldTypes::isDisplay($fieldTypesByKey[$target])) {
                        $errors[] = "{$path}/rules/{$r}: cannot depend on display field \"{$target}\".";
                    }
                }
            }
        }

        return $errors === []
            ? SchemaValidationResult::ok()
            : SchemaValidationResult::fail(array_slice($errors, 0, self::MAX_ERRORS));
    }
}
