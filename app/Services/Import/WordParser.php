<?php

namespace App\Services\Import;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

/**
 * Deterministic .docx -> draft schema parser.
 *
 * Heuristics (see README "Import" for the full contract):
 *  - first Title            -> form title
 *  - further Titles         -> new sections
 *  - "Question?" lines      -> fields, "Label:" lines -> fields
 *  - "(required)" / "*"     -> required flag
 *  - "(email)"-style hints  -> explicit type hint
 *  - list items / ☐ glyphs  -> options of the preceding field
 *  - 2-column table rows    -> one field per row (first cell = label)
 *  - anything else          -> reported as an unparseable block
 */
class WordParser
{
    private const CHECKBOX_GLYPHS = ['☐', '□', '☑', '⬜', '[ ]', '[]'];

    private const TYPE_HINTS = [
        'email', 'phone', 'number', 'date', 'textarea', 'dropdown',
        'radio', 'checkbox', 'file', 'rating', 'text',
    ];

    public function __construct(private readonly TypeInferrer $inferrer = new TypeInferrer())
    {
    }

    /**
     * @return array{schema: array, ambiguousKeys: list<string>, warnings: list<string>}
     */
    public function parse(string $path): array
    {
        try {
            $document = IOFactory::load($path);
        } catch (Throwable $e) {
            return [
                'schema' => ['title' => 'Imported form', 'sections' => []],
                'ambiguousKeys' => [],
                'warnings' => ['Could not read the document: '.$e->getMessage()],
            ];
        }

        $state = [
            'title' => null,
            'description' => null,
            'sections' => [],
            'current' => ['title' => 'Form', 'fields' => []],
            'lastField' => null,
            'warnings' => [],
            'ambiguous' => [],
        ];

        foreach ($document->getSections() as $docSection) {
            $this->walk($docSection, $state);
        }

        $this->flushSection($state);

        return [
            'schema' => [
                'title' => $state['title'] ?? 'Imported form',
                'description' => $state['description'],
                'sections' => $state['sections'],
            ],
            'ambiguousKeys' => $state['ambiguous'],
            'warnings' => $state['warnings'],
        ];
    }

    private function walk(AbstractContainer|Table $container, array &$state): void
    {
        foreach ($container->getElements() as $element) {
            match (true) {
                $element instanceof Title => $this->handleTitle($element, $state),
                $element instanceof ListItem => $this->handleOption($element->getTextObject()->getText(), $state),
                $element instanceof ListItemRun => $this->handleOption($this->runText($element), $state),
                $element instanceof Table => $this->handleTable($element, $state),
                $element instanceof TextRun => $this->handleLine($this->runText($element), $state),
                $element instanceof Text => $this->handleLine($element->getText() ?? '', $state),
                default => null,
            };
        }
    }

    private function runText(TextRun|ListItemRun $run): string
    {
        $text = '';

        foreach ($run->getElements() as $element) {
            if ($element instanceof Text) {
                $text .= $element->getText();
            }
        }

        return $text;
    }

    private function handleTitle(Title $title, array &$state): void
    {
        $text = trim(is_string($title->getText()) ? $title->getText() : $this->runText($title->getText()));

        if ($text === '') {
            return;
        }

        if ($state['title'] === null) {
            $state['title'] = $text;

            return;
        }

        $this->flushSection($state);
        $state['current'] = ['title' => $text, 'fields' => []];
    }

    private function handleLine(string $raw, array &$state): void
    {
        $line = trim($raw);

        if ($line === '') {
            return;
        }

        // "☐ Option" lines are options of the previous field.
        foreach (self::CHECKBOX_GLYPHS as $glyph) {
            if (str_starts_with($line, $glyph)) {
                $this->handleOption(trim(mb_substr($line, mb_strlen($glyph))), $state, checkbox: true);

                return;
            }
        }

        // Field detection must see past a trailing "(hint)" after the "?".
        $probe = trim(preg_replace('/\([^)]*\)\s*$/', '', $line));

        // The first free paragraph after the title is the description.
        $isField = str_ends_with($probe, '?') || preg_match('/^(.{2,120}?):\s*$/u', $line);

        if (! $isField) {
            if ($state['title'] !== null && $state['description'] === null
                && $state['sections'] === [] && $state['current']['fields'] === []) {
                $state['description'] = mb_substr($line, 0, 2000);
            } elseif (mb_strlen($line) > 2) {
                $state['warnings'][] = 'Could not parse block: "'.mb_substr($line, 0, 120).'"';
                $state['lastField'] = null;
            }

            return;
        }

        $this->addField($line, $state);
    }

    private function addField(string $line, array &$state): void
    {
        $label = rtrim(trim($line), ':');
        $required = false;
        $hint = null;

        if (preg_match('/\(\s*required\s*\)/i', $label)) {
            $required = true;
            $label = trim(preg_replace('/\(\s*required\s*\)/i', '', $label));
        }

        if (str_ends_with($label, '*')) {
            $required = true;
            $label = rtrim($label, "* \t");
        }

        // Explicit hints like "(email)" or "(file: pdf)" win over inference.
        if (preg_match('/\(\s*('.implode('|', self::TYPE_HINTS).')[^)]*\)\s*$/i', $label, $m)) {
            $hint = strtolower($m[1]);
            $label = trim(preg_replace('/\(\s*[^)]*\)\s*$/', '', $label));
        }

        if ($label === '') {
            return;
        }

        $inferred = $hint !== null
            ? ['type' => $hint, 'validation' => null]
            : $this->inferrer->infer($label);

        $field = [
            'type' => $inferred['type'] ?? 'text',
            'label' => $label,
            'required' => $required,
            'validation' => $inferred['validation'],
            'options' => [],
        ];

        if ($inferred['type'] === null) {
            // Remember by label; keys are assigned later by the normalizer.
            $state['ambiguous'][] = $label;
        }

        $state['current']['fields'][] = $field;
        $state['lastField'] = array_key_last($state['current']['fields']);
    }

    private function handleOption(string $text, array &$state, bool $checkbox = false): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if ($state['lastField'] === null) {
            $state['warnings'][] = 'Choice "'.mb_substr($text, 0, 60).'" appears before any question — skipped.';

            return;
        }

        $field = &$state['current']['fields'][$state['lastField']];

        // A field that accumulates options becomes a choice field.
        if (! in_array($field['type'], ['dropdown', 'radio', 'checkbox'], true)) {
            $field['type'] = $checkbox ? 'checkbox' : 'radio';
        } elseif ($checkbox) {
            $field['type'] = 'checkbox';
        }

        $field['options'][] = $text;

        // No longer ambiguous once options exist.
        $state['ambiguous'] = array_values(array_diff($state['ambiguous'], [$field['label']]));
    }

    private function handleTable(Table $table, array &$state): void
    {
        foreach ($table->getRows() as $row) {
            $cells = $row->getCells();

            if ($cells === []) {
                continue;
            }

            $label = '';
            foreach ($cells[0]->getElements() as $element) {
                if ($element instanceof TextRun) {
                    $label .= $this->runText($element);
                } elseif ($element instanceof Text) {
                    $label .= $element->getText();
                }
            }

            $label = trim($label);

            if ($label !== '' && ! preg_match('/^(field|label|question)s?$/i', $label)) {
                $this->addField(rtrim($label, ':').':', $state);
            }
        }

        $state['lastField'] = null;
    }

    private function flushSection(array &$state): void
    {
        if ($state['current']['fields'] !== []) {
            $state['sections'][] = $state['current'];
        }

        $state['current'] = ['title' => 'Section', 'fields' => []];
        $state['lastField'] = null;
    }
}
