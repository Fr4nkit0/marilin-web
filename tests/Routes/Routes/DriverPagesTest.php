<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
    $this->user = User::factory()->create(['name' => 'Juan Pérez', 'email' => null, 'username' => 'jperez']);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
});

test('the drivers page shows each driver by username, not email', function (): void {
    $this->actingAs($this->admin)->get(route('drivers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Routes/Routes/Drivers')
            ->where('repartidores.0.username', 'jperez')
            ->missing('repartidores.0.email'));
});

test('the driver page shows the username, not email', function (): void {
    $this->actingAs($this->admin)->get(route('drivers.show', $this->driver))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Routes/Routes/Driver')
            ->where('repartidor.username', 'jperez')
            ->missing('repartidor.email'));
});

test('the driver page shows the day asked for, and today when none is', function (): void {
    $this->actingAs($this->admin)->get(route('drivers.show', ['repartidor' => $this->driver, 'date' => '2026-11-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('targetDate', '2026-11-12'));

    $this->actingAs($this->admin)->get(route('drivers.show', $this->driver))
        ->assertInertia(fn (Assert $page) => $page->where('targetDate', now()->toDateString()));
});
