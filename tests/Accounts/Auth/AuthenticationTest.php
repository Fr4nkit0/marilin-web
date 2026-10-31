<?php

use App\Accounts\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('users can authenticate with their email written in other letter case', function () {
    $user = User::factory()->create(['email' => 'marcos.morales@marilin.com']);

    $this->post('/login', [
        'email' => 'Marcos.Morales@marilin.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email)->toBe('marcos.morales@marilin.com');
});

test('a stored email with capitals authenticates when typed in lowercase', function () {
    $user = User::factory()->create(['email' => 'Esteban.Ruiz@marilin.com']);

    $this->post('/login', ['email' => 'esteban.ruiz@marilin.com', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});
