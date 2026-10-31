<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

require_once __DIR__.'/PushHelpers.php';

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    Neighborhood::factory()->create(['name' => 'Centro']);
    $this->admin = User::factory()->create(['enabled' => true]);
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
            'apartment' => '2B',
            'visual_reference' => 'Porton verde',
            'neighborhood' => 'Centro',
        ],
    ]]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $this->customer = Customer::query()->where('client_uuid', $this->customerUuid)->firstOrFail();
    $this->address = $this->customer->primaryAddress;
});

/**
 * An edit of the customer pushed in the beforeEach, carrying only the given fields.
 *
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function mergeEdit(string $uuid, int $serverId, string $modifiedAt, array $fields): array
{
    return ['uuid' => $uuid, 'server_id' => $serverId, 'modified_at' => $modifiedAt, ...$fields];
}

/**
 * The admin's edit of the customer page: the form always sends every field, changed or not.
 *
 * @param  array<string, mixed>  $changes
 */
function webEdit(User $admin, Customer $customer, array $changes): void
{
    $address = $customer->fresh()->primaryAddress;

    test()->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'name' => $customer->fresh()->name,
            'phone_number' => $customer->fresh()->phone_number,
            'street' => $address->street,
            'apartment' => $address->apartment,
            'floor' => $address->floor,
            'visual_reference' => $address->visual_reference,
            'neighborhood' => $address->neighborhood,
            'repartidor_id' => $customer->fresh()->driver_id,
            ...$changes,
        ])
        ->assertSessionHasNoErrors();

    Sanctum::actingAs(test()->user, ['*']);
}

/**
 * @return array<string, mixed>
 */
function customerSnapshot(Customer $customer): array
{
    $customer = $customer->fresh();

    return [
        'customer' => $customer->only(['name', 'phone_number', 'enabled', 'client_uuid', 'updated_at', 'received_at', 'field_modified_at']),
        'address' => $customer->primaryAddress->only(['street', 'apartment', 'floor', 'visual_reference', 'neighborhood', 'client_uuid', 'updated_at', 'received_at', 'field_modified_at']),
    ];
}

test('S3: an app edit of the phone only keeps the visual reference the admin set in the web before', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    webEdit($this->admin, $this->customer, ['visual_reference' => 'Porton AZUL web']);

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T11:00:00-03:00', [
        'phone_number' => '3875999999',
    ])]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $this->customer->id);

    $customer = $this->customer->fresh();
    expect($customer->phone_number)->toBe('3875999999')
        ->and($customer->name)->toBe('Almacen Don Pepe')
        ->and($customer->primaryAddress->visual_reference)->toBe('Porton AZUL web')
        ->and($customer->primaryAddress->street)->toBe('Av Belgrano 100');
});

test('a field older than its own clock is skipped while a newer field of the same edit applies', function (): void {
    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T11:00:00-03:00', [
        'phone_number' => '3875111111',
    ])]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T10:00:00-03:00', [
        'name' => 'Nombre nuevo',
        'phone_number' => '3875000000',
        'address' => ['visual_reference' => 'Porton rojo'],
    ])]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = $this->customer->fresh();
    expect($customer->name)->toBe('Nombre nuevo')
        ->and($customer->phone_number)->toBe('3875111111')
        ->and($customer->primaryAddress->visual_reference)->toBe('Porton rojo')
        ->and($customer->field_modified_at)->toHaveKeys(['name', 'phone_number'])
        ->and($customer->primaryAddress->field_modified_at)->toHaveKey('visual_reference');
});

test('S7: an edit whose every field is older than the server answers outdated and changes nothing', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    webEdit($this->admin, $this->customer, ['name' => 'Nombre web', 'visual_reference' => 'Porton web']);
    $before = customerSnapshot($this->customer);

    $this->travelTo('2026-10-21 12:00:00');
    $response = push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T09:00:00-03:00', [
        'name' => 'Nombre viejo',
        'address' => ['visual_reference' => 'Porton viejo'],
    ])]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.uuid', $this->customerUuid)
        ->assertJsonPath('data.customers.0.status', 'outdated')
        ->assertJsonPath('data.customers.0.server_id', $this->customer->id)
        ->assertJsonPath('data.customers.0.review_reasons', []);

    expect($response->json('data.customers.0'))->toHaveKeys(['uuid', 'status', 'server_id', 'review_reasons'])
        ->and(customerSnapshot($this->customer))->toEqual($before);
});

