<?php

namespace App\Services\Schema;

/**
 * Server-side twin of the condition evaluation the React fill page performs.
 * Fields whose conditions fail are treated as absent: not required, and their
 * submitted values are discarded.
 *
 * resources/js/lib/conditions.js mirrors this logic — keep the two in sync.
 */
class ConditionEvaluator
{
    /**
     * Compute visibility for every input field key in the schema, given the
     * submitted values (keyed by field key).
     *
     * Fields are evaluated in document order; a rule that references a field
     * already known to be hidden sees its value as null, so hiding cascades.
     *
     * @return array<string, bool> key => visible
     */
    public function visibility(array $schema, array $values): array
    {
        $visible = [];

        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if (! FieldTypes::isInput($field['type'])) {
                    continue;
                }

                $visible[$field['key']] = $this->passes(
                    $field['conditions'] ?? null,
                    $values,
                    $visible,
                );
            }
        }

        return $visible;
    }

    private function passes(?array $conditions, array $values, array $visibleSoFar): bool
    {
        $rules = $conditions['rules'] ?? [];

        if ($rules === []) {
            return true;
        }

        $logic = $conditions['logic'] ?? 'all';
        $results = [];

        foreach ($rules as $rule) {
            $key = $rule['field'];

            // A hidden dependency contributes no value.
            $actual = (isset($visibleSoFar[$key]) && ! $visibleSoFar[$key])
                ? null
                : ($values[$key] ?? null);

            $results[] = $this->compare($rule['operator'], $actual, $rule['value'] ?? null);
        }

        return $logic === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    private function compare(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $this->looselyEquals($actual, $expected),
            'not_equals' => ! $this->looselyEquals($actual, $expected),
            'contains' => $this->contains($actual, $expected),
            'greater_than' => is_numeric($actual) && is_numeric($expected)
                && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected)
                && (float) $actual < (float) $expected,
            'is_empty' => $this->isEmpty($actual),
            'is_not_empty' => ! $this->isEmpty($actual),
            default => false,
        };
    }

    private function looselyEquals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            // checkbox groups: "equals" means the selection is exactly that one value
            return count($actual) === 1 && $this->looselyEquals($actual[0], $expected);
        }

        if (is_bool($actual) || is_bool($expected)) {
            return filter_var($actual, FILTER_VALIDATE_BOOLEAN) === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $actual === (string) $expected;
    }

    private function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array((string) $expected, array_map('strval', $actual), true);
        }

        if (! is_string($actual) && ! is_numeric($actual)) {
            return false;
        }

        return $expected !== null
            && str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected));
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
