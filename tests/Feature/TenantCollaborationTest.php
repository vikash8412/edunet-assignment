<?php

use App\Models\AiGeneration;
use App\Models\Form;
use App\Models\Import;
use App\Models\User;

it('lets a teammate view, edit and delete a form created by their tenant owner', function () {
    $tenant = User::factory()->tenant()->create();
    $teammate = User::factory()->teamMemberOf($tenant)->create();
    $form = Form::factory()->for($tenant)->create();

    $this->actingAs($teammate)->get(route('forms.edit', $form))->assertOk();

    $this->actingAs($teammate)->put(route('forms.update', $form), [
        'schema' => baseSchema(['title' => 'Edited by teammate']),
    ])->assertRedirect();
    expect($form->refresh()->title)->toBe('Edited by teammate');

    $this->actingAs($teammate)->delete(route('forms.destroy', $form))->assertRedirect();
    expect(Form::count())->toBe(0);
});

it('lets the tenant owner view, edit and delete a form created by a teammate', function () {
    $tenant = User::factory()->tenant()->create();
    $teammate = User::factory()->teamMemberOf($tenant)->create();
    $form = Form::factory()->for($teammate)->create();

    expect($form->tenant_id)->toBe($tenant->id); // sanity: factory derived the right tenant

    $this->actingAs($tenant)->get(route('forms.edit', $form))->assertOk();
    $this->actingAs($tenant)->delete(route('forms.destroy', $form))->assertRedirect();
    expect(Form::count())->toBe(0);
});

it('lets two teammates both edit the same form', function () {
    $tenant = User::factory()->tenant()->create();
    $alice = User::factory()->teamMemberOf($tenant)->create();
    $bob = User::factory()->teamMemberOf($tenant)->create();
    $form = Form::factory()->for($tenant)->create();

    $this->actingAs($alice)->put(route('forms.update', $form), [
        'schema' => baseSchema(['title' => 'Edited by Alice']),
    ])->assertRedirect();

    $this->actingAs($bob)->put(route('forms.update', $form), [
        'schema' => baseSchema(['title' => 'Edited by Bob']),
    ])->assertRedirect();

    expect($form->refresh()->title)->toBe('Edited by Bob')
        ->and($form->versions()->count())->toBe(3); // initial + Alice's + Bob's
});

it('shares AI generations across teammates in the same tenant', function () {
    $tenant = User::factory()->tenant()->create();
    $teammate = User::factory()->teamMemberOf($tenant)->create();

    $generation = AiGeneration::createForTenant($tenant, [
        'mode' => 'create',
        'prompt' => 'a contact form',
        'status' => AiGeneration::STATUS_DONE,
    ]);

    $this->actingAs($teammate)
        ->getJson(route('ai.generations.show', $generation))
        ->assertOk();
});

it('shares imports across teammates in the same tenant', function () {
    $tenant = User::factory()->tenant()->create();
    $teammate = User::factory()->teamMemberOf($tenant)->create();

    $import = Import::createForTenant($tenant, [
        'original_name' => 'x.docx',
        'path' => 'imports/x.docx',
        'kind' => 'docx',
        'status' => Import::STATUS_READY,
        'parsed_schema' => baseSchema(),
    ]);

    $this->actingAs($teammate)
        ->getJson(route('imports.show', $import))
        ->assertOk();

    $this->actingAs($teammate)->post(
        route('imports.commit', $import),
        ['schema' => baseSchema()],
    )->assertRedirect();

    expect(Form::sole()->tenant_id)->toBe($tenant->id);
});

it('lets a teammate preview a draft form created by their tenant owner', function () {
    $tenant = User::factory()->tenant()->create();
    $teammate = User::factory()->teamMemberOf($tenant)->create();
    $form = Form::factory()->draft()->for($tenant)->create();

    $this->actingAs($teammate)
        ->get('/f/'.$form->public_id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Public/Fill')->where('preview', true));
});
