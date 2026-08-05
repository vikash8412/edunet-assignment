<?php

use App\Services\Schema\ConditionEvaluator;

beforeEach(function () {
    $this->evaluator = new ConditionEvaluator();
});

function conditionalSchema(array $conditions): array
{
    $schema = baseSchema();
    $schema['sections'][0]['fields'][] = [
        'id' => 'fld_dep00001',
        'type' => 'text',
        'key' => 'dependent',
        'label' => 'Dependent',
        'required' => false,
        'conditions' => $conditions,
    ];

    return $schema;
}

it('shows fields without conditions', function () {
    $visibility = $this->evaluator->visibility(baseSchema(), []);

    expect($visibility['full_name'])->toBeTrue();
});

it('evaluates equals and not_equals', function () {
    $schema = conditionalSchema([
        'logic' => 'all',
        'rules' => [['field' => 'full_name', 'operator' => 'equals', 'value' => 'yes']],
    ]);

    expect($this->evaluator->visibility($schema, ['full_name' => 'yes'])['dependent'])->toBeTrue()
        ->and($this->evaluator->visibility($schema, ['full_name' => 'no'])['dependent'])->toBeFalse();
});

it('evaluates numeric comparisons', function () {
    $schema = conditionalSchema([
        'logic' => 'all',
        'rules' => [['field' => 'full_name', 'operator' => 'greater_than', 'value' => 10]],
    ]);

    expect($this->evaluator->visibility($schema, ['full_name' => '15'])['dependent'])->toBeTrue()
        ->and($this->evaluator->visibility($schema, ['full_name' => '5'])['dependent'])->toBeFalse()
        ->and($this->evaluator->visibility($schema, ['full_name' => 'abc'])['dependent'])->toBeFalse();
});

it('evaluates contains against arrays and strings', function () {
    $schema = conditionalSchema([
        'logic' => 'all',
        'rules' => [['field' => 'full_name', 'operator' => 'contains', 'value' => 'php']],
    ]);

    expect($this->evaluator->visibility($schema, ['full_name' => ['php', 'js']])['dependent'])->toBeTrue()
        ->and($this->evaluator->visibility($schema, ['full_name' => 'loves PHP dearly'])['dependent'])->toBeTrue()
        ->and($this->evaluator->visibility($schema, ['full_name' => ['go']])['dependent'])->toBeFalse();
});

it('combines rules with any/all logic', function () {
    $rules = [
        ['field' => 'full_name', 'operator' => 'equals', 'value' => 'a'],
        ['field' => 'full_name', 'operator' => 'equals', 'value' => 'b'],
    ];

    $all = conditionalSchema(['logic' => 'all', 'rules' => $rules]);
    $any = conditionalSchema(['logic' => 'any', 'rules' => $rules]);

    expect($this->evaluator->visibility($all, ['full_name' => 'a'])['dependent'])->toBeFalse()
        ->and($this->evaluator->visibility($any, ['full_name' => 'a'])['dependent'])->toBeTrue();
});

it('cascades hiding through chained dependencies', function () {
    $schema = conditionalSchema([
        'logic' => 'all',
        'rules' => [['field' => 'full_name', 'operator' => 'equals', 'value' => 'show']],
    ]);
    $schema['sections'][0]['fields'][] = [
        'id' => 'fld_chain001',
        'type' => 'text',
        'key' => 'grandchild',
        'label' => 'Grandchild',
        'required' => false,
        'conditions' => [
            'logic' => 'all',
            'rules' => [['field' => 'dependent', 'operator' => 'is_not_empty', 'value' => null]],
        ],
    ];

    // Parent hidden -> its stale value must not reveal the grandchild.
    $visibility = $this->evaluator->visibility($schema, [
        'full_name' => 'hide',
        'dependent' => 'stale value',
    ]);

    expect($visibility['dependent'])->toBeFalse()
        ->and($visibility['grandchild'])->toBeFalse();
});

it('treats empty checkbox arrays as empty', function () {
    $schema = conditionalSchema([
        'logic' => 'all',
        'rules' => [['field' => 'full_name', 'operator' => 'is_empty', 'value' => null]],
    ]);

    expect($this->evaluator->visibility($schema, ['full_name' => []])['dependent'])->toBeTrue()
        ->and($this->evaluator->visibility($schema, ['full_name' => 'x'])['dependent'])->toBeFalse();
});
