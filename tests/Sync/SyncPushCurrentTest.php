<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerPreference;
use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

require_once __DIR__.'/PushHelpers.php';

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    Neighborhood::factory()->create(['name' => 'Centro']);
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->customerUuid = (string) Str::uuid();
    $this->addressUuid = (string) Str::uuid();
    push(['customers' => [[
        'uuid' => $this->customerUuid,
        'name' => 'Almacen Don Pepe',
        'phone_number' => '3874001122',
        'modified_at' => '2026-10-20T08:00:00-03:00',
        'address' => [
            'uuid' => $this->addressUuid,
            'street' => 'Av Belgrano 100',
            'visual_reference' => 'Porton verde',
            'neighborhood' => 'Centro',
        ],
    ]]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $this->customer = Customer::query()->where('client_uuid', $this->customerUuid)->firstOrFail();
    $this->address = $this->customer->primaryAddress;
    $this->travelTo('2026-10-21 12:00:00');
});

/**
 * The row exactly as GET /api/v1/sync/pull sends it.
 *
 * @return array<string, mixed>
 */
function pulledRow(string $entity, int $id): array
{
    $row = collect(test()->getJson('/api/v1/sync/pull')->assertOk()->json("data.{$entity}"))->firstWhere('id', $id);
    expect($row)->not->toBeNull();

    return $row;
}

/**
 * A customer's `current`: its pull row with its primary address's pull row under `address`.
 *
 * @return array<string, mixed>
 */
function pulledCustomer(Customer $customer): array
{
    return [
        ...pulledRow('customers', $customer->id),
        'address' => pulledRow('customer_addresses', $customer->fresh()->primaryAddress->id),
    ];
}

