<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Review\ReviewReason;
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
        ->assertJsonPath('data.visits.0.review_reasons', []);

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
    $reason = $response->json('data.visits.0.review_reasons.0');

    expect($reason)->toBeString()->not->toBeEmpty()
        ->and($visit->needs_review)->toBeTrue()
        ->and(ReviewReason::labels($visit->review_reasons))->toBe([$reason]);
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

    expect($response->json('data.visits.0.error'))->toBe('La parada referida no existe.')
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
    $reason = $response->json('data.container_movements.0.review_reasons.0');

    expect($reason)->toBeString()->not->toBeEmpty()
        ->and($movement->needs_review)->toBeTrue()
        ->and(ReviewReason::labels($movement->review_reasons))->toBe([$reason])
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

/**
 * The visit as the server holds it, to prove a push left it untouched.
 *
 * @return array<string, mixed>
 */
function visitSnapshot(string $uuid): array
{
    return Visit::query()->where('client_uuid', $uuid)->firstOrFail()
        ->only(['outcome_id', 'notes', 'arrived_at', 'latitude', 'longitude', 'altitude', 'route_stop_id', 'customer_id', 'driver_id', 'app_modified_at', 'last_modified_by', 'updated_at']);
}

test('push applies a newer visit edit to its outcome and notes only', function (): void {
    $payload = visitPayload();
    $created = push(['visits' => [$payload]]);
    $before = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    $outcome = VisitOutcome::factory()->create();

    $response = push(['visits' => [array_replace($payload, [
        'modified_at' => '2026-10-22T14:30:00Z',
        'outcome_id' => $outcome->id,
        'notes' => 'No estaba, volver a la tarde',
        'arrived_at' => '2026-10-22T10:00:00Z',
        'latitude' => -24.1,
        'longitude' => -65.1,
        'altitude' => 1.5,
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $created->json('data.visits.0.server_id'))
        ->assertJsonPath('data.visits.0.review_reasons', []);

    $visit = $before->fresh();

    expect($visit->outcome_id)->toBe($outcome->id)
        ->and($visit->notes)->toBe('No estaba, volver a la tarde')
        ->and($visit->app_modified_at->toIso8601String())->toBe('2026-10-22T11:30:00-03:00')
        ->and($visit->last_modified_by)->toBe($this->user->id)
        ->and($visit->arrived_at->toIso8601String())->toBe($before->arrived_at->toIso8601String())
        ->and($visit->latitude)->toBe($before->latitude)
        ->and($visit->longitude)->toBe($before->longitude)
        ->and($visit->altitude)->toBe($before->altitude)
        ->and($visit->route_stop_id)->toBe($before->route_stop_id)
        ->and($visit->customer_id)->toBe($before->customer_id)
        ->and(Visit::query()->count())->toBe(1);
});

test('push clears the outcome and notes of a visit when a newer edit sends them empty', function (): void {
    $payload = visitPayload();
    push(['visits' => [$payload]]);

    push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-22T14:30:00Z', 'outcome_id' => null, 'notes' => null])]])
        ->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted');

    $visit = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    expect($visit->outcome_id)->toBeNull()
        ->and($visit->notes)->toBeNull();
});

test('push stores the app modification date of a visit created with modified_at, clamped to the server clock', function (): void {
    $payload = visitPayload(['modified_at' => '2026-10-23T09:00:00Z']);

    push(['visits' => [$payload]])->assertOk()->assertJsonPath('data.visits.0.status', 'accepted');

    expect(Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail()->app_modified_at->toIso8601String())
        ->toBe('2026-10-22T12:00:00-03:00');
});

test('push ignores a visit edit older than the last one applied but still accepts it', function (): void {
    $payload = visitPayload(['modified_at' => '2026-10-22T14:30:00Z']);
    $created = push(['visits' => [$payload]]);
    $before = visitSnapshot($payload['uuid']);

    $response = push(['visits' => [array_replace($payload, [
        'modified_at' => '2026-10-22T14:00:00Z',
        'outcome_id' => VisitOutcome::factory()->create()->id,
        'notes' => 'Edicion vieja',
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $created->json('data.visits.0.server_id'));

    expect(visitSnapshot($payload['uuid']))->toEqual($before);
});

test('push rejects an edit made on a later day than the visit and leaves it unchanged', function (): void {
    $payload = visitPayload(['arrived_at' => '2026-10-21T15:00:00Z']);
    push(['visits' => [$payload]])->assertOk()->assertJsonPath('data.visits.0.status', 'accepted');
    $before = visitSnapshot($payload['uuid']);

    $response = push(['visits' => [array_replace($payload, [
        'modified_at' => '2026-10-22T14:30:00Z',
        'outcome_id' => VisitOutcome::factory()->create()->id,
        'notes' => 'Edicion tardia',
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null)
        ->assertJsonPath('data.visits.0.error', 'La edición no se hizo el día de la visita.');

    expect(visitSnapshot($payload['uuid']))->toEqual($before);
});

test('push applies an offline visit edit made on the visit day and synced after midnight', function (): void {
    $payload = visitPayload();
    $created = push(['visits' => [$payload]]);
    $outcome = VisitOutcome::factory()->create();

    // Edited on 2026-10-22 at 23:50 local, synced at 00:00:13 of the next day.
    $this->travelTo('2026-10-23 00:00:13');
    push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-23T02:50:00Z', 'outcome_id' => $outcome->id, 'notes' => 'Editada de noche'])]])
        ->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $created->json('data.visits.0.server_id'));

    $visit = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    expect($visit->outcome_id)->toBe($outcome->id)
        ->and($visit->notes)->toBe('Editada de noche')
        ->and($visit->app_modified_at->toIso8601String())->toBe('2026-10-22T23:50:00-03:00');
});

test('push rejects a visit edit made the day after the visit, synced that day, and leaves it unchanged', function (): void {
    $payload = visitPayload();
    push(['visits' => [$payload]]);
    $before = visitSnapshot($payload['uuid']);

    $this->travelTo('2026-10-23 09:00:00');
    push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-23T11:00:00Z', 'notes' => 'Al dia siguiente'])]])
        ->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null)
        ->assertJsonPath('data.visits.0.error', 'La edición no se hizo el día de la visita.');

    expect(visitSnapshot($payload['uuid']))->toEqual($before);
});

test('push rejects a visit edit more than 3 days old and leaves it unchanged', function (): void {
    $payload = visitPayload();
    push(['visits' => [$payload]]);
    $before = visitSnapshot($payload['uuid']);

    // Edited on the visit's day at 11:30 local, synced 3 days and 1 second later.
    $this->travelTo('2026-10-25 11:30:01');
    push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-22T14:30:00Z', 'notes' => 'Muy vieja'])]])
        ->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null)
        ->assertJsonPath('data.visits.0.error', 'La edición tiene más de 3 días.');

    expect(visitSnapshot($payload['uuid']))->toEqual($before);
});

test('push applies a visit edit exactly 3 days old', function (): void {
    $payload = visitPayload();
    push(['visits' => [$payload]]);

    $this->travelTo('2026-10-25 11:30:00');
    push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-22T14:30:00Z', 'notes' => 'Justo a tiempo'])]])
        ->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted');

    expect(Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail()->notes)->toBe('Justo a tiempo');
});

test('push clamps a visit edit dated in the future to the server clock and applies it', function (): void {
    $payload = visitPayload();
    push(['visits' => [$payload]]);

    push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-23T15:00:00Z', 'notes' => 'Reloj adelantado'])]])
        ->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted');

    $visit = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    expect($visit->notes)->toBe('Reloj adelantado')
        ->and($visit->app_modified_at->toIso8601String())->toBe('2026-10-22T12:00:00-03:00');
});

test('push rejects an edit of a visit of another driver and leaves it unchanged', function (): void {
    $payload = visitPayload();
    Visit::factory()->create([
        'client_uuid' => $payload['uuid'],
        'customer_id' => $this->customer->id,
        'driver_id' => Driver::factory()->create(['enabled' => true])->id,
        'arrived_at' => '2026-10-22 09:00:00',
        'notes' => 'Original',
    ]);
    $before = visitSnapshot($payload['uuid']);

    $response = push(['visits' => [array_replace($payload, ['modified_at' => '2026-10-22T14:30:00Z'])]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null)
        ->assertJsonPath('data.visits.0.error', 'La visita pertenece a otro repartidor.');

    expect(visitSnapshot($payload['uuid']))->toEqual($before);
});

test('push still treats a re-push of a known visit without modified_at as a replay that changes nothing', function (): void {
    $payload = visitPayload();
    $created = push(['visits' => [$payload]]);
    $before = visitSnapshot($payload['uuid']);

    $response = push(['visits' => [array_replace($payload, [
        'outcome_id' => VisitOutcome::factory()->create()->id,
        'notes' => 'Cambio sin fecha',
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $created->json('data.visits.0.server_id'));

    expect(visitSnapshot($payload['uuid']))->toEqual($before)
        ->and(Visit::query()->count())->toBe(1);
});

test('push rejects a visit edit with an invalid modified_at and leaves the visit unchanged', function (): void {
    $payload = visitPayload();
    push(['visits' => [$payload]]);
    $before = visitSnapshot($payload['uuid']);

    $response = push(['visits' => [array_replace($payload, ['modified_at' => 'not-a-date', 'notes' => 'Invalida'])]]);

    $response->assertOk()
        ->assertJsonPath('data.visits.0.status', 'rejected')
        ->assertJsonPath('data.visits.0.server_id', null);

    expect($response->json('data.visits.0.error'))->toBe('El campo fecha de modificación debe ser una fecha válida.')
        ->and(visitSnapshot($payload['uuid']))->toEqual($before);
});

test('push answers a creation replayed the next day with its modification date as accepted and changes nothing', function (): void {
    $payload = visitPayload(['modified_at' => '2026-10-22T09:20:00Z']);
    push(['visits' => [$payload]])->assertOk()->assertJsonPath('data.visits.0.status', 'accepted');
    $visit = Visit::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    $this->travelTo('2026-10-23 08:00:00');

    push(['visits' => [$payload]])->assertOk()
        ->assertJsonPath('data.visits.0.status', 'accepted')
        ->assertJsonPath('data.visits.0.server_id', $visit->id);

    expect(Visit::query()->count())->toBe(1)
        ->and($visit->fresh()->notes)->toBe($payload['notes']);
});
