<?php

use App\Models\User;

it('lets a tenant list, create, update, delete their own team members', function () {
    $tenant = User::factory()->tenant()->create();
    User::factory()->teamMemberOf($tenant)->count(2)->create();

    $this->actingAs($tenant)->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Team/Index')->has('members.data', 2));

    $this->actingAs($tenant)->post(route('team.store'), [
        'name' => 'New Hire',
        'email' => 'newhire@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    $member = User::where('email', 'newhire@example.com')->sole();
    expect($member->role)->toBe(User::ROLE_USER)
        ->and($member->tenant_id)->toBe($tenant->id);

    $this->actingAs($tenant)->put(route('team.update', $member), [
        'name' => 'New Hire Jr',
        'email' => 'newhire@example.com',
    ])->assertRedirect();
    expect($member->refresh()->name)->toBe('New Hire Jr');

    $this->actingAs($tenant)->delete(route('team.destroy', $member))->assertRedirect();
    expect(User::find($member->id))->toBeNull();
});

it('cannot manage another tenant\'s team members', function () {
    $tenantA = User::factory()->tenant()->create();
    $tenantB = User::factory()->tenant()->create();
    $memberOfB = User::factory()->teamMemberOf($tenantB)->create();

    $this->actingAs($tenantA)->put(route('team.update', $memberOfB), ['name' => 'X', 'email' => 'x@example.com'])
        ->assertNotFound();
    $this->actingAs($tenantA)->delete(route('team.destroy', $memberOfB))->assertNotFound();
});

it('blocks user-role accounts from every team route', function () {
    $tenant = User::factory()->tenant()->create();
    $member = User::factory()->teamMemberOf($tenant)->create();

    $this->actingAs($member)->get(route('team.index'))->assertNotFound();
    $this->actingAs($member)->post(route('team.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123',
    ])->assertNotFound();
});

it('removing a team member never touches the tenant\'s forms', function () {
    $tenant = User::factory()->tenant()->create();
    $member = User::factory()->teamMemberOf($tenant)->create();
    \App\Models\Form::factory()->for($tenant)->count(3)->create();

    $this->actingAs($tenant)->delete(route('team.destroy', $member));

    expect(\App\Models\Form::count())->toBe(3);
});
