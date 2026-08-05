<?php

namespace App\Services\Schema;

use Illuminate\Validation\Rule;

/**
 * Compiles a form schema into Laravel validation rules for the public fill
 * endpoint. The schema is the single source of truth: the browser's checks
 * are a convenience, these rules are the contract.
 */
class RuleCompiler
{
    public function __construct(
        private readonly ConditionEvaluator $conditions = new ConditionEvaluator(),
    ) {
    }

    /**
     * @param array $schema validated form schema
     * @param array $values submitted values keyed by field key (files included)
     * @return array{rules: array<string, list<mixed>>, attributes: array<string, string>, visible: array<string, bool>}
     */
    public function compile(array $schema, array $values): array
    {
        $visible = $this->conditions->visibility($schema, $values);
        $rules = [];
        $attributes = [];

        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if (! FieldTypes::isInput($field['type'])) {
                    continue;
                }

                $key = $field['key'];
                $attributes[$key] = $field['label'];

                if (! ($visible[$key] ?? true)) {
                    // Hidden by conditions: never required, value discarded upstream.
                    continue;
                }

                $fieldRules = $this->rulesForField($field);

                if (isset($fieldRules['*'])) {
                    $rules[$key.'.*'] = $fieldRules['*'];
                    unset($fieldRules['*']);
                }

                $rules[$key] = array_values($fieldRules);
            }
        }

        return ['rules' => $rules, 'attributes' => $attributes, 'visible' => $visible];
    }

    /** @return list<mixed> */
    private function rulesForField(array $field): array
    {
        $v = $field['validation'] ?? [];
        $required = $field['required'] ?? false;

        $rules = [$required ? 'required' : 'nullable'];

        switch ($field['type']) {
            case 'text':
            case 'hidden':
                $rules[] = 'string';
                if (! empty($v['url'])) {
                    $rules[] = 'url';
                }
                $this->applyLengthRules($rules, $v);
                $this->applyPattern($rules, $v);
                break;

            case 'textarea':
                $rules[] = 'string';
                $this->applyLengthRules($rules, $v, defaultMax: 10000);
                $this->applyPattern($rules, $v);
                break;

            case 'number':
                $rules[] = 'numeric';
                if (! empty($v['integer'])) {
                    $rules[] = 'integer';
                }
                if (isset($v['min'])) {
                    $rules[] = 'min:'.$v['min'];
                }
                if (isset($v['max'])) {
                    $rules[] = 'max:'.$v['max'];
                }
                break;

            case 'email':
                $rules[] = 'string';
                $rules[] = 'email:rfc';
                $this->applyLengthRules($rules, $v, defaultMax: 255);
                break;

            case 'phone':
                $rules[] = 'string';
                $rules[] = isset($v['pattern'])
                    ? 'regex:/'.str_replace('/', '\/', $v['pattern']).'/'
                    : 'regex:/^\+?[0-9 ().\-]{6,20}$/';
                break;

            case 'date':
                $rules[] = 'date';
                if (! empty($v['minDate'])) {
                    $rules[] = 'after_or_equal:'.$v['minDate'];
                }
                if (! empty($v['maxDate'])) {
                    $rules[] = 'before_or_equal:'.$v['maxDate'];
                }
                break;

            case 'dropdown':
            case 'radio':
                $rules[] = Rule::in($this->optionValues($field));
                break;

            case 'checkbox':
                $rules[] = 'array';
                if (($field['required'] ?? false)) {
                    $rules[] = 'min:1';
                }
                if (isset($v['max'])) {
                    $rules[] = 'max:'.(int) $v['max'];
                }
                $rules['*'] = [Rule::in($this->optionValues($field))];
                break;

            case 'file':
                $rules[] = 'file';
                $rules[] = 'max:'.(int) ($v['maxSizeKb'] ?? 10240);
                if (! empty($v['mimes'])) {
                    $rules[] = 'mimes:'.implode(',', $v['mimes']);
                }
                break;

            case 'rating':
                $rules[] = 'integer';
                $rules[] = 'min:'.(int) ($v['min'] ?? 1);
                $rules[] = 'max:'.(int) ($v['max'] ?? 5);
                break;
        }

        return $rules;
    }

    private function applyLengthRules(array &$rules, array $v, int $defaultMax = 255): void
    {
        $rules[] = 'min:'.(int) ($v['minLength'] ?? 0);
        $rules[] = 'max:'.(int) ($v['maxLength'] ?? $defaultMax);
    }

    private function applyPattern(array &$rules, array $v): void
    {
        if (! empty($v['pattern'])) {
            $rules[] = 'regex:/'.str_replace('/', '\/', $v['pattern']).'/';
        }
    }

    /** @return list<string> */
    private function optionValues(array $field): array
    {
        return array_map(
            fn ($o) => (string) $o['value'],
            $field['options'] ?? [],
        );
    }
}