test('S7: an address edit whose every field is older than the server answers outdated and changes nothing', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    webEdit($this->admin, $this->customer, ['street' => 'Mitre 900']);
    $before = customerSnapshot($this->customer);

    $this->travelTo('2026-10-21 12:00:00');
    push(['customer_addresses' => [[
        'uuid' => $this->addressUuid,
        'server_id' => $this->address->id,
        'customer_id' => $this->customer->id,
        'street' => 'Calle vieja',
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'outdated')
        ->assertJsonPath('data.customer_addresses.0.server_id', $this->address->id);

    expect(customerSnapshot($this->customer))->toEqual($before);
});

test('an edit leaves every field it does not carry untouched', function (): void {
    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T11:00:00-03:00', [
        'name' => 'Solo el nombre',
    ])]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = $this->customer->fresh();
    expect($customer->name)->toBe('Solo el nombre')
        ->and($customer->phone_number)->toBe('3874001122')
        ->and($customer->enabled)->toBeTrue()
        ->and($customer->primaryAddress->only(['street', 'apartment', 'visual_reference', 'neighborhood']))->toBe([
            'street' => 'Av Belgrano 100',
            'apartment' => '2B',
            'visual_reference' => 'Porton verde',
            'neighborhood' => 'Centro',
        ])
        ->and($customer->field_modified_at)->toHaveKey('name')
        ->and($customer->field_modified_at)->not->toHaveKey('phone_number');
});

test('an address edit applies only the fields it carries', function (): void {
    $this->travelTo('2026-10-21 12:00:00');
    push(['customer_addresses' => [[
        'uuid' => $this->addressUuid,
        'customer_id' => $this->customer->id,
        'floor' => '3',
        'modified_at' => '2026-10-21T11:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $this->address->id);

    expect($this->address->fresh()->only(['street', 'apartment', 'floor', 'visual_reference', 'is_primary', 'enabled']))->toBe([
        'street' => 'Av Belgrano 100',
        'apartment' => '2B',
        'floor' => '3',
        'visual_reference' => 'Porton verde',
        'is_primary' => true,
        'enabled' => true,
    ]);
});

test('a new customer still needs its name and its street', function (string $missing, string $error): void {
    $payload = [
        'uuid' => (string) Str::uuid(),
        'name' => 'Kiosco Nuevo',
        'modified_at' => '2026-10-20T08:00:00-03:00',
        'address' => ['street' => 'Caseros 500', 'apartment' => '1A'],
    ];
    data_forget($payload, $missing);

    push(['customers' => [$payload]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.error', $error);

    expect(Customer::query()->where('client_uuid', $payload['uuid'])->exists())->toBeFalse();
})->with([
    'name' => ['name', 'El campo nombre es obligatorio.'],
    'street' => ['address.street', 'El campo calle es obligatorio.'],
    'address' => ['address', 'El campo dirección es obligatorio.'],
]);

test('a new address still needs its street, primary flag and enabled flag', function (string $missing): void {
    $payload = [
        'uuid' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'street' => 'Caseros 500',
        'is_primary' => false,
        'enabled' => true,
        'modified_at' => '2026-10-20T09:00:00-03:00',
    ];
    unset($payload[$missing]);

    push(['customer_addresses' => [$payload]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected');

    expect(CustomerAddress::query()->where('client_uuid', $payload['uuid'])->exists())->toBeFalse();
})->with(['street', 'is_primary', 'enabled']);

test('an edit cannot blank the name or the street it carries', function (): void {
    $before = customerSnapshot($this->customer);

    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-20T09:00:00-03:00', [
        'name' => '',
    ])]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.error', 'El campo nombre es obligatorio.');

    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-20T09:00:00-03:00', [
        'address' => ['street' => ''],
    ])]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.error', 'El campo calle es obligatorio.');

    expect(customerSnapshot($this->customer))->toEqual($before);
});

test('a web edit stamps only the fields it changed, so an older app edit of other fields still wins', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    webEdit($this->admin, $this->customer, ['phone_number' => '3870000000']);

    $customer = $this->customer->fresh();
    expect($customer->field_modified_at)->toHaveKey('phone_number')
        ->and($customer->field_modified_at)->not->toHaveKey('name')
        ->and($customer->primaryAddress->field_modified_at)->toBeNull();

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T09:30:00-03:00', [
        'name' => 'Nombre de la calle',
        'phone_number' => '3879999999',
        'address' => ['visual_reference' => 'Porton rojo'],
    ])]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = $this->customer->fresh();
    expect($customer->name)->toBe('Nombre de la calle')
        ->and($customer->phone_number)->toBe('3870000000')
        ->and($customer->primaryAddress->visual_reference)->toBe('Porton rojo');
});

test('an applied edit older than the row keeps its update date, is marked received now and travels in the pull', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    webEdit($this->admin, $this->customer, ['phone_number' => '3870000000']);

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T09:30:00-03:00', [
        'name' => 'Nombre de la calle',
    ])]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = $this->customer->fresh();
    expect($customer->updated_at->toIso8601String())->toBe('2026-10-21T10:00:00-03:00')
        ->and($customer->received_at->toIso8601String())->toBe('2026-10-21T12:00:00-03:00')
        ->and($customer->last_modified_by)->toBe($this->user->id);

    $this->travel(1)->minutes();
    $cursor = '2026-10-21T11:00:00-03:00';
    $ids = collect($this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.customers'))->pluck('id')->all();
    expect($ids)->toBe([$this->customer->id]);
});

