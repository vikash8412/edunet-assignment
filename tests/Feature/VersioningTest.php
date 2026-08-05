<?php

use App\Models\Form;
use App\Models\User;
use App\Services\Schema\SchemaDiff;

it('computes field-level diffs between versions', function () {
    $differ = new SchemaDiff();

    $old = baseSchema();
    $new = baseSchema(['title' => 'Renamed']);
    $new['sections'][0]['fields'][0]['required'] = false; // changed
    $new['sections'][0]['fields'][] = [
        'id' => 'fld_new00001', 'type' => 'email', 'key' => 'email', 'label' => 'Email',
    ]; // added

    $diff = $differ->diff($old, $new);

    expect($diff['title_changed'])->toBeTrue()
        ->and($diff['added'])->toHaveCount(1)
        ->and($diff['added'][0]['key'])->toBe('email')
        ->and($diff['removed'])->toBeEmpty()
        ->and($diff['changed'])->toHaveCount(1)
        ->and($diff['changed'][0]['props'])->toBe(['required']);
});

it('shows version history with diffs', function () {
    $form = Form::factory()->create();

    $changed = $form->schema;
    $changed['sections'][0]['fields'][0]['label'] = 'Renamed field';
    $form->saveSchemaVersion($changed, $form->user_id);

    $this->actingAs($form->user)
        ->get(route('forms.versions.index', $form))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Forms/Versions')
                ->has('versions', 2)
                ->where('versions.0.version', 2)
                ->where('versions.0.is_current', true)
                ->where('versions.0.diff.changed.0.props.0', 'label')
                ->where('versions.1.diff', null),
        );
});

it('rolls back by appending the old snapshot as a new version', function () {
    $form = Form::factory()->create();
    $originalSchema = $form->schema;

    $changed = $form->schema;
    $changed['title'] = 'Changed title';
    $form->saveSchemaVersion($changed, $form->user_id);

    $v1 = $form->versions()->where('version', 1)->first();

    $this->actingAs($form->user)
        ->post(route('forms.versions.rollback', [$form, $v1]))
        ->assertRedirect(route('forms.versions.index', $form));

    $form->refresh();

    expect($form->current_version)->toBe(3)
        ->and($form->schema)->toBe($originalSchema)
        ->and($form->title)->toBe($originalSchema['title'])
        ->and($form->versions()->count())->toBe(3)
        ->and($form->versions()->where('version', 3)->first()->source)->toBe('rollback');
});

it('refuses rollback to the current version or foreign forms', function () {
    $form = Form::factory()->create();
    $current = $form->versions()->first();

    $this->actingAs($form->user)
        ->post(route('forms.versions.rollback', [$form, $current]))
        ->assertStatus(422);

    $other = Form::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('forms.versions.rollback', [$other, $other->versions()->first()]))
        ->assertForbidden();
});
