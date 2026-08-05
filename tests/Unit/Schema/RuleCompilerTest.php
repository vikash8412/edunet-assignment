<?php

use App\Services\Schema\RuleCompiler;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->compiler = new RuleCompiler();
});

function schemaWithField(array $field): array
{
    $schema = baseSchema();
    $schema['sections'][0]['fields'] = [array_merge([
        'id' => 'fld_test0001',
        'key' => 'subject',
        'label' => 'Subject',
        'required' => false,
    ], $field)];

    return $schema;
}

it('marks required fields required and optional fields nullable', function () {
    $compiled = $this->compiler->compile(baseSchema(), []);

    expect($compiled['rules']['full_name'])->toContain('required');

    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['required'] = false;
    $compiled = $this->compiler->compile($schema, []);

    expect($compiled['rules']['full_name'])->toContain('nullable');
});

it('compiles number rules with bounds', function () {
    $compiled = $this->compiler->compile(schemaWithField([
        'type' => 'number',
        'validation' => ['min' => 1, 'max' => 10, 'integer' => true],
    ]), []);

    expect($compiled['rules']['subject'])
        ->toContain('numeric')
        ->toContain('integer')
        ->toContain('min:1')
        ->toContain('max:10');
});

it('compiles email, url and regex rules', function () {
    $email = $this->compiler->compile(schemaWithField(['type' => 'email']), []);
    expect($email['rules']['subject'])->toContain('email:rfc');

    $url = $this->compiler->compile(schemaWithField([
        'type' => 'text',
        'validation' => ['url' => true],
    ]), []);
    expect($url['rules']['subject'])->toContain('url');

    $regex = $this->compiler->compile(schemaWithField([
        'type' => 'text',
        'validation' => ['pattern' => '^[A-Z]{3}$'],
    ]), []);
    expect(implode('|', array_map('strval', $regex['rules']['subject'])))->toContain('regex:');
});

it('restricts choice fields to their option values', function () {
    $compiled = $this->compiler->compile(schemaWithField([
        'type' => 'radio',
        'options' => [
            ['label' => 'Red', 'value' => 'red'],
            ['label' => 'Blue', 'value' => 'blue'],
        ],
    ]), []);

    $validator = Validator::make(['subject' => 'green'], $compiled['rules']);
    expect($validator->fails())->toBeTrue();

    $validator = Validator::make(['subject' => 'red'], $compiled['rules']);
    expect($validator->passes())->toBeTrue();
});

it('validates checkbox groups as arrays of allowed values', function () {
    $compiled = $this->compiler->compile(schemaWithField([
        'type' => 'checkbox',
        'required' => true,
        'options' => [
            ['label' => 'PHP', 'value' => 'php'],
            ['label' => 'JS', 'value' => 'js'],
        ],
    ]), []);

    expect(Validator::make(['subject' => []], $compiled['rules'])->fails())->toBeTrue()
        ->and(Validator::make(['subject' => ['php', 'go']], $compiled['rules'])->fails())->toBeTrue()
        ->and(Validator::make(['subject' => ['php', 'js']], $compiled['rules'])->passes())->toBeTrue();
});

it('compiles file rules with mimes and size', function () {
    $compiled = $this->compiler->compile(schemaWithField([
        'type' => 'file',
        'validation' => ['mimes' => ['pdf', 'docx'], 'maxSizeKb' => 2048],
    ]), []);

    $flat = implode('|', array_map('strval', $compiled['rules']['subject']));

    expect($flat)->toContain('file')
        ->toContain('mimes:pdf,docx')
        ->toContain('max:2048');
});

it('bounds ratings to the configured scale', function () {
    $compiled = $this->compiler->compile(schemaWithField([
        'type' => 'rating',
        'validation' => ['max' => 5],
    ]), []);

    expect(Validator::make(['subject' => 6], $compiled['rules'])->fails())->toBeTrue()
        ->and(Validator::make(['subject' => 5], $compiled['rules'])->passes())->toBeTrue();
});

it('applies date window rules', function () {
    $compiled = $this->compiler->compile(schemaWithField([
        'type' => 'date',
        'validation' => ['minDate' => '2026-01-01', 'maxDate' => '2026-12-31'],
    ]), []);

    expect(Validator::make(['subject' => '2025-06-01'], $compiled['rules'])->fails())->toBeTrue()
        ->and(Validator::make(['subject' => '2026-06-01'], $compiled['rules'])->passes())->toBeTrue();
});

it('skips display fields entirely', function () {
    $compiled = $this->compiler->compile(schemaWithField(['type' => 'heading']), []);

    expect($compiled['rules'])->not->toHaveKey('subject');
});

it('drops required from fields hidden by conditions', function () {
    $schema = baseSchema();
    $schema['sections'][0]['fields'][] = [
        'id' => 'fld_cond0001',
        'type' => 'text',
        'key' => 'other_reason',
        'label' => 'Other reason',
        'required' => true,
        'conditions' => [
            'logic' => 'all',
            'rules' => [['field' => 'full_name', 'operator' => 'equals', 'value' => 'other']],
        ],
    ];

    // Condition not met -> hidden -> no rules for it.
    $compiled = $this->compiler->compile($schema, ['full_name' => 'john']);
    expect($compiled['rules'])->not->toHaveKey('other_reason')
        ->and($compiled['visible']['other_reason'])->toBeFalse();

    // Condition met -> required as authored.
    $compiled = $this->compiler->compile($schema, ['full_name' => 'other']);
    expect($compiled['rules']['other_reason'])->toContain('required')
        ->and($compiled['visible']['other_reason'])->toBeTrue();
});

it('uses field labels as validation attribute names', function () {
    $compiled = $this->compiler->compile(baseSchema(), []);

    expect($compiled['attributes']['full_name'])->toBe('Full name');
});
