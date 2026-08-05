<?php

use App\Models\Form;
use App\Models\User;

it('lets super list, create, update companies', function () {
    $super = User::factory()->super()->create();
    User::factory()->tenant()->count(2)->create();

    $this->actingAs($super)->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Companies/Index')->has('companies.data', 2));

    $this->actingAs($super)->post(route('companies.store'), [
        'name' => 'Acme Inc',
        'email' => 'acme@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    $tenant = User::where('email', 'acme@example.com')->sole();
    expect($tenant->role)->toBe(User::ROLE_TENANT)
        ->and($tenant->tenant_id)->toBeNull();

    $this->actingAs($super)->put(route('companies.update', $tenant), [
        'name' => 'Acme Incorporated',
        'email' => 'acme@example.com',
        'password' => '',
    ])->assertRedirect();

    expect($tenant->refresh()->name)->toBe('Acme Incorporated');
});

it('blocks non-super from every companies route with 404', function () {
    $tenant = User::factory()->tenant()->create();
    $target = User::factory()->tenant()->create();

    $this->actingAs($tenant)->get(route('companies.index'))->assertNotFound();
    $this->actingAs($tenant)->post(route('companies.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123',
    ])->assertNotFound();
    $this->actingAs($tenant)->put(route('companies.update', $target), [
        'name' => 'X', 'email' => 'x2@example.com',
    ])->assertNotFound();
    $this->actingAs($tenant)->delete(route('companies.destroy', $target))->assertNotFound();

    $user = User::factory()->teamMemberOf($tenant)->create();
    $this->actingAs($user)->get(route('companies.index'))->assertNotFound();
});

it('disabling a company preserves its data but blocks logins and public forms', function () {
    $super = User::factory()->super()->create();
    $tenant = User::factory()->tenant()->create(['email' => 'owner@example.com', 'password' => 'password']);
    $teammate = User::factory()->teamMemberOf($tenant)->create(['email' => 'mate@example.com', 'password' => 'password']);
    $form = Form::factory()->for($tenant)->create(['status' => 'published']);

    $this->actingAs($super)->delete(route('companies.destroy', $tenant))->assertRedirect();

    expect($tenant->refresh()->disabled_at)->not->toBeNull()
        ->and(Form::count())->toBe(1) // data preserved
        ->and($tenant->teamMembers()->count())->toBe(1);

    // Log out the acting super — /login sits behind guest middleware and
    // would otherwise redirect away before the disabled check ever runs.
    $this->post(route('logout'));

    // Logins blocked with a specific message, not the generic "invalid credentials".
    $ownerResponse = $this->post(route('login'), ['email' => 'owner@example.com', 'password' => 'password']);
    $ownerResponse->assertSessionHasErrors('email');
    expect($ownerResponse->getSession()->get('errors')->get('email')[0])->toContain('disabled');
    $this->assertGuest();

    $this->post(route('login'), ['email' => 'mate@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    // Public fill URL now shows the closed screen.
    $this->get('/f/'.$form->public_id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Public/Closed'));

    // Restoring reverses everything.
    $this->actingAs($super)->post(route('companies.restore', $tenant))->assertRedirect();
    expect($tenant->refresh()->disabled_at)->toBeNull();

    $this->post(route('logout'));

    $this->post(route('login'), ['email' => 'owner@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('rejects a duplicate email on company creation', function () {
    $super = User::factory()->super()->create();
    User::factory()->tenant()->create(['email' => 'taken@example.com']);

    $this->actingAs($super)->post(route('companies.store'), [
        'name' => 'Dup',
        'email' => 'taken@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');
});
