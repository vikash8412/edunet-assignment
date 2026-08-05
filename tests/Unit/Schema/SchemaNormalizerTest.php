<?php

use App\Services\Schema\SchemaNormalizer;
use App\Services\Schema\SchemaValidator;

beforeEach(function () {
    $this->normalizer = new SchemaNormalizer();
});

it('maps aliased field types to canonical ones with a warning', function () {
    $result = $this->normalizer->normalize([
        'title' => 'Aliases',
        'sections' => [[
            'title' => 'S',
            'fields' => [
                ['type' => 'tel', 'label' => 'Phone'],
                ['type' => 'select', 'label' => 'Pick one', 'options' => ['A', 'B']],
                ['type' => 'long_text', 'label' => 'Story'],
            ],
        ]],
    ]);

    $fields = $result['schema']['sections'][0]['fields'];

    expect($fields[0]['type'])->toBe('phone')
        ->and($fields[1]['type'])->toBe('dropdown')
        ->and($fields[2]['type'])->toBe('textarea')
        ->and($result['warnings'])->not->toBeEmpty();
});

it('falls back to text for unknown types', function () {
    $result = $this->normalizer->normalize([
        'title' => 'Unknown',
        'sections' => [[
            'title' => 'S',
            'fields' => [['type' => 'hologram', 'label' => 'Weird']],
        ]],
    ]);

    expect($result['schema']['sections'][0]['fields'][0]['type'])->toBe('text')
        ->and(implode(' ', $result['warnings']))->toContain('hologram');
});

it('wraps root-level fields into a section', function () {
    $result = $this->normalizer->normalize([
        'title' => 'Flat',
        'fields' => [['type' => 'text', 'label' => 'Name']],
    ]);

    expect($result['schema']['sections'])->toHaveCount(1)
        ->and($result['schema']['sections'][0]['fields'])->toHaveCount(1);
});

it('generates ids and unique keys when missing', function () {
    $result = $this->normalizer->normalize([
        'title' => 'Keys',
        'sections' => [[
            'title' => 'S',
            'fields' => [
                ['type' => 'text', 'label' => 'Email Address'],
                ['type' => 'text', 'label' => 'Email Address'],
            ],
        ]],
    ]);

    $fields = $result['schema']['sections'][0]['fields'];

    expect($fields[0]['key'])->toBe('email_address')
        ->and($fields[1]['key'])->toBe('email_address_2')
        ->and($fields[0]['id'])->toMatch('/^fld_/')
        ->and($result['schema']['sections'][0]['id'])->toMatch('/^sec_/');
});

it('converts string options into label/value pairs', function () {
    $result = $this->normalizer->normalize([
        'title' => 'Options',
        'sections' => [[
            'title' => 'S',
            'fields' => [[
                'type' => 'radio',
                'label' => 'Colour',
                'options' => ['Deep Red', 'Sky Blue'],
            ]],
        ]],
    ]);

    expect($result['schema']['sections'][0]['fields'][0]['options'])->toBe([
        ['label' => 'Deep Red', 'value' => 'deep_red'],
        ['label' => 'Sky Blue', 'value' => 'sky_blue'],
    ]);
});

it('downgrades choice fields without options to text', function () {
    $result = $this->normalizer->normalize([
        'title' => 'No options',
        'sections' => [[
            'title' => 'S',
            'fields' => [['type' => 'dropdown', 'label' => 'Pick']],
        ]],
    ]);

    expect($result['schema']['sections'][0]['fields'][0]['type'])->toBe('text')
        ->and(implode(' ', $result['warnings']))->toContain('no options');
});

it('produces schemas that pass strict validation for messy input', function () {
    $messy = [
        'title' => str_repeat('Very long title ', 30),
        'sections' => [
            'not-an-object',
            [
                'title' => 'Real section',
                'fields' => [
                    ['type' => 'TEL', 'label' => 'Phone!'],
                    ['type' => 'checkboxes', 'label' => 'Skills', 'choices' => ['PHP', 'JS', 'PHP']],
                    ['type' => 'file', 'label' => 'CV', 'default' => 'oops', 'validation' => ['mimes' => ['.PDF', 'docx'], 'maxSizeKb' => '2048']],
                    'garbage',
                ],
            ],
        ],
    ];

    $result = $this->normalizer->normalize($messy);
    $validation = (new SchemaValidator())->validate($result['schema']);

    expect($validation->valid)->toBeTrue()
        ->and($validation->errors)->toBeEmpty();
});

it('handles a completely empty payload', function () {
    $result = $this->normalizer->normalize([]);
    $validation = (new SchemaValidator())->validate($result['schema']);

    expect($validation->valid)->toBeTrue()
        ->and($result['schema']['title'])->toBe('Untitled form');
});
