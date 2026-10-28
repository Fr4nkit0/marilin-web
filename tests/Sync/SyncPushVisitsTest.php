<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitOutcome;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
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
        'status' => 'IN_PROGRESS',
    ]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $this->address = CustomerAddress::factory()->create([
        'customer_id' => $this->customer->id,
        'is_primary' => true,
        'created_by' => $this->user->id,
    ]);
    $this->stop = RouteStop::factory()->create([
        'route_id' => $this->route->id,
        'customer_id' => $this->customer->id,
        'address_id' => $this->address->id,
        'scheduled_time' => '09:00',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rescheduledStopPayload(RouteStop $original, array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'rescheduled_from_id' => $original->id,
        'scheduled_time' => '16:00',
    ], $overrides);
}

test('push creates a rescheduled stop in the same route with the chosen time and marks the original as rescheduled', function (): void {
    $payload = rescheduledStopPayload($this->stop);

    $response = push(['route_stops' => [$payload]]);

    $new = RouteStop::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.route_stops.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.route_stops.0.status', 'accepted')
        ->assertJsonPath('data.route_stops.0.server_id', $new->id);

    expect($new->route_id)->toBe($this->route->id)
        ->and($new->customer_id)->toBe($this->customer->id)
        ->and($new->address_id)->toBe($this->address->id)
        ->and($new->is_rescheduled_from)->toBe($this->stop->id)
        ->and(substr((string) $new->scheduled_time, 0, 5))->toBe('16:00')
        ->and($this->stop->fresh()->effectiveStatus()->value)->toBe('RESCHEDULED');
});

test('push answers accepted with the same server id and creates nothing when a rescheduled stop is sent twice', function (): void {
    $payload = rescheduledStopPayload($this->stop);

    $first = push(['route_stops' => [$payload]]);
    $second = push(['route_stops' => [$payload]]);

    $second->assertOk()
        ->assertJsonPath('data.route_stops.0.status', 'accepted')
        ->assertJsonPath('data.route_stops.0.server_id', $first->json('data.route_stops.0.server_id'));

    expect(RouteStop::query()->where('client_uuid', $payload['uuid'])->count())->toBe(1)
        ->and(RouteStop::query()->count())->toBe(2);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function visitPayload(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'route_stop_id' => test()->stop->id,
        'customer_id' => test()->customer->id,
        'outcome_id' => VisitOutcome::factory()->create()->id,
        'arrived_at' => '2026-10-22T09:15:00Z',
        'latitude' => -24.7859,
        'longitude' => -65.4117,
        'altitude' => 1187.5,
        'notes' => 'Dejo agua en el patio',
    ], $overrides);
}

test('push accepts a visit of the stop with its outcome, arrival time, position and notes', function (): void {
    $payload = visitPayload();

    $response = push(['visits' => [$payload]]);
    $visit = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.visits.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $visit->id)
        ->assertJsonPath('data.visits.0.review_reason', null);

    expect($visit->driver_id)->toBe($this->driver->id)
        ->and($visit->route_stop_id)->toBe($this->stop->id)
        ->and($visit->customer_id)->toBe($this->customer->id)
        ->and($visit->outcome_id)->toBe($payload['outcome_id'])
        ->and($visit->arrived_at->toIso8601String())->toBe('2026-10-22T06:15:00-03:00')
        ->and((float) $visit->latitude)->toBe(-24.7859)
        ->and($visit->notes)->toBe('Dejo agua en el patio')
        ->and($visit->created_by)->toBe($this->user->id);
});

test('push processes entities in dependency order whatever the order of the request, so a visit can point at a stop rescheduled in the same push', function (): void {
    $stop = rescheduledStopPayload($this->stop);
    $visit = visitPayload(['route_stop_id' => null, 'route_stop_uuid' => $stop['uuid']]);

    $response = push([
        'visits' => [$visit],
        'route_stops' => [$stop],
    ]);

    $newStop = RouteStop::query()->where('client_uuid', $stop['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.route_stops.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.status', 'accepted');

    expect(Visit::query()->where('client_uuid', $visit['uuid'])->firstOrFail()->route_stop_id)->toBe($newStop->id);
});

test('push answers accepted with the same server id and creates nothing when a visit is sent twice', function (): void {
    $payload = visitPayload();

    $first = push(['visits' => [$payload]]);
    $second = push(['visits' => [$payload]]);

    $second->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $first->json('data.visits.0.server_id'));

    expect(Visit::query()->where('client_uuid', $payload['uuid'])->count())->toBe(1);
});

test('push accepts a visit on a customer of another driver or a disabled one and flags it for review', function (string $case): void {
    $customer = $case === 'disabled'
        ? Customer::factory()->disabled()->create(['driver_id' => $this->driver->id])
        : Customer::factory()->create(['driver_id' => Driver::factory()->create(['enabled' => true])->id]);
    $payload = visitPayload(['route_stop_id' => null, 'customer_id' => $customer->id]);

    $response = push(['visits' => [$payload]]);

    $response->assertOk()->assertJsonPath('data.visits.0.status', 'accepted');

    $visit = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    $reason = $response->json('data.visits.0.review_reason');

    expect($reason)->toBeString()->not->toBeEmpty()
        ->and($visit->needs_review)->toBeTrue()
        ->and($visit->review_reason)->toBe($reason);
})->with(['other driver' => ['other driver'], 'disabled' => ['disabled']]);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function movementPayload(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'container_type_id' => test()->containerType->id,
        'movement_type' => 'DELIVERED',
        'quantity' => 5,
    ], $overrides);
}

