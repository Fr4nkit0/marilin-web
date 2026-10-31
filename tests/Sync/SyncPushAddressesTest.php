<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

require_once __DIR__.'/PushHelpers.php';

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_at' => '2026-10-20 10:00:00', 'updated_at' => '2026-10-20 10:00:00']);
    $this->primary = serverAddress($this->customer, null, true);
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addressPayload(Customer $customer, array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'street' => 'Caseros 1200',
        'apartment' => '2B',
        'floor' => '2',
        'visual_reference' => 'Porton azul',
        'neighborhood' => 'Tres Cerritos',
        'latitude' => -24.78,
        'longitude' => -65.41,
        'is_primary' => false,
        'enabled' => true,
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ], $overrides);
}

function serverAddress(Customer $customer, ?string $uuid = null, bool $primary = false): CustomerAddress
{
    return CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'client_uuid' => $uuid,
        'street' => 'Calle del servidor',
        'is_primary' => $primary,
        'created_at' => '2026-10-20 10:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
}

/**
 * @return array<string, mixed>
 */
function addressState(CustomerAddress $address): array
{
    return $address->fresh()->only(['customer_id', 'street', 'apartment', 'floor', 'is_primary', 'enabled', 'client_uuid', 'updated_at']);
}

test('push creates a secondary address with the app modification date and the moment it was received', function (): void {
    $payload = addressPayload($this->customer);

    $response = push(['customer_addresses' => [$payload]]);

    $stored = CustomerAddress::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.customer_addresses.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $stored->id)
        ->assertJsonPath('data.customer_addresses.0.review_reasons', []);

    expect($stored->only(['customer_id', 'street', 'apartment', 'floor', 'visual_reference', 'neighborhood', 'latitude', 'longitude', 'is_primary', 'enabled']))->toBe([
        'customer_id' => $this->customer->id,
        'street' => 'Caseros 1200',
        'apartment' => '2B',
        'floor' => '2',
        'visual_reference' => 'Porton azul',
        'neighborhood' => 'Tres Cerritos',
        'latitude' => '-24.7800000',
        'longitude' => '-65.4100000',
        'is_primary' => false,
        'enabled' => true,
    ])
        ->and($stored->created_by)->toBe($this->user->id)
        ->and($stored->updated_at->toIso8601String())->toBe('2026-10-21T09:00:00-03:00')
        ->and($stored->received_at->toIso8601String())->toBe('2026-10-22T12:00:00-03:00')
        ->and($this->primary->fresh()->is_primary)->toBeTrue();
});

