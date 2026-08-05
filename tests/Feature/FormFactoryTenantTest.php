<?php

use App\Models\Form;
use App\Models\User;

// Pinning down factory correctness before touching any controller — a bug
// here would otherwise surface as a wall of confusing "tenant_id mismatch"
// failures across unrelated-looking tests.

it('derives tenant_id from a bare factory default', function () {
    $form = Form::factory()->create();

    expect($form->tenant_id)->not->toBeNull()
        ->and($form->tenant_id)->toBe($form->user_id) // default owner is role=tenant, so tenantId() === own id
        ->and(User::find($form->user_id)->role)->toBe(User::ROLE_TENANT);
});

it('derives tenant_id from an explicit user_id attribute', function () {
    $owner = User::factory()->tenant()->create();
    $form = Form::factory()->create(['user_id' => $owner->id]);

    expect($form->tenant_id)->toBe($owner->id)
        ->and($form->user_id)->toBe($owner->id);
});

it('derives tenant_id from ->for($user)', function () {
    $owner = User::factory()->tenant()->create();
    $form = Form::factory()->for($owner)->create();

    expect($form->tenant_id)->toBe($owner->id)
        ->and($form->user_id)->toBe($owner->id);
});

it('derives the tenant-owner id, not the personal id, when the creator is a team member', function () {
    $tenant = User::factory()->tenant()->create();
    $teammate = User::factory()->teamMemberOf($tenant)->create();

    $form = Form::factory()->for($teammate)->create();

    expect($form->user_id)->toBe($teammate->id) // author stays the teammate
        ->and($form->tenant_id)->toBe($tenant->id); // but tenant_id is the company
});
