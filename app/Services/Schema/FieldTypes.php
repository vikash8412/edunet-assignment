<?php

namespace App\Services\Schema;

/**
 * Registry of supported field types. The server side is authoritative;
 * resources/js/lib/fieldTypes.js mirrors this list for the builder UI.
 */
class FieldTypes
{
    /** Types that collect a value from the respondent. */
    public const INPUT_TYPES = [
        'text', 'textarea', 'number', 'email', 'phone', 'date',
        'dropdown', 'radio', 'checkbox', 'file', 'rating', 'hidden',
    ];

    /** Display-only types (never collect data, never required). */
    public const DISPLAY_TYPES = ['heading', 'paragraph'];

    /** Types whose value comes from a fixed option list. */
    public const CHOICE_TYPES = ['dropdown', 'radio', 'checkbox'];

    /**
     * Aliases for field types LLMs and import heuristics tend to produce.
     * Maps foreign name => canonical type.
     */
    public const ALIASES = [
        'tel' => 'phone',
        'telephone' => 'phone',
        'mobile' => 'phone',
        'phone_number' => 'phone',
        'select' => 'dropdown',
        'select_one' => 'dropdown',
        'combobox' => 'dropdown',
        'choice' => 'radio',
        'radio_group' => 'radio',
        'multiple_choice' => 'radio',
        'single_choice' => 'radio',
        'checkboxes' => 'checkbox',
        'checkbox_group' => 'checkbox',
        'multiselect' => 'checkbox',
        'multi_select' => 'checkbox',
        'string' => 'text',
        'input' => 'text',
        'text_input' => 'text',
        'shorttext' => 'text',
        'short_text' => 'text',
        'longtext' => 'textarea',
        'long_text' => 'textarea',
        'text_area' => 'textarea',
        'paragraph_text' => 'textarea',
        'multiline' => 'textarea',
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'decimal' => 'number',
        'numeric' => 'number',
        'datetime' => 'date',
        'datepicker' => 'date',
        'date_picker' => 'date',
        'time' => 'date',
        'upload' => 'file',
        'file_upload' => 'file',
        'attachment' => 'file',
        'document' => 'file',
        'stars' => 'rating',
        'score' => 'rating',
        'scale' => 'rating',
        'title' => 'heading',
        'section_heading' => 'heading',
        'header' => 'heading',
        'description' => 'paragraph',
        'static_text' => 'paragraph',
        'info' => 'paragraph',
        'url' => 'text',
        'website' => 'text',
    ];

    public static function all(): array
    {
        return array_merge(self::INPUT_TYPES, self::DISPLAY_TYPES);
    }

    public static function exists(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    public static function isInput(string $type): bool
    {
        return in_array($type, self::INPUT_TYPES, true);
    }

    public static function isDisplay(string $type): bool
    {
        return in_array($type, self::DISPLAY_TYPES, true);
    }

    public static function hasOptions(string $type): bool
    {
        return in_array($type, self::CHOICE_TYPES, true);
    }

    /**
     * Resolve an unknown type name to a canonical one.
     * Returns null when nothing sensible matches (caller decides fallback).
     */
    public static function resolve(string $type): ?string
    {
        $normalized = strtolower(trim(str_replace(['-', ' '], '_', $type)));

        if (self::exists($normalized)) {
            return $normalized;
        }

        return self::ALIASES[$normalized] ?? null;
    }
}
