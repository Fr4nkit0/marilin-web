<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Visit;
use App\Review\ReviewReason;
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
 * A stop of the driver's route of the given day.
 */
function routeStopOn(string $date, string $status = 'IN_PROGRESS', array $attributes = []): RouteStop
{
    $route = Route::query()->firstOrCreate(
        ['assigned_driver_id' => test()->driver->id, 'date' => $date],
        ['status' => $status],
    );

    return RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => test()->customer->id,
        'address_id' => test()->address->id,
        'is_rescheduled_from' => null,
        ...$attributes,
    ]);
}

test('a stop of a past day without a visit goes to the review inbox', function (): void {
    $unvisited = routeStopOn('2026-10-22');
    $visited = routeStopOn('2026-10-22');
    Visit::factory()->finished()->create(['route_stop_id' => $visited->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id]);
    $today = routeStopOn('2026-10-23');

    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect($unvisited->fresh()->needs_review)->toBeTrue()
        ->and($unvisited->fresh()->review_reason)->toBe(ReviewReason::UnvisitedStop->value)
        ->and($visited->fresh()->needs_review)->toBeFalse()
        ->and($today->fresh()->needs_review)->toBeFalse();
});

test('it leaves out cancelled routes, disabled stops and rescheduled stops', function (): void {
    $cancelled = routeStopOn('2026-10-21', 'CANCELLED');
    $disabled = routeStopOn('2026-10-22', attributes: ['enabled' => false]);
    $original = routeStopOn('2026-10-22');
    $rescheduled = routeStopOn('2026-10-22', attributes: ['is_rescheduled_from' => $original->id]);

    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect($cancelled->fresh()->needs_review)->toBeFalse()
        ->and($disabled->fresh()->needs_review)->toBeFalse()
        ->and($rescheduled->fresh()->review_reason)->toBeNull()
        ->and($original->fresh()->needs_review)->toBeFalse();
});

test('it does not flag again a stop the admin already reviewed', function (): void {
    $stop = routeStopOn('2026-10-22');
    $stop->forceFill(['needs_review' => true, 'review_reason' => 'x', 'reviewed_at' => '2026-10-23 00:10:00'])->save();

    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect($stop->fresh()->review_reason)->toBe('x');
});

test('the admin reads why the stop is in the inbox', function (): void {
    expect(ReviewReason::UnvisitedStop->label())->toBe('La parada terminó el día sin visita.');
});

test('the unvisited stops task is scheduled to run every day', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'review:flag-unvisited-stops'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toMatch('/^\d+ \d+ \* \* \*$/');
});

test('a stop whose only visit was cancelled or left in progress is flagged, a done one is not', function (): void {
    $cancelled = routeStopOn('2026-10-22');
    Visit::factory()->create(['route_stop_id' => $cancelled->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'status' => 'CANCELLED']);
    $inProgress = routeStopOn('2026-10-22');
    Visit::factory()->create(['route_stop_id' => $inProgress->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'status' => 'IN_PROGRESS']);
    $done = routeStopOn('2026-10-22');
    Visit::factory()->create(['route_stop_id' => $done->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'status' => 'FINISHED']);

    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect($cancelled->fresh()->needs_review)->toBeTrue()
        ->and($cancelled->fresh()->review_reason)->toBe(ReviewReason::UnvisitedStop->value)
        ->and($inProgress->fresh()->needs_review)->toBeTrue()
        ->and($done->fresh()->needs_review)->toBeFalse();
});

test('a rescheduled stop with only a cancelled visit is flagged, one with a finished visit is not', function (): void {
    $original = routeStopOn('2026-10-21');
    $cancelled = routeStopOn('2026-10-22', attributes: ['is_rescheduled_from' => $original->id]);
    Visit::factory()->create(['route_stop_id' => $cancelled->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'status' => 'CANCELLED']);
    $done = routeStopOn('2026-10-22', attributes: ['is_rescheduled_from' => $original->id]);
    Visit::factory()->create(['route_stop_id' => $done->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'status' => 'FINISHED']);

    $this->artisan('review:flag-rescheduled-stops')->assertSuccessful();

    expect($cancelled->fresh()->needs_review)->toBeTrue()
        ->and($cancelled->fresh()->review_reason)->toBe(ReviewReason::UnvisitedRescheduledStop->value)
        ->and($done->fresh()->needs_review)->toBeFalse();
});
