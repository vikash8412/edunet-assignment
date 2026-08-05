<?php

use App\Models\User;

// Login redirects to /dashboard, which itself redirects by role — assert
// against /dashboard here, and cover /dashboard's own branching below.
it('redirects super toward /dashboard after login', function () {
    $super = User::factory()->super()->create(['email' => 'super@example.com', 'password' => 'password']);

    $this->post(route('login'), ['email' => 'super@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('redirects tenant toward /dashboard after login', function () {
    $tenant = User::factory()->tenant()->create(['email' => 'tenant@example.com', 'password' => 'password']);

    $this->post(route('login'), ['email' => 'tenant@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('redirects a team member toward /dashboard after login', function () {
    $tenant = User::factory()->tenant()->create();
    $member = User::factory()->teamMemberOf($tenant)->create(['email' => 'member@example.com', 'password' => 'password']);

    $this->post(route('login'), ['email' => 'member@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('routes /dashboard to companies for super and forms for everyone else', function () {
    $super = User::factory()->super()->create();
    $tenant = User::factory()->tenant()->create();

    $this->actingAs($super)->get('/dashboard')->assertRedirect(route('companies.index'));
    $this->actingAs($tenant)->get('/dashboard')->assertRedirect(route('forms.index'));
});