test('push moves the container balance of the customer up on deliveries and down on returns, once per movement', function (): void {
    $this->containerType = ContainerType::factory()->create();
    $delivered = movementPayload();
    $returned = movementPayload(['movement_type' => 'RETURNED', 'quantity' => 2]);

    $response = push(['container_movements' => [$delivered, $returned]]);

    $response->assertOk()
        ->assertJsonPath('data.container_movements.0.status', 'accepted')
        ->assertJsonPath('data.container_movements.1.status', 'accepted');

    $balance = fn (): int => CustomerContainerBalance::query()
        ->where('customer_id', $this->customer->id)
        ->where('container_type_id', $this->containerType->id)
        ->firstOrFail()->balance;

    expect($balance())->toBe(3)
        ->and(ContainerMovement::query()->where('client_uuid', $delivered['uuid'])->firstOrFail()->direction)->toBe(1)
        ->and(ContainerMovement::query()->where('client_uuid', $returned['uuid'])->firstOrFail()->direction)->toBe(-1);

    $replay = push(['container_movements' => [$delivered, $returned]]);

    $replay->assertOk()
        ->assertJsonPath('data.container_movements.0.server_id', $response->json('data.container_movements.0.server_id'));
    expect($balance())->toBe(3)
        ->and(ContainerMovement::query()->count())->toBe(2);
});

test('push rejects a visit that points at a stop that does not exist and still accepts the rest of the batch', function (): void {
    $broken = visitPayload(['route_stop_id' => 999999]);
    $valid = visitPayload();

    $response = push(['visits' => [$broken, $valid]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null)
        ->assertJsonPath('data.visits.1.status', 'accepted');

    expect($response->json('data.visits.0.error'))->toContain('route_stop')
        ->and(Visit::query()->where('client_uuid', $broken['uuid'])->exists())->toBeFalse();
});

test('push rejects a second reschedule of a stop that was already rescheduled', function (): void {
    $first = rescheduledStopPayload($this->stop);
    $second = rescheduledStopPayload($this->stop, ['scheduled_time' => '17:00']);

    push(['route_stops' => [$first]])->assertOk();
    $response = push(['route_stops' => [$second]]);

    $response->assertOk()
        ->assertJsonPath('data.route_stops.0.status', 'rejected')
        ->assertJsonPath('data.route_stops.0.server_id', null);

    expect(RouteStop::query()->where('client_uuid', $second['uuid'])->exists())->toBeFalse()
        ->and(RouteStop::query()->where('is_rescheduled_from', $this->stop->id)->count())->toBe(1);
});

test('push rejects a record the database refuses and still saves the rest of the batch', function (): void {
    $overflowing = visitPayload(['altitude' => 999999999999]);
    $valid = visitPayload();

    $response = push(['visits' => [$overflowing, $valid]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null)
        ->assertJsonPath('data.visits.1.status', 'accepted');

    expect($response->json('data.visits.0.error'))->toBeString()->not->toBeEmpty()
        ->and(Visit::query()->where('client_uuid', $overflowing['uuid'])->exists())->toBeFalse()
        ->and(Visit::query()->where('client_uuid', $valid['uuid'])->exists())->toBeTrue();
});

test('push accepts a container movement on a customer of another driver or a disabled one, flags it and still moves the balance', function (string $case): void {
    $this->containerType = ContainerType::factory()->create();
    $customer = $case === 'disabled'
        ? Customer::factory()->disabled()->create(['driver_id' => $this->driver->id])
        : Customer::factory()->create(['driver_id' => Driver::factory()->create(['enabled' => true])->id]);
    $payload = movementPayload(['customer_id' => $customer->id]);

    $response = push(['container_movements' => [$payload]]);

    $response->assertOk()->assertJsonPath('data.container_movements.0.status', 'accepted');

    $movement = ContainerMovement::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    $reason = $response->json('data.container_movements.0.review_reason');

    expect($reason)->toBeString()->not->toBeEmpty()
        ->and($movement->needs_review)->toBeTrue()
        ->and($movement->review_reason)->toBe($reason)
        ->and(CustomerContainerBalance::query()->where('customer_id', $customer->id)->firstOrFail()->balance)->toBe(5);
})->with(['other driver' => ['other driver'], 'disabled' => ['disabled']]);

test('push still accepts a numeric-string container type and quantity, as before the street operation', function (): void {
    $this->containerType = ContainerType::factory()->create();
    $payload = movementPayload(['container_type_id' => (string) $this->containerType->id, 'quantity' => '4']);

    push(['container_movements' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.container_movements.0.status', 'accepted');

    expect(CustomerContainerBalance::query()
        ->where('customer_id', $this->customer->id)
        ->where('container_type_id', $this->containerType->id)
        ->firstOrFail()->balance)->toBe(4);
});
