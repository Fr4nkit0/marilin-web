<?php

use App\Accounts\Models\User;

it('sends the home page to the dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});

it('sends a guest that opens the home page on to the login', function () {
    $this->followingRedirects()->get('/')->assertInertia(fn ($page) => $page->component('Accounts/Login'));
});

it('lands a signed-in admin on the dashboard', function () {
    $this->actingAs(User::factory()->superUser()->create())
        ->followingRedirects()
        ->get('/')
        ->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
});
