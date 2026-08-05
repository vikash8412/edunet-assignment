<?php

use App\Models\AiGeneration;
use App\Models\Form;
use App\Models\Import;
use App\Models\User;

it('forbids a user from a different tenant from touching another company\'s forms', function () {
    $tenantA = User::factory()->tenant()->create();
    $tenantB = User::factory()->tenant()->create();
    $userOfB = User::factory()->teamMemberOf($tenantB)->create();
    $formOfA = Form::factory()->for($tenantA)->create();

    $this->actingAs($tenantB)->get(route('forms.edit', $formOfA))->assertForbidden();
    $this->actingAs($tenantB)->put(route('forms.update', $formOfA), ['schema' => baseSchema()])->assertForbidden();
    $this->actingAs($tenantB)->delete(route('forms.destroy', $formOfA))->assertForbidden();

    $this->actingAs($userOfB)->get(route('forms.edit', $formOfA))->assertForbidden();
});

it('forbids cross-tenant access to AI generations even between two authenticated non-stranger accounts', function () {
    $tenantA = User::factory()->tenant()->create();
    $tenantB = User::factory()->tenant()->create();

    $generation = AiGeneration::createForTenant($tenantA, [
        'mode' => 'create',
        'prompt' => 'anything',
        'status' => AiGeneration::STATUS_DONE,
    ]);

    $this->actingAs($tenantB)
        ->getJson(route('ai.generations.show', $generation))
        ->assertForbidden();
});

it('forbids cross-tenant access to imports', function () {
    $tenantA = User::factory()->tenant()->create();
    $tenantB = User::factory()->tenant()->create();

    $import = Import::createForTenant($tenantA, [
        'original_name' => 'x.docx',
        'path' => 'imports/x.docx',
        'kind' => 'docx',
        'status' => Import::STATUS_READY,
        'parsed_schema' => baseSchema(),
    ]);

    $this->actingAs($tenantB)->getJson(route('imports.show', $import))->assertForbidden();
    $this->actingAs($tenantB)
        ->post(route('imports.commit', $import), ['schema' => baseSchema()])
        ->assertForbidden();
});

it('a super sees zero forms and cannot reach the forms area at all', function () {
    $super = User::factory()->super()->create();
    Form::factory()->count(3)->create();

    $this->actingAs($super)->get(route('forms.index'))->assertNotFound();
    $this->actingAs($super)->get(route('ai.generate'))->assertNotFound();
    $this->actingAs($super)->get(route('imports.create'))->assertNotFound();
});
