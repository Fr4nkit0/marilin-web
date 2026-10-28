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

test('the welcome page does not offer a sign up link', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('canRegister', false));
});
