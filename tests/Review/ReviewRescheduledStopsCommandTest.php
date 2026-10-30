<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-23 01:00:00');
    $this->driver = Driver::factory()->create(['enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_primary' => true]);
});

/**
 * A stop of the driver's route of the given day; rescheduled ones point at an original.
 */
function stopOn(string $date, ?RouteStop $rescheduledFrom = null): RouteStop
{
    $route = Route::query()->firstOrCreate(
        ['assigned_driver_id' => test()->driver->id, 'date' => $date],
        ['status' => 'IN_PROGRESS'],
    );

    return RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => test()->customer->id,
        'address_id' => test()->address->id,
        'is_rescheduled_from' => $rescheduledFrom?->id,
        'scheduled_time' => '16:00',
    ]);
}

test('the daily task flags a rescheduled stop whose day ended without a visit and nothing else', function (): void {
    $original = stopOn('2026-10-22');
    $unvisited = stopOn('2026-10-22', $original);
    $visited = stopOn('2026-10-22', $original);
    Visit::factory()->finished()->create(['route_stop_id' => $visited->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id]);
    $today = stopOn('2026-10-23', $original);
    $notRescheduled = stopOn('2026-10-22');

    $this->artisan('review:flag-rescheduled-stops')->assertSuccessful();

    expect($unvisited->fresh()->needs_review)->toBeTrue()
        ->and($unvisited->fresh()->review_reason)->toBeString()->not->toBeEmpty()
        ->and($visited->fresh()->needs_review)->toBeFalse()
        ->and($today->fresh()->needs_review)->toBeFalse()
        ->and($notRescheduled->fresh()->needs_review)->toBeFalse()
        ->and($original->fresh()->needs_review)->toBeFalse();
});

test('the daily task never flags a disabled rescheduled stop', function (): void {
    $original = stopOn('2026-10-21');
    $disabled = stopOn('2026-10-22', $original);
    $disabled->update(['enabled' => false]);

    $this->artisan('review:flag-rescheduled-stops')->assertSuccessful();

    expect($disabled->fresh()->needs_review)->toBeFalse();
});

test('the daily task does not flag the stops of a cancelled route', function (): void {
    $original = stopOn('2026-10-21');
    $stop = stopOn('2026-10-22', $original);
    $stop->route->update(['status' => 'CANCELLED']);

    $this->artisan('review:flag-rescheduled-stops')->assertSuccessful();

    expect($stop->fresh()->needs_review)->toBeFalse();
});

test('the daily task does not flag again a stop the admin already reviewed', function (): void {
    $stop = stopOn('2026-10-22', stopOn('2026-10-22'));
    $stop->forceFill(['needs_review' => true, 'review_reason' => 'x', 'reviewed_at' => '2026-10-23 00:10:00'])->save();

    $this->artisan('review:flag-rescheduled-stops')->assertSuccessful();

    expect($stop->fresh()->review_reason)->toBe('x');
});

test('the daily task is scheduled to run every day', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'review:flag-rescheduled-stops'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toMatch('/^\d+ \d+ \* \* \*$/');
});
