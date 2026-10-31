<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Routes\Routes\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->actingAs(User::factory()->superUser()->create());
});

function routeDriver(string $name, string $date = '2026-10-20', string $status = 'IN_PROGRESS'): Driver
{
    $driver = Driver::factory()->create(['user_id' => User::factory()->create(['name' => $name])->id, 'enabled' => true]);
    Route::factory()->create(['assigned_driver_id' => $driver->id, 'date' => $date, 'status' => $status]);

    return $driver;
}

/**
 * @return array<string, mixed>|null
 */
function sharedNotifications(): ?array
{
    $notifications = null;
    test()->get(route('dashboard'))->assertOk()->assertInertia(function (Assert $page) use (&$notifications): void {
        $notifications = $page->toArray()['props']['notifications'] ?? null;
    });

    return $notifications;
}

test('a driver on route today who never synced is listed, linked by id', function (): void {
    $juan = routeDriver('Juan Pérez');

    expect(sharedNotifications())->toBe([
        'unsyncedDrivers' => [['id' => $juan->id, 'name' => 'Juan Pérez', 'lastSyncAt' => null]],
        'pendingReview' => 0,
    ]);
});

test('a driver on route who synced within the half hour is not listed, one quiet for longer is', function (): void {
    $juan = routeDriver('Juan Pérez');
    $esteban = routeDriver('Esteban Benítez');
    $juan->forceFill(['last_synced_at' => now()->subMinutes(4)])->save();
    $esteban->forceFill(['last_synced_at' => now()->subMinutes(45)])->save();

    expect(sharedNotifications()['unsyncedDrivers'])->toBe([
        ['id' => $esteban->id, 'name' => 'Esteban Benítez', 'lastSyncAt' => '2026-10-20T17:15:00-03:00'],
    ]);
});

test('a driver on route whose token was used without a sync is still listed as never synced', function (): void {
    $juan = routeDriver('Juan Pérez');
    $juan->user->createToken('android')->accessToken->forceFill(['last_used_at' => now()->subMinutes(2)])->save();

    expect(sharedNotifications()['unsyncedDrivers'])->toBe([
        ['id' => $juan->id, 'name' => 'Juan Pérez', 'lastSyncAt' => null],
    ]);
});

test('a route that is not today or not in progress does not raise a notification', function (): void {
    routeDriver('Juan Pérez', date: '2026-10-19');
    routeDriver('Esteban Benítez', status: 'PLANNED');
    routeDriver('Carlos Gómez', status: 'COMPLETED');

    expect(sharedNotifications()['unsyncedDrivers'])->toBe([]);
});

test('a user who does not see the review menu gets no notifications', function (): void {
    routeDriver('Juan Pérez');
    $this->actingAs(User::factory()->create(['is_super_user' => false]));

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('notifications'));
});

test('guests get no notifications', function (): void {
    auth()->logout();

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('notifications'));
});
