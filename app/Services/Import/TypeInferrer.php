<?php

namespace App\Services\Import;

/**
 * Deterministic, keyword-driven type inference shared by both document
 * parsers. Returns null when nothing matches confidently — those fields are
 * "ambiguous" and may be resolved by a single batched AI call later.
 */
class TypeInferrer
{
    /** Ordered: first confident match wins. */
    private const PATTERNS = [
        'email' => '/\be-?mail\b/i',
        'phone' => '/\b(phone|mobile|whatsapp|contact\s*(no|number))\b/i',
        'file' => '/\b(upload|attach|resume|cv|photo|document|file|certificate)\b/i',
        'date' => '/\b(date|dob|birth|deadline|joining|available from)\b/i',
        'rating' => '/\b(rating|rate\b|scale of|stars)\b/i',
        'number' => '/\b(number of|how many|age|years?|salary|amount|count|quantity|marks|percentage|cgpa|score)\b/i',
        'textarea' => '/\b(describe|description|comments?|feedback|details|address|explain|why|tell us|about your)\b/i',
    ];

    private const URL_PATTERN = '/\b(website|url|link|portfolio|github|linkedin)\b/i';

    /**
     * @return array{type: ?string, validation: ?array} type null = ambiguous
     */
    public function infer(string $label): array
    {
        foreach (self::PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $label)) {
                return ['type' => $type, 'validation' => null];
            }
        }

        if (preg_match(self::URL_PATTERN, $label)) {
            return ['type' => 'text', 'validation' => ['url' => true]];
        }

        return ['type' => null, 'validation' => null];
    }

    /**
     * Infer a type from sample cell values (Excel header-row layout).
     */
    public function inferFromSamples(string $label, array $samples): array
    {
        $byLabel = $this->infer($label);
        if ($byLabel['type'] !== null) {
            return $byLabel;
        }

        $samples = array_values(array_filter(
            array_map(fn ($sample) => trim((string) $sample), $samples),
            fn ($sample) => $sample !== '',
        ));

        if ($samples === []) {
            return ['type' => null, 'validation' => null];
        }

        $matches = fn (callable $test) => count(array_filter($samples, $test)) === count($samples);

        if ($matches(fn ($sample) => filter_var($sample, FILTER_VALIDATE_EMAIL) !== false)) {
            return ['type' => 'email', 'validation' => null];
        }

        if ($matches(fn ($sample) => is_numeric($sample))) {
            return ['type' => 'number', 'validation' => null];
        }

        if ($matches(fn ($sample) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$|^\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}$/', $sample))) {
            return ['type' => 'date', 'validation' => null];
        }

        // Few distinct repeated values across many rows: a choice list.
        $distinct = array_unique(array_map('mb_strtolower', $samples));
        if (count($samples) >= 4 && count($distinct) <= 3) {
            return ['type' => 'dropdown', 'validation' => null];
        }

        return ['type' => null, 'validation' => null];
    }
}
