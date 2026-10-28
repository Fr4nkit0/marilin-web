<?php

use App\Accounts\Models\User;

// Accounts are created by the admin; nobody can sign up on their own.

test('the registration screen does not exist', function () {
    $this->get('/register')->assertNotFound();
});

test('nobody can create an account by posting to register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a guest who opens the home page lands on the login, which offers no sign up', function () {
    $this->followingRedirects()->get('/')
        ->assertInertia(fn ($page) => $page->component('Accounts/Login')->missing('canRegister'));
});
