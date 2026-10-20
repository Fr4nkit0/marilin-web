<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'uuid' => (string) Str::uuid(),
        'name' => 'Almacen Don Pepe',
        'phone_number' => '3874001122',
        'modified_at' => '2026-10-20T09:00:00-03:00',
        'address' => [
            'street' => 'Av Belgrano 100',
            'neighborhood' => 'Centro',
            'latitude' => -24.785,
            'longitude' => -65.411,
        ],
    ], $overrides);
}

test('push accepts a new customer, assigns a server id and stores it with its primary address', function (): void {
    $customer = customerPayload();

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [$customer]]);

    $stored = Customer::query()->where('client_uuid', $customer['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.customers.0.uuid', $customer['uuid'])
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $stored->id)
        ->assertJsonPath('data.customers.0.review_reason', null);

    expect($stored->driver_id)->toBe($this->driver->id)
        ->and($stored->name)->toBe('Almacen Don Pepe');

    $address = CustomerAddress::query()->where('customer_id', $stored->id)->firstOrFail();
    expect($address->is_primary)->toBeTrue()
        ->and($address->street)->toBe('Av Belgrano 100')
        ->and($address->neighborhood)->toBe('Centro');
});

test('push answers accepted with the same server id and creates nothing when a new customer is sent twice', function (): void {
    $payload = ['customers' => [customerPayload()]];

    $first = $this->postJson('/api/v1/sync/push', $payload);
    $second = $this->postJson('/api/v1/sync/push', $payload);

    $second->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $first->json('data.customers.0.server_id'));

    expect(Customer::query()->where('client_uuid', $payload['customers'][0]['uuid'])->count())->toBe(1)
        ->and(CustomerAddress::query()->count())->toBe(1);
});

test('push rejects an invalid customer with a readable reason and still accepts the rest of the batch', function (): void {
    $invalid = customerPayload(['address' => ['street' => '']]);
    $valid = customerPayload(['name' => 'Kiosco Norte']);

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [$invalid, $valid]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.uuid', $invalid['uuid'])
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.server_id', null)
        ->assertJsonPath('data.customers.1.uuid', $valid['uuid'])
        ->assertJsonPath('data.customers.1.status', 'accepted');

    expect($response->json('data.customers.0.error'))->toContain('street')
        ->and(Customer::query()->where('client_uuid', $invalid['uuid'])->exists())->toBeFalse()
        ->and(Customer::query()->where('client_uuid', $valid['uuid'])->exists())->toBeTrue();
});

/**
 * @return array{0: Customer, 1: CustomerAddress}
 */
function serverCustomer(Driver $driver, User $user, ?string $uuid): array
{
    $customer = Customer::factory()->create([
        'driver_id' => $driver->id,
        'created_by' => $user->id,
        'client_uuid' => $uuid,
        'name' => 'Nombre del servidor',
        'updated_at' => '2026-10-20 10:00:00',
    ]);

    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'street' => 'Calle del servidor',
        'is_primary' => true,
        'created_by' => $user->id,
        'updated_at' => '2026-10-20 10:00:00',
    ]);

    return [$customer, $address];
}

