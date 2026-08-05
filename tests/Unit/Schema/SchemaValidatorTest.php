<?php

use App\Services\Schema\SchemaValidator;

beforeEach(function () {
    $this->validator = new SchemaValidator();
});

it('accepts a minimal valid schema', function () {
    $result = $this->validator->validate(baseSchema());

    expect($result->valid)->toBeTrue()
        ->and($result->errors)->toBeEmpty();
});

it('accepts every supported field type', function () {
    $fields = [];
    $types = [
        'text', 'textarea', 'number', 'email', 'phone', 'date',
        'dropdown', 'radio', 'checkbox', 'file', 'rating', 'hidden',
        'heading', 'paragraph',
    ];

    foreach ($types as $i => $type) {
        $field = [
            'id' => sprintf('fld_type%04d', $i),
            'type' => $type,
            'key' => 'field_'.$type,
            'label' => ucfirst($type).' field',
            'required' => false,
        ];

        if (in_array($type, ['dropdown', 'radio', 'checkbox'], true)) {
            $field['options'] = [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No', 'value' => 'no'],
            ];
        }

        $fields[] = $field;
    }

    $schema = baseSchema();
    $schema['sections'][0]['fields'] = $fields;

    $result = $this->validator->validate($schema);

    expect($result->valid)->toBeTrue()->and($result->errors)->toBeEmpty();
});

it('rejects a schema without a title', function () {
    $schema = baseSchema();
    unset($schema['title']);

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});

it('rejects unknown field types', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['type'] = 'quantum_input';

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});

it('rejects invalid field keys', function (string $badKey) {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['key'] = $badKey;

    expect($this->validator->validate($schema)->valid)->toBeFalse();
})->with(['Full Name', '1starts_with_digit', 'UPPER', 'has-dash', '']);

it('rejects duplicate field keys across sections', function () {
    $schema = baseSchema();
    $schema['sections'][] = [
        'id' => 'sec_efgh5678',
        'title' => 'Second',
        'description' => null,
        'fields' => [
            [
                'id' => 'fld_efgh5678',
                'type' => 'text',
                'key' => 'full_name',
                'label' => 'Duplicate key',
                'required' => false,
            ],
        ],
    ];

    $result = $this->validator->validate($schema);

    expect($result->valid)->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('duplicate field key');
});

it('rejects choice fields without options', function (string $type) {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['type'] = $type;
    $schema['sections'][0]['fields'][0]['options'] = [];

    $result = $this->validator->validate($schema);

    expect($result->valid)->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('at least one option');
})->with(['dropdown', 'radio', 'checkbox']);

it('rejects duplicate option values', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['type'] = 'dropdown';
    $schema['sections'][0]['fields'][0]['options'] = [
        ['label' => 'One', 'value' => 'same'],
        ['label' => 'Two', 'value' => 'same'],
    ];

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});

it('rejects required display fields', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['type'] = 'heading';
    $schema['sections'][0]['fields'][0]['required'] = true;

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});

it('rejects conditions that reference unknown fields', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['conditions'] = [
        'logic' => 'all',
        'rules' => [['field' => 'ghost_field', 'operator' => 'equals', 'value' => 'x']],
    ];

    $result = $this->validator->validate($schema);

    expect($result->valid)->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('unknown field key');
});

it('rejects self-referencing conditions', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['conditions'] = [
        'logic' => 'all',
        'rules' => [['field' => 'full_name', 'operator' => 'is_empty', 'value' => null]],
    ];

    $result = $this->validator->validate($schema);

    expect($result->valid)->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('cannot depend on itself');
});

it('rejects an invalid regex pattern', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['validation'] = ['pattern' => '[unclosed'];

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});

it('rejects minLength greater than maxLength', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['validation'] = ['minLength' => 50, 'maxLength' => 10];

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});

it('rejects schemas with zero sections', function () {
    $schema = baseSchema();
    $schema['sections'] = [];

    expect($this->validator->validate($schema)->valid)->toBeFalse();
});