test('push stores a floor sent as a number as its text form', function (): void {
    $payload = addressPayload($this->customer, ['floor' => 3]);

    push(['customer_addresses' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted');

    expect(CustomerAddress::query()->where('client_uuid', $payload['uuid'])->value('floor'))->toBe('3');
});

test('push makes a new primary address and unsets the customer previous primary', function (): void {
    $payload = addressPayload($this->customer, ['is_primary' => true]);

    push(['customer_addresses' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted');

    $previous = $this->primary->fresh();
    expect(CustomerAddress::query()->where('client_uuid', $payload['uuid'])->value('is_primary'))->toBeTrue()
        ->and($previous->is_primary)->toBeFalse()
        ->and($previous->received_at->toIso8601String())->toBe('2026-10-22T12:00:00-03:00')
        ->and($this->customer->fresh()->primaryAddress->client_uuid)->toBe($payload['uuid']);
});

test('push applies a newer edit found by uuid', function (): void {
    $uuid = (string) Str::uuid();
    $address = serverAddress($this->customer, $uuid);

    push(['customer_addresses' => [addressPayload($this->customer, ['uuid' => $uuid, 'street' => 'Caseros 1300'])]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $address->id);

    $address->refresh();
    expect($address->street)->toBe('Caseros 1300')
        ->and($address->last_modified_by)->toBe($this->user->id)
        ->and($address->updated_at->toIso8601String())->toBe('2026-10-21T09:00:00-03:00')
        ->and($address->received_at->toIso8601String())->toBe('2026-10-22T12:00:00-03:00')
        ->and(CustomerAddress::query()->count())->toBe(2);
});

test('push applies a newer edit found by server id and adopts the app uuid', function (): void {
    $uuid = (string) Str::uuid();

    push(['customer_addresses' => [addressPayload($this->customer, [
        'uuid' => $uuid,
        'server_id' => $this->primary->id,
        'street' => 'Belgrano 50',
        'is_primary' => true,
    ])]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $this->primary->id);

    $primary = $this->primary->fresh();
    expect($primary->street)->toBe('Belgrano 50')
        ->and($primary->client_uuid)->toBe($uuid)
        ->and($primary->is_primary)->toBeTrue()
        ->and(CustomerAddress::query()->count())->toBe(1);
});

test('push ignores an edit older than the server version and answers outdated with its server id', function (): void {
    $uuid = (string) Str::uuid();
    $address = serverAddress($this->customer, $uuid);
    $before = addressState($address);

    push(['customer_addresses' => [addressPayload($this->customer, [
        'uuid' => $uuid,
        'street' => 'Vieja',
        'is_primary' => true,
        'modified_at' => '2026-10-20T09:00:00-03:00',
    ])]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'outdated')
        ->assertJsonPath('data.customer_addresses.0.server_id', $address->id);

    expect(addressState($address))->toEqual($before)
        ->and($this->primary->fresh()->is_primary)->toBeTrue();
});

test('push answers a replayed address with the same server id and creates nothing', function (): void {
    $payload = ['customer_addresses' => [addressPayload($this->customer)]];

    $first = push($payload);
    $second = push($payload);

    $second->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $first->json('data.customer_addresses.0.server_id'));

    expect(CustomerAddress::query()->count())->toBe(2);
});

test('push rejects an address that points at a server id that does not exist', function (): void {
    push(['customer_addresses' => [addressPayload($this->customer, ['server_id' => 999999])]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_addresses.0.server_id', null)
        ->assertJsonPath('data.customer_addresses.0.error', 'The server_id does not match any customer address.');

    expect(CustomerAddress::query()->count())->toBe(1);
});

test('push rejects moving an address to another customer', function (): void {
    $other = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $uuid = (string) Str::uuid();
    $address = serverAddress($this->customer, $uuid);
    $before = addressState($address);

    push(['customer_addresses' => [addressPayload($other, ['uuid' => $uuid])]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_addresses.0.error', 'The record belongs to another customer.');

    expect(addressState($address))->toEqual($before);
});

test('push rejects an invalid address with a readable reason and still accepts the rest of the batch', function (): void {
    $invalid = addressPayload($this->customer, ['street' => '']);
    $valid = addressPayload($this->customer);

    $response = push(['customer_addresses' => [$invalid, $valid]]);

    $response->assertOk()
        ->assertJsonPath('data.customer_addresses.0.uuid', $invalid['uuid'])
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_addresses.1.status', 'accepted');

    expect($response->json('data.customer_addresses.0.error'))->toBe('El campo calle es obligatorio.')
        ->and(CustomerAddress::query()->where('client_uuid', $invalid['uuid'])->exists())->toBeFalse()
        ->and(CustomerAddress::query()->where('client_uuid', $valid['uuid'])->exists())->toBeTrue();
});

test('push disables a secondary address when the app sends enabled false', function (): void {
    $uuid = (string) Str::uuid();
    $address = serverAddress($this->customer, $uuid);

    push(['customer_addresses' => [addressPayload($this->customer, ['uuid' => $uuid, 'enabled' => false])]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted');

    expect($address->fresh()->enabled)->toBeFalse();
});

test('push rejects disabling the primary address', function (bool $isPrimary): void {
    $before = addressState($this->primary);

    push(['customer_addresses' => [addressPayload($this->customer, [
        'server_id' => $this->primary->id,
        'is_primary' => $isPrimary,
        'enabled' => false,
    ])]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_addresses.0.error', 'The primary address cannot be disabled.');

    expect(addressState($this->primary))->toEqual($before);
})->with([true, false]);

test('push rejects a new disabled primary address', function (): void {
    push(['customer_addresses' => [addressPayload($this->customer, ['is_primary' => true, 'enabled' => false])]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.error', 'The primary address cannot be disabled.');

    expect(CustomerAddress::query()->count())->toBe(1)
        ->and($this->primary->fresh()->is_primary)->toBeTrue();
});

test('push rejects leaving the customer without a primary address', function (): void {
    $before = addressState($this->primary);

    push(['customer_addresses' => [addressPayload($this->customer, ['server_id' => $this->primary->id, 'is_primary' => false])]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_addresses.0.error', 'A customer needs a primary address.');

    expect(addressState($this->primary))->toEqual($before);
});

test('push applies an address on a customer of another driver and flags the customer for review', function (): void {
    $otherDriver = Driver::factory()->create(['enabled' => true]);
    $foreign = Customer::factory()->create(['driver_id' => $otherDriver->id]);
    serverAddress($foreign, null, true);
    $payload = addressPayload($foreign);

    push(['customer_addresses' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.review_reasons', ['Customer is assigned to another driver.']);

    $foreign->refresh();
    expect(CustomerAddress::query()->where('client_uuid', $payload['uuid'])->value('customer_id'))->toBe($foreign->id)
        ->and($foreign->needs_review)->toBeTrue()
        ->and($foreign->review_reasons)->toBe(['Customer is assigned to another driver.']);
});

test('the customer push keeps editing the primary address after the app moved it', function (): void {
    $customerUuid = (string) Str::uuid();
    $this->customer->timestamps = false;
    $this->customer->forceFill(['client_uuid' => $customerUuid])->save();
    $payload = addressPayload($this->customer, ['is_primary' => true]);

    push(['customer_addresses' => [$payload]])->assertOk();
    push(['customers' => [[
        'uuid' => $customerUuid,
        'name' => 'Editado',
        'modified_at' => '2026-10-22T10:00:00-03:00',
        'address' => ['street' => 'Mitre 900'],
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted');

    expect(CustomerAddress::query()->where('client_uuid', $payload['uuid'])->value('street'))->toBe('Mitre 900')
        ->and($this->primary->fresh()->street)->toBe('Calle del servidor')
        ->and(CustomerAddress::query()->count())->toBe(2);
});

test('pull returns a pushed address by the moment it was received even when its app date is older than the cursor', function (): void {
    $payload = addressPayload($this->customer, ['modified_at' => '2026-10-20T09:00:00-03:00']);
    $id = push(['customer_addresses' => [$payload]])->json('data.customer_addresses.0.server_id');

    $this->travel(1)->minutes();
    $cursor = now()->subHour()->toIso8601String();
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();

    $rows = collect($response->json('data.customer_addresses'));
    expect($rows->pluck('id')->all())->toBe([$id])
        ->and($rows->first()['client_uuid'])->toBe($payload['uuid']);
});

/**
 * @param  array<string, mixed>  $address
 * @return array<string, mixed>
 */
function customerWithAddressPayload(array $address, array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'name' => 'Kiosco Nuevo',
        'modified_at' => '2026-10-21T08:00:00-03:00',
        'address' => ['street' => 'Caseros 500', ...$address],
    ], $overrides);
}

test('a customer pushed with an address uuid lets the address push edit its primary address instead of adding one', function (): void {
    $addressUuid = (string) Str::uuid();
    $customerPayload = customerWithAddressPayload(['uuid' => $addressUuid]);

    push(['customers' => [$customerPayload]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');
    $customer = Customer::query()->where('client_uuid', $customerPayload['uuid'])->firstOrFail();
    $primary = $customer->primaryAddress;
    expect($primary->client_uuid)->toBe($addressUuid);

    push(['customer_addresses' => [addressPayload($customer, [
        'uuid' => $addressUuid,
        'customer_id' => null,
        'customer_uuid' => $customerPayload['uuid'],
        'street' => 'Caseros 600',
        'is_primary' => true,
    ])]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $primary->id);

    expect($customer->addresses()->count())->toBe(1)
        ->and($customer->addresses()->where('is_primary', true)->count())->toBe(1)
        ->and($primary->fresh()->street)->toBe('Caseros 600');
});

test('a customer pushed without an address uuid leaves its primary address without one', function (): void {
    $customerPayload = customerWithAddressPayload([]);

    push(['customers' => [$customerPayload]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = Customer::query()->where('client_uuid', $customerPayload['uuid'])->firstOrFail();
    expect($customer->primaryAddress->client_uuid)->toBeNull();
});

test('a newer customer edit gives its address uuid to a primary address that has none', function (): void {
    $customerUuid = (string) Str::uuid();
    $addressUuid = (string) Str::uuid();
    $this->customer->timestamps = false;
    $this->customer->forceFill(['client_uuid' => $customerUuid])->save();

    push(['customers' => [customerWithAddressPayload(['uuid' => $addressUuid], ['uuid' => $customerUuid])]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted');

    expect($this->primary->fresh()->client_uuid)->toBe($addressUuid);
});

test('a customer edit never replaces the uuid the primary address already has', function (): void {
    $customerUuid = (string) Str::uuid();
    $kept = (string) Str::uuid();
    $this->customer->timestamps = false;
    $this->customer->forceFill(['client_uuid' => $customerUuid])->save();
    $this->primary->timestamps = false;
    $this->primary->forceFill(['client_uuid' => $kept])->save();

    push(['customers' => [customerWithAddressPayload(['uuid' => (string) Str::uuid()], ['uuid' => $customerUuid])]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted');

    expect($this->primary->fresh()->client_uuid)->toBe($kept);
});

test('a customer push whose address uuid belongs to another address is rejected and changes nothing', function (bool $existingCustomer): void {
    $taken = (string) Str::uuid();
    $other = serverAddress($this->customer, $taken);
    $otherBefore = addressState($other);
    $primaryBefore = addressState($this->primary);
    $customerUuid = (string) Str::uuid();

    if ($existingCustomer) {
        $this->customer->timestamps = false;
        $this->customer->forceFill(['client_uuid' => $customerUuid])->save();
    }

    push(['customers' => [customerWithAddressPayload(['uuid' => $taken], ['uuid' => $customerUuid, 'name' => 'No se guarda'])]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.server_id', null)
        ->assertJsonPath('data.customers.0.error', 'The address uuid belongs to another address.');

    expect(addressState($other))->toEqual($otherBefore)
        ->and(addressState($this->primary))->toEqual($primaryBefore)
        ->and(Customer::query()->where('name', 'No se guarda')->exists())->toBeFalse()
        ->and(CustomerAddress::query()->count())->toBe(2);
})->with(['new customer' => false, 'existing customer' => true]);

test('a customer push with an address uuid that is not a uuid is rejected', function (): void {
    $customerPayload = customerWithAddressPayload(['uuid' => 'nope']);

    $response = push(['customers' => [$customerPayload]]);

    $response->assertOk()->assertJsonPath('data.customers.0.status', 'rejected');
    expect($response->json('data.customers.0.error'))->toBe('El campo identificador de la dirección debe ser un UUID válido.')
        ->and(Customer::query()->where('client_uuid', $customerPayload['uuid'])->exists())->toBeFalse();
});