test('push does not overwrite a customer when the edit is older than the server version', function (): void {
    $uuid = (string) Str::uuid();
    [$customer, $address] = serverCustomer($this->driver, $this->user, $uuid);

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [customerPayload([
        'uuid' => $uuid,
        'name' => 'Nombre viejo de la app',
        'modified_at' => '2026-10-20T09:00:00Z',
        'address' => ['street' => 'Calle vieja de la app'],
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $customer->id);

    expect($customer->fresh()->name)->toBe('Nombre del servidor')
        ->and($address->fresh()->street)->toBe('Calle del servidor');
});

test('push overwrites a customer found by server id when the edit is newer and stores the app modification date', function (): void {
    [$customer, $address] = serverCustomer($this->driver, $this->user, null);
    $uuid = (string) Str::uuid();

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [customerPayload([
        'uuid' => $uuid,
        'server_id' => $customer->id,
        'name' => 'Nombre nuevo de la app',
        'modified_at' => '2026-10-20T11:00:00Z',
        'address' => ['street' => 'Calle nueva de la app'],
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $customer->id);

    $customer->refresh();
    expect($customer->name)->toBe('Nombre nuevo de la app')
        ->and($customer->client_uuid)->toBe($uuid)
        ->and($customer->last_modified_by)->toBe($this->user->id)
        ->and($customer->updated_at->toIso8601String())->toBe('2026-10-20T11:00:00+00:00')
        ->and($address->fresh()->street)->toBe('Calle nueva de la app');
});

test('push accepts an edit on a customer assigned to another driver and flags it for review', function (): void {
    $otherDriver = Driver::factory()->create(['enabled' => true]);
    [$customer] = serverCustomer($otherDriver, $this->user, null);

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [customerPayload([
        'server_id' => $customer->id,
        'name' => 'Editado en la calle',
        'modified_at' => '2026-10-20T11:00:00Z',
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $customer->id);

    $reason = $response->json('data.customers.0.review_reason');
    $customer->refresh();

    expect($reason)->toBeString()->not->toBeEmpty()
        ->and($customer->needs_review)->toBeTrue()
        ->and($customer->review_reason)->toBe($reason)
        ->and($customer->name)->toBe('Editado en la calle')
        ->and($customer->driver_id)->toBe($otherDriver->id);
});

test('push stores the dates sent by the app and records separately when the server received the customer', function (): void {
    $this->travelTo('2026-10-20 15:00:00');
    $customer = customerPayload(['modified_at' => '2026-10-18T08:30:00Z']);

    $this->postJson('/api/v1/sync/push', ['customers' => [$customer]])->assertOk();

    $stored = Customer::query()->where('client_uuid', $customer['uuid'])->firstOrFail();

    expect($stored->created_at->toIso8601String())->toBe('2026-10-18T08:30:00+00:00')
        ->and($stored->updated_at->toIso8601String())->toBe('2026-10-18T08:30:00+00:00')
        ->and($stored->received_at->toIso8601String())->toBe('2026-10-20T15:00:00+00:00');
});

test('every entity the app can create has a unique client uuid and the generic idempotency table is gone', function (): void {
    foreach (['customers', 'route_stops', 'visits', 'container_movements', 'sales', 'payments'] as $table) {
        $unique = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['client_uuid']);

        expect($unique)->toBeTrue("{$table} lacks a unique client_uuid");
    }

    expect(Schema::hasTable('idempotency_records'))->toBeFalse();
});

test('the draft nested sync endpoints no longer exist', function (): void {
    $this->postJson('/api/v1/sync/customers', ['customers' => []])->assertNotFound();
    $this->postJson('/api/v1/sync/route-visits', ['visits' => []])->assertNotFound();
});

test('push flags a customer of another driver for review even when the edit is older than the server version', function (): void {
    $otherDriver = Driver::factory()->create(['enabled' => true]);
    [$customer] = serverCustomer($otherDriver, $this->user, null);

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [customerPayload([
        'server_id' => $customer->id,
        'name' => 'Edicion vieja',
        'modified_at' => '2026-10-20T09:00:00Z',
    ])]]);

    $response->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    expect($response->json('data.customers.0.review_reason'))->toBeString()->not->toBeEmpty()
        ->and($customer->fresh()->needs_review)->toBeTrue()
        ->and($customer->fresh()->name)->toBe('Nombre del servidor');
});

test('push rejects an edit that points at a server id that does not exist', function (): void {
    $payload = customerPayload(['server_id' => 999999]);

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [$payload]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.server_id', null);

    expect($response->json('data.customers.0.error'))->toContain('server_id')
        ->and(Customer::query()->where('client_uuid', $payload['uuid'])->exists())->toBeFalse();
});

test('push keeps the full address when creating and editing a customer', function (): void {
    $uuid = (string) Str::uuid();
    $detail = ['apartment' => '4B', 'floor' => '4', 'visual_reference' => 'Porton verde'];

    $this->postJson('/api/v1/sync/push', ['customers' => [customerPayload(['uuid' => $uuid, 'address' => $detail])]])->assertOk();

    $customer = Customer::query()->where('client_uuid', $uuid)->firstOrFail();
    expect($customer->primaryAddress->only(array_keys($detail)))->toBe($detail);

    $edited = ['apartment' => '5C', 'floor' => 'PB', 'visual_reference' => 'Porton rojo'];
    $this->postJson('/api/v1/sync/push', ['customers' => [customerPayload([
        'uuid' => $uuid,
        'modified_at' => '2026-10-21T09:00:00Z',
        'address' => $edited,
    ])]])->assertOk();

    expect($customer->fresh()->primaryAddress->only(array_keys($edited)))->toBe($edited);
});

test('push stores a text floor and a numeric floor exactly as written', function (string $floor): void {
    $payload = customerPayload(['address' => ['floor' => $floor]]);

    $this->postJson('/api/v1/sync/push', ['customers' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = Customer::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    expect($customer->primaryAddress->floor)->toBe($floor);
})->with(['PB', '3', 'Local 2']);

test('push stores a floor sent as a number as its text form', function (): void {
    $payload = customerPayload(['address' => ['floor' => 3]]);

    $this->postJson('/api/v1/sync/push', ['customers' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = Customer::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    expect($customer->primaryAddress->floor)->toBe('3');
});

test('push rejects a floor longer than 20 characters', function (): void {
    $payload = customerPayload(['address' => ['floor' => str_repeat('A', 21)]]);

    $response = $this->postJson('/api/v1/sync/push', ['customers' => [$payload]]);

    $response->assertOk()->assertJsonPath('data.customers.0.status', 'rejected');
    expect($response->json('data.customers.0.error'))->toContain('floor')
        ->and(Customer::query()->where('client_uuid', $payload['uuid'])->exists())->toBeFalse();
});

test('push clamps a modification date ahead of the server clock so later server edits are not shadowed', function (): void {
    $this->travelTo('2026-10-20 15:00:00');
    $customer = customerPayload(['modified_at' => '2026-10-25T08:00:00Z']);

    $this->postJson('/api/v1/sync/push', ['customers' => [$customer]])->assertOk();

    $stored = Customer::query()->where('client_uuid', $customer['uuid'])->firstOrFail();

    expect($stored->updated_at->toIso8601String())->toBe('2026-10-20T15:00:00+00:00')
        ->and($stored->created_at->toIso8601String())->toBe('2026-10-20T15:00:00+00:00');
});