test('a field without its own clock is judged against the creation of the row, not its last update', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    webEdit($this->admin, $this->customer, ['phone_number' => '3870000000']);

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-20T07:00:00-03:00', [
        'name' => 'Antes de crearlo',
    ])]])->assertOk()->assertJsonPath('data.customers.0.status', 'outdated');

    expect($this->customer->fresh()->name)->toBe('Almacen Don Pepe');
});

test('S5: an edit by server id of a row another app record already owns is rejected and changes nothing', function (): void {
    $before = customerSnapshot($this->customer);
    $stranger = (string) Str::uuid();

    push(['customers' => [mergeEdit($stranger, $this->customer->id, '2026-10-21T11:00:00-03:00', ['name' => 'Intruso'])]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.uuid', $stranger)
        ->assertJsonPath('data.customers.0.status', 'rejected')
        ->assertJsonPath('data.customers.0.server_id', null)
        ->assertJsonPath('data.customers.0.error', 'The server_id belongs to another app record.');

    push(['customer_addresses' => [[
        'uuid' => $stranger,
        'server_id' => $this->address->id,
        'customer_id' => $this->customer->id,
        'street' => 'Intrusa',
        'modified_at' => '2026-10-21T11:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_addresses.0.error', 'The server_id belongs to another app record.');

    expect(customerSnapshot($this->customer))->toEqual($before)
        ->and(Customer::query()->where('client_uuid', $stranger)->exists())->toBeFalse();
});

test('S5: a preference edit by server id of a preference another app record owns is rejected', function (): void {
    $owner = (string) Str::uuid();
    $preference = CustomerPreference::factory()->create([
        'customer_id' => $this->customer->id,
        'client_uuid' => $owner,
        'day_of_week' => 4,
        'time_window_start' => '14:00:00',
        'time_window_end' => '15:00:00',
    ]);

    push(['customer_preferences' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $preference->id,
        'customer_id' => $this->customer->id,
        'day_of_week' => 4,
        'time_window_start' => '14:00',
        'time_window_end' => '16:00',
        'enabled' => true,
        'modified_at' => '2026-10-21T11:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The server_id belongs to another app record.');

    $preference->refresh();
    expect($preference->time_window_end)->toBe('15:00:00')
        ->and($preference->client_uuid)->toBe($owner);
});

test('S5: a row created in the web is adopted by its first app edit by server id', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    $this->actingAs($this->admin)
        ->post(route('customers.store'), ['name' => 'Kiosco Web', 'street' => 'Mitre 50'])
        ->assertSessionHasNoErrors();
    Sanctum::actingAs($this->user, ['*']);
    $web = Customer::query()->where('name', 'Kiosco Web')->firstOrFail();
    $uuid = (string) Str::uuid();

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($uuid, $web->id, '2026-10-21T11:00:00-03:00', ['phone_number' => '3871234567'])]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $web->id);

    expect($web->fresh()->client_uuid)->toBe($uuid)
        ->and($web->fresh()->phone_number)->toBe('3871234567');
});

test('a replayed edit answers accepted again and changes nothing more', function (): void {
    $this->travelTo('2026-10-21 12:00:00');
    $edit = mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T11:00:00-03:00', ['name' => 'Editado']);

    push(['customers' => [$edit]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');
    $before = customerSnapshot($this->customer);

    $this->travelTo('2026-10-21 12:05:00');
    push(['customers' => [$edit]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    expect(customerSnapshot($this->customer))->toEqual($before);
});

test('a disable carried alone applies and flags the customer, an older one does not', function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    $this->customer->timestamps = false;
    $this->customer->forceFill(['field_modified_at' => ['enabled' => '2026-10-21T10:00:00-03:00']])->save();

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T09:00:00-03:00', ['enabled' => false])]])
        ->assertOk()->assertJsonPath('data.customers.0.status', 'outdated');
    expect($this->customer->fresh()->enabled)->toBeTrue();

    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T11:00:00-03:00', ['enabled' => false])]])
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', ['Customer disabled by a driver.']);

    $customer = $this->customer->fresh();
    expect($customer->enabled)->toBeFalse()
        ->and($customer->name)->toBe('Almacen Don Pepe')
        ->and($customer->needs_review)->toBeTrue();
});

test('enabled true carried alone never re-enables a disabled customer', function (): void {
    $this->customer->timestamps = false;
    $this->customer->forceFill(['enabled' => false])->save();

    $this->travelTo('2026-10-21 12:00:00');
    push(['customers' => [mergeEdit($this->customerUuid, $this->customer->id, '2026-10-21T11:00:00-03:00', ['enabled' => true])]])
        ->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    expect($this->customer->fresh()->enabled)->toBeFalse();
});
