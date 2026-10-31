<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->route = Route::factory()->create([
        'assigned_driver_id' => $this->driver->id,
        'date' => '2026-10-22',
        'status' => 'PLANNED',
    ]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_primary' => true]);
    $this->first = progressStop();
    $this->second = progressStop();
});

function progressStop(array $attributes = []): RouteStop
{
    return RouteStop::factory()->create([
        'route_id' => test()->route->id,
        'customer_id' => test()->customer->id,
        'address_id' => test()->address->id,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function progressVisit(?RouteStop $stop, string $arrivedAt, array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'route_stop_id' => $stop?->id,
        'customer_id' => test()->customer->id,
        'arrived_at' => $arrivedAt,
    ], $overrides);
}

test('S1: the first visit pushed on a stop starts the planned route at its arrival time', function (): void {
    push(['visits' => [progressVisit($this->first, '2026-10-22T12:15:00Z')]])->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted');

    $route = $this->route->fresh();

    expect($route->status)->toBe('IN_PROGRESS')
        ->and($route->started_at->toIso8601String())->toBe('2026-10-22T09:15:00-03:00')
        ->and($route->completed_at)->toBeNull();
});

test('S1: a route already in progress keeps its start time when more visits arrive', function (): void {
    $this->route->update(['status' => 'IN_PROGRESS', 'started_at' => '2026-10-22 08:00:00']);

    push(['visits' => [progressVisit($this->first, '2026-10-22T12:15:00Z')]])->assertOk();

    $route = $this->route->fresh();

    expect($route->status)->toBe('IN_PROGRESS')
        ->and($route->started_at->format('Y-m-d H:i:s'))->toBe('2026-10-22 08:00:00');
});

test('S2: the visit of the last stop completes the route, an absent outcome counting as done', function (): void {
    $absent = VisitOutcome::factory()->create(['name' => 'Ausente']);

    push(['visits' => [progressVisit($this->first, '2026-10-22T12:15:00Z')]])->assertOk();
    expect($this->route->fresh()->status)->toBe('IN_PROGRESS');

    push(['visits' => [progressVisit($this->second, '2026-10-22T13:40:00Z', ['outcome_id' => $absent->id])]])->assertOk();

    $route = $this->route->fresh();

    expect($route->status)->toBe('COMPLETED')
        ->and($route->started_at->toIso8601String())->toBe('2026-10-22T09:15:00-03:00')
        ->and($route->completed_at->toIso8601String())->toBe('2026-10-22T10:40:00-03:00');
});

test('S2: a disabled stop is not waited for to complete the route', function (): void {
    $this->second->update(['enabled' => false]);

    push(['visits' => [progressVisit($this->first, '2026-10-22T12:15:00Z')]])->assertOk();

    expect($this->route->fresh()->status)->toBe('COMPLETED');
});

test('S2: a stop rescheduled within the day is done when its rescheduled copy is done', function (): void {
    $copyUuid = (string) Str::uuid();

    push([
        'visits' => [
            progressVisit($this->first, '2026-10-22T12:15:00Z'),
            progressVisit($this->second, '2026-10-22T12:30:00Z'),
        ],
        'route_stops' => [['uuid' => $copyUuid, 'rescheduled_from_id' => $this->second->id, 'scheduled_time' => '16:00']],
    ])->assertOk();

    expect($this->route->fresh()->status)->toBe('IN_PROGRESS');

    push(['visits' => [progressVisit(null, '2026-10-22T19:05:00Z', ['route_stop_id' => null, 'route_stop_uuid' => $copyUuid])]])->assertOk();

    $route = $this->route->fresh();

    expect($route->status)->toBe('COMPLETED')
        ->and($route->completed_at->toIso8601String())->toBe('2026-10-22T16:05:00-03:00');
});

test('S3: a rescheduled stop pushed on a completed route reopens it', function (): void {
    push(['visits' => [
        progressVisit($this->first, '2026-10-22T12:15:00Z'),
        progressVisit($this->second, '2026-10-22T12:30:00Z'),
    ]])->assertOk();
    expect($this->route->fresh()->status)->toBe('COMPLETED');

    push(['route_stops' => [['uuid' => (string) Str::uuid(), 'rescheduled_from_id' => $this->second->id, 'scheduled_time' => '16:00']]])
        ->assertOk()
        ->assertJsonPath('data.route_stops.0.status', 'accepted');

    $route = $this->route->fresh();

    expect($route->status)->toBe('IN_PROGRESS')
        ->and($route->started_at->toIso8601String())->toBe('2026-10-22T09:15:00-03:00')
        ->and($route->completed_at)->toBeNull();
});

test('S4: a cancelled route is never moved by a push', function (): void {
    $this->route->update(['status' => 'CANCELLED']);

    push(['visits' => [
        progressVisit($this->first, '2026-10-22T12:15:00Z'),
        progressVisit($this->second, '2026-10-22T12:30:00Z'),
    ]])->assertOk();

    $route = $this->route->fresh();

    expect($route->status)->toBe('CANCELLED')
        ->and($route->started_at)->toBeNull()
        ->and($route->completed_at)->toBeNull();
});

test('S4: an edited visit push recalculates its route', function (): void {
    $visit = Visit::factory()->create([
        'client_uuid' => (string) Str::uuid(),
        'route_stop_id' => $this->first->id,
        'customer_id' => $this->customer->id,
        'driver_id' => $this->driver->id,
        'status' => 'FINISHED',
        'arrived_at' => '2026-10-22 09:15:00',
        'app_modified_at' => '2026-10-22 09:15:00',
    ]);
    expect($this->route->fresh()->status)->toBe('PLANNED');

    push(['visits' => [progressVisit($this->first, '2026-10-22T12:15:00Z', [
        'uuid' => $visit->client_uuid,
        'notes' => 'No estaba',
        'modified_at' => '2026-10-22T14:30:00Z',
    ])]])->assertOk()->assertJsonPath('data.visits.0.server_id', $visit->id);

    $route = $this->route->fresh();

    expect($visit->fresh()->notes)->toBe('No estaba')
        ->and($route->status)->toBe('IN_PROGRESS')
        ->and($route->started_at->format('Y-m-d H:i:s'))->toBe('2026-10-22 09:15:00');
});

test('a loose visit without a stop changes no route', function (): void {
    push(['visits' => [progressVisit(null, '2026-10-22T12:15:00Z')]])->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted');

    $route = $this->route->fresh();

    expect($route->status)->toBe('PLANNED')
        ->and($route->started_at)->toBeNull()
        ->and($route->completed_at)->toBeNull();
});

test("S3: a late push for a past day's closed route does not reopen it", function (): void {
    $this->route->update(['date' => '2026-10-21', 'status' => 'COMPLETED', 'started_at' => null, 'completed_at' => null]);

    push(['visits' => [progressVisit($this->first, '2026-10-21T12:15:00Z')]])->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted');

    $route = $this->route->fresh();

    expect($route->status)->toBe('COMPLETED')
        ->and($route->completed_at)->toBeNull();
});
