<?php

use Illuminate\Support\Facades\Route;

it('no longer has a register route', function () {
    expect(Route::has('register'))->toBeFalse();
});

it('returns 404 for GET and POST /register', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
