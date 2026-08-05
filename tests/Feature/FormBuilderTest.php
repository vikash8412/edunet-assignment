<?php

use App\Models\Form;
use App\Models\User;

it('lists only the owner\'s forms', function () {
    $user = User::factory()->create();
    Form::factory()->for($user)->count(2)->create();
    Form::factory()->create(); // someone else's

    $response = $this->actingAs($user)->get(route('forms.index'));

    $response->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Forms/Index')
            ->has('forms.data', 2),
    );
});

it('creates a form with a valid schema and records version 1', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('forms.store'), [
        'schema' => baseSchema(),
        'status' => 'published',
    ]);

    $form = Form::sole();

    $response->assertRedirect(route('forms.edit', ['form' => $form->id, 'step' => 'finish']));

    expect($form->title)->toBe('Test form')
        ->and($form->status)->toBe('published')
        ->and($form->current_version)->toBe(1)
        ->and($form->public_id)->not->toBeEmpty()
        ->and($form->versions()->count())->toBe(1)
        ->and($form->versions->first()->schema)->toBe(baseSchema());
});

it('rejects an invalid schema with readable errors', function () {
    $user = User::factory()->create();

    $schema = baseSchema();
    $schema['sections'][0]['fields'][0]['type'] = 'quantum';

    $response = $this->actingAs($user)->post(route('forms.store'), [
        'schema' => $schema,
    ]);

    $response->assertSessionHasErrors('schema');
    expect(Form::count())->toBe(0);
});

it('appends a new version only when the schema changed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('forms.store'), ['schema' => baseSchema()]);
    $form = Form::sole();

    // Same schema again: no new version.
    $this->actingAs($user)
        ->put(route('forms.update', $form), ['schema' => baseSchema(), 'status' => 'published'])
        ->assertRedirect();

    expect($form->refresh()->current_version)->toBe(1)
        ->and($form->status)->toBe('published');

    // Changed schema: version 2.
    $changed = baseSchema(['title' => 'Renamed form']);
    $this->actingAs($user)->put(route('forms.update', $form), ['schema' => $changed]);

    expect($form->refresh()->current_version)->toBe(2)
        ->and($form->title)->toBe('Renamed form')
        ->and($form->versions()->count())->toBe(2);
});

it('blocks editing forms owned by someone else', function () {
    $stranger = User::factory()->create();
    $form = Form::factory()->create();

    $this->actingAs($stranger)->get(route('forms.edit', $form))->assertForbidden();
    $this->actingAs($stranger)
        ->put(route('forms.update', $form), ['schema' => baseSchema()])
        ->assertForbidden();
    $this->actingAs($stranger)->delete(route('forms.destroy', $form))->assertForbidden();
});

it('soft deletes forms', function () {
    $user = User::factory()->create();
    $form = Form::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('forms.destroy', $form))
        ->assertRedirect(route('forms.index'));

    expect(Form::count())->toBe(0)
        ->and(Form::withTrashed()->count())->toBe(1);
});

it('requires authentication for the builder', function () {
    $this->get(route('forms.index'))->assertRedirect(route('login'));
    $this->post(route('forms.store'), ['schema' => baseSchema()])->assertRedirect(route('login'));
});