test('S8: an outdated customer edit carries the server row in the pull shape, with its primary address', function (): void {
    push(['customers' => [[
        'uuid' => $this->customerUuid, 'server_id' => $this->customer->id, 'modified_at' => '2026-10-21T11:00:00-03:00',
        'name' => 'Nombre nuevo', 'address' => ['visual_reference' => 'Porton rojo'],
    ]]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $result = push(['customers' => [[
        'uuid' => $this->customerUuid, 'server_id' => $this->customer->id, 'modified_at' => '2026-10-21T10:00:00-03:00',
        'name' => 'Nombre viejo', 'address' => ['visual_reference' => 'Porton viejo'],
    ]]])->assertOk()->assertJsonPath('data.customers.0.status', 'outdated')->json('data.customers.0');

    expect($result['current'])->toBe(pulledCustomer($this->customer))
        ->and($result['current']['name'])->toBe('Nombre nuevo')
        ->and($result['current']['address']['visual_reference'])->toBe('Porton rojo');
});

test('S8: an outdated address edit carries the address in the pull shape', function (): void {
    $edit = fn (string $at, string $reference): array => [
        'uuid' => $this->addressUuid, 'server_id' => $this->address->id, 'customer_id' => $this->customer->id,
        'modified_at' => $at, 'visual_reference' => $reference,
    ];
    push(['customer_addresses' => [$edit('2026-10-21T11:00:00-03:00', 'Porton rojo')]])
        ->assertOk()->assertJsonPath('data.customer_addresses.0.status', 'accepted');

    $result = push(['customer_addresses' => [$edit('2026-10-21T10:00:00-03:00', 'Porton viejo')]])
        ->assertOk()->assertJsonPath('data.customer_addresses.0.status', 'outdated')->json('data.customer_addresses.0');

    expect($result['current'])->toBe(pulledRow('customer_addresses', $this->address->id))
        ->and($result['current']['visual_reference'])->toBe('Porton rojo');
});

test('S8: an edit rejected by the server_id guard carries the row that server_id points at', function (): void {
    $result = push(['customers' => [[
        'uuid' => (string) Str::uuid(), 'server_id' => $this->customer->id, 'modified_at' => '2026-10-21T11:00:00-03:00',
        'name' => 'Otro nombre',
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.error', 'The server_id belongs to another app record.')
        ->json('data.customers.0');

    expect($result['current'])->toBe(pulledCustomer($this->customer))
        ->and($result['current']['name'])->toBe('Almacen Don Pepe');
});

test('S8: a preference edit rejected because its new slot is taken carries its own server row', function (): void {
    $preference = fn (string $uuid, string $start, string $end, string $at): array => [
        'uuid' => $uuid, 'customer_id' => $this->customer->id, 'day_of_week' => 4,
        'time_window_start' => $start, 'time_window_end' => $end, 'enabled' => true, 'modified_at' => $at,
    ];
    $ownUuid = (string) Str::uuid();
    push(['customer_preferences' => [
        $preference((string) Str::uuid(), '14:00', '15:00', '2026-10-21T10:00:00-03:00'),
        $preference($ownUuid, '16:00', '17:00', '2026-10-21T10:00:00-03:00'),
    ]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.1.status', 'accepted');
    $own = CustomerPreference::query()->where('client_uuid', $ownUuid)->firstOrFail();

    $result = push(['customer_preferences' => [$preference($ownUuid, '14:00', '16:00', '2026-10-21T11:00:00-03:00')]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The customer already has a preference for that day and time.')
        ->json('data.customer_preferences.0');

    expect($result['current'])->toBe(pulledRow('customer_preferences', $own->id))
        ->and($result['current']['time_window_start'])->toStartWith('16:00');
});

test('S8 E6b: a preference insert rejected because the slot is taken carries no current, it has no row on the server', function (): void {
    $preference = fn (string $start, string $end): array => [
        'uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'day_of_week' => 4,
        'time_window_start' => $start, 'time_window_end' => $end, 'enabled' => true, 'modified_at' => '2026-10-21T10:00:00-03:00',
    ];
    push(['customer_preferences' => [$preference('14:00', '15:00')]])
        ->assertOk()->assertJsonPath('data.customer_preferences.0.status', 'accepted');

    $result = push(['customer_preferences' => [$preference('14:00', '16:00')]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The customer already has a preference for that day and time.')
        ->json('data.customer_preferences.0');

    expect($result)->not->toHaveKey('current')
        ->and(CustomerPreference::query()->count())->toBe(1);
});

test('S8: a rejected create carries no current', function (array $record): void {
    $result = push(['customers' => [['uuid' => (string) Str::uuid(), ...$record]]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->json('data.customers.0');

    expect($result)->not->toHaveKey('current')
        ->and(Customer::query()->count())->toBe(1);
})->with([
    'missing name, checked by the create' => [['modified_at' => '2026-10-21T10:00:00-03:00', 'address' => ['street' => 'Calle 1']]],
    'missing modified_at, checked before anything' => [['name' => 'Nuevo', 'address' => ['street' => 'Calle 1']]],
    'server_id pointing at nothing' => [['server_id' => 999999, 'modified_at' => '2026-10-21T10:00:00-03:00', 'name' => 'Nuevo']],
]);

test('S8: accepted results carry no current', function (): void {
    $response = push([
        'customers' => [[
            'uuid' => $this->customerUuid, 'server_id' => $this->customer->id, 'modified_at' => '2026-10-21T11:00:00-03:00',
            'phone_number' => '3875999999',
        ]],
        'customer_addresses' => [[
            'uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'street' => 'Calle 2',
            'is_primary' => false, 'enabled' => true, 'modified_at' => '2026-10-21T11:00:00-03:00',
        ]],
        'customer_preferences' => [[
            'uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'day_of_week' => 2,
            'time_window_start' => '09:00', 'time_window_end' => '10:00', 'enabled' => true, 'modified_at' => '2026-10-21T11:00:00-03:00',
        ]],
    ])->assertOk();

    foreach (['customers', 'customer_addresses', 'customer_preferences'] as $entity) {
        $result = $response->json("data.{$entity}.0");
        expect($result['status'])->toBe('accepted')
            ->and(Arr::has($result, 'current'))->toBeFalse();
    }
});
