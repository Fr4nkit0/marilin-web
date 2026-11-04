<?php

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('the admin can authenticate using the login screen', function () {
    $user = User::factory()->superUser()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('the admin can not authenticate with invalid password', function () {
    $user = User::factory()->superUser()->create();

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

test('the admin can authenticate with the email written in other letter case', function () {
    $user = User::factory()->superUser()->create(['email' => 'marcos.morales@marilin.com']);

    $this->post('/login', [
        'email' => 'Marcos.Morales@marilin.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email)->toBe('marcos.morales@marilin.com');
});

test('a stored email with capitals authenticates when typed in lowercase', function () {
    $user = User::factory()->superUser()->create(['email' => 'Esteban.Ruiz@marilin.com']);

    $this->post('/login', ['email' => 'esteban.ruiz@marilin.com', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('a driver with the right email and password can not sign in to the web', function () {
    $user = User::factory()->create(['email' => 'juan@marilin.com']);
    Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);

    $this->post('/login', ['email' => 'juan@marilin.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Esta cuenta entra desde la app.']);

    $this->assertGuest();
});

test('an account that is not an admin can not sign in to the web', function () {
    User::factory()->create(['email' => 'someone@marilin.com']);

    $this->post('/login', ['email' => 'someone@marilin.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Esta cuenta entra desde la app.']);

    $this->assertGuest();
});
