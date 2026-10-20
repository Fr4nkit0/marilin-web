<?php

declare(strict_types=1);

use App\Models\Driver;
use App\Models\Route;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->actingAs(User::factory()->superUser()->create());
});

test('the dashboard shows the requested day and each driver row carries the driver id for the cash close link', function (): void {
    $driver = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Juan Pérez'])->id]);
    Route::factory()->create(['assigned_driver_id' => $driver->id, 'date' => '2026-10-19', 'status' => 'COMPLETED']);
    Sale::factory()->create(['date' => '2026-10-19 10:00:00', 'total' => 1200]);
    Sale::factory()->create(['date' => '2026-10-20 10:00:00', 'total' => 900]);

    $this->get(route('dashboard', ['date' => '2026-10-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('metrics.date', '2026-10-19')
            ->where('metrics.kpis.totalSales', 1200)
            ->where('metrics.repartidores.0.id', $driver->id)
            ->where('metrics.repartidores.0.name', 'Juan Pérez'));
});
