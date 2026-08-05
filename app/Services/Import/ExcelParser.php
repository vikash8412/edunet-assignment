<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * .xlsx -> draft schema parser supporting two layouts:
 *
 *  A) Field template (documented in README + samples/fields-template.xlsx):
 *     header row "Label | Type | Required | Options | Help | Placeholder",
 *     one field per row, options separated by ";" or ",".
 *
 *  B) Plain header row: first row = one column per field; up to 5 data rows
 *     are sampled to infer types (emails, numbers, dates, small choice sets).
 */
class ExcelParser
{
    private const SAMPLE_ROWS = 5;

    public function __construct(private readonly TypeInferrer $inferrer = new TypeInferrer())
    {
    }

    /**
     * @return array{schema: array, ambiguousKeys: list<string>, warnings: list<string>}
     */
    public function parse(string $path, string $originalName = 'Imported form'): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
        } catch (Throwable $e) {
            return [
                'schema' => ['title' => 'Imported form', 'sections' => []],
                'ambiguousKeys' => [],
                'warnings' => ['Could not read the spreadsheet: '.$e->getMessage()],
            ];
        }

        // Drop fully-empty rows at the top.
        while ($rows !== [] && $this->rowEmpty($rows[0])) {
            array_shift($rows);
        }

        if ($rows === []) {
            return [
                'schema' => ['title' => 'Imported form', 'sections' => []],
                'ambiguousKeys' => [],
                'warnings' => ['The sheet is empty.'],
            ];
        }

        $title = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Imported form';
        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), $rows[0]);

        return $this->isTemplateLayout($header)
            ? $this->parseTemplate($rows, $title)
            : $this->parseHeaderRow($rows, $title);
    }

    private function isTemplateLayout(array $header): bool
    {
        return in_array('label', $header, true) && in_array('type', $header, true);
    }

    private function parseTemplate(array $rows, string $title): array
    {
        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), array_shift($rows));
        $column = fn (string $name) => array_search($name, $header, true);

        $labelCol = $column('label');
        $typeCol = $column('type');
        $requiredCol = $column('required');
        $optionsCol = $column('options');
        $helpCol = $column('help');
        $placeholderCol = $column('placeholder');

        $fields = [];
        $warnings = [];

        foreach ($rows as $i => $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }

            $label = trim((string) ($row[$labelCol] ?? ''));

            if ($label === '') {
                $warnings[] = 'Row '.($i + 2).' has no label — skipped.';
                continue;
            }

            $options = array_values(array_filter(array_map(
                'trim',
                preg_split('/[;,]/', (string) ($row[$optionsCol] ?? '')) ?: [],
            )));

            $fields[] = [
                'type' => trim((string) ($row[$typeCol] ?? 'text')) ?: 'text',
                'label' => $label,
                'required' => filter_var($row[$requiredCol] ?? false, FILTER_VALIDATE_BOOLEAN),
                'help' => trim((string) ($row[$helpCol] ?? '')) ?: null,
                'placeholder' => trim((string) ($row[$placeholderCol] ?? '')) ?: null,
                'options' => $options,
            ];
        }

        return [
            'schema' => [
                'title' => $title,
                'sections' => [['title' => 'Form', 'fields' => $fields]],
            ],
            // Unknown "type" cells are handled by the normalizer's alias map.
            'ambiguousKeys' => [],
            'warnings' => $warnings,
        ];
    }

    private function parseHeaderRow(array $rows, string $title): array
    {
        $header = array_shift($rows);
        $samples = array_slice($rows, 0, self::SAMPLE_ROWS);

        $fields = [];
        $ambiguous = [];
        $warnings = [];

        foreach ($header as $col => $cell) {
            $label = trim((string) $cell);

            if ($label === '') {
                continue;
            }

            $columnSamples = array_map(fn ($row) => $row[$col] ?? null, $samples);
            $inferred = $this->inferrer->inferFromSamples($label, $columnSamples);

            $field = [
                'type' => $inferred['type'] ?? 'text',
                'label' => $label,
                'required' => false,
                'validation' => $inferred['validation'],
                'options' => [],
            ];

            // Small repeated value sets become dropdown options.
            if ($inferred['type'] === 'dropdown') {
                $distinct = array_values(array_unique(array_filter(array_map(
                    fn ($sample) => trim((string) $sample),
                    $columnSamples,
                ))));
                $field['options'] = $distinct;
            }

            if ($inferred['type'] === null) {
                $ambiguous[] = $label;
            }

            $fields[] = $field;
        }

        if ($fields === []) {
            $warnings[] = 'No usable header cells found in the first row.';
        }

        return [
            'schema' => [
                'title' => $title,
                'sections' => [['title' => 'Form', 'fields' => $fields]],
            ],
            'ambiguousKeys' => $ambiguous,
            'warnings' => $warnings,
        ];
    }

    private function rowEmpty(array $row): bool
    {
        return array_filter($row, fn ($cell) => trim((string) $cell) !== '') === [];
    }
}
