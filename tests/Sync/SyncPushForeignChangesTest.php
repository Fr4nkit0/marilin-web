<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

require_once __DIR__.'/PushHelpers.php';

const FOREIGN_REASON = 'Customer is assigned to another driver.';

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 12:00:00');
    $this->user = User::factory()->create(['enabled' => true, 'name' => 'Marcos']);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    $this->owner = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Esteban'])->id, 'enabled' => true]);
    $this->foreign = Customer::factory()->create([
        'driver_id' => $this->owner->id,
        'name' => 'Kiosco Ana',
        'phone_number' => '3874000000',
        'created_at' => '2026-10-20 10:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
    $this->address = CustomerAddress::factory()->create([
        'customer_id' => $this->foreign->id,
        'street' => 'Calle del servidor',
        'is_primary' => true,
        'created_at' => '2026-10-20 10:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
    $this->preference = CustomerPreference::factory()->create([
        'customer_id' => $this->foreign->id,
        'day_of_week' => 1,
        'time_window_start' => '09:00:00',
        'time_window_end' => '12:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * @return array<string, mixed>
 */
function foreignState(Customer $customer): array
{
    $customer->refresh();

    return [
        'customer' => $customer->only(['name', 'phone_number', 'enabled', 'driver_id', 'updated_at', 'needs_review']),
        'addresses' => $customer->addresses()->orderBy('id')->get()->map->only(['id', 'street', 'visual_reference', 'is_primary', 'enabled', 'updated_at'])->all(),
        'preferences' => $customer->preferences()->orderBy('id')->get()->map->only(['id', 'day_of_week', 'time_window_start', 'time_window_end', 'enabled', 'updated_at'])->all(),
    ];
}

/**
 * The inbox rows the admin sees for the pending customer changes.
 *
 * @return list<array<string, mixed>>
 */
function pendingChangeRows(): array
{
    $admin = User::factory()->superUser()->create();
    $page = null;
    test()->actingAs($admin, 'web')->get('/review?type=customer_change')->assertOk()
        ->assertInertia(function (Assert $inertia) use (&$page): void {
            $page = $inertia->toArray()['props'];
        });

    return $page['items']['data'];
}

test('a driver\'s edit of another driver\'s customer is not applied, waits for approval and is answered accepted with the reason', function (): void {
    $before = foreignState($this->foreign);

    $response = push(['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->foreign->id,
        'name' => 'Kiosco Ana renombrado',
        'address' => ['street' => 'Nueva calle 123'],
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]]);

    $response->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.server_id', $this->foreign->id)
        ->assertJsonPath('data.customers.0.review_reasons', [FOREIGN_REASON])
        ->assertJsonMissingPath('data.customers.0.current');

    expect(foreignState($this->foreign))->toEqual($before);

    $rows = pendingChangeRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['driver'])->toBe('Marcos')
        ->and($rows[0]['customer'])->toBe('Kiosco Ana')
        ->and($rows[0]['reasons'])->toBe(['El cliente está asignado a otro repartidor.'])
        ->and($rows[0]['change']['entity'])->toBe('customer')
        ->and($rows[0]['change']['status'])->toBe('PENDING')
        ->and($rows[0]['change']['fields'])->toBe([
            ['field' => 'name', 'current' => 'Kiosco Ana', 'proposed' => 'Kiosco Ana renombrado'],
            ['field' => 'address.street', 'current' => 'Calle del servidor', 'proposed' => 'Nueva calle 123'],
        ]);
});

test('a driver disabling another driver\'s customer waits for approval and the customer stays enabled', function (): void {
    $before = foreignState($this->foreign);

    push(['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->foreign->id,
        'enabled' => false,
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', [FOREIGN_REASON]);

    expect(foreignState($this->foreign))->toEqual($before)
        ->and(pendingChangeRows()[0]['change']['fields'])->toBe([
            ['field' => 'enabled', 'current' => true, 'proposed' => false],
        ]);
});

test('a driver\'s edit of an address of another driver\'s customer waits for approval', function (): void {
    $before = foreignState($this->foreign);

    push(['customer_addresses' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->address->id,
        'customer_id' => $this->foreign->id,
        'visual_reference' => 'Porton rojo',
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', $this->address->id)
        ->assertJsonPath('data.customer_addresses.0.review_reasons', [FOREIGN_REASON]);

    $rows = pendingChangeRows();

    expect(foreignState($this->foreign))->toEqual($before)
        ->and($rows[0]['change']['entity'])->toBe('customer_address')
        ->and($rows[0]['change']['fields'])->toBe([
            ['field' => 'visual_reference', 'current' => $this->address->visual_reference, 'proposed' => 'Porton rojo'],
        ]);
});

test('a new address on another driver\'s customer waits for approval and has no server id yet', function (): void {
    $before = foreignState($this->foreign);

    push(['customer_addresses' => [[
        'uuid' => (string) Str::uuid(),
        'customer_id' => $this->foreign->id,
        'street' => 'Caseros 1200',
        'is_primary' => false,
        'enabled' => true,
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'accepted')
        ->assertJsonPath('data.customer_addresses.0.server_id', null)
        ->assertJsonPath('data.customer_addresses.0.review_reasons', [FOREIGN_REASON]);

    expect(foreignState($this->foreign))->toEqual($before)
        ->and(collect(pendingChangeRows()[0]['change']['fields'])->firstWhere('field', 'street'))
        ->toBe(['field' => 'street', 'current' => null, 'proposed' => 'Caseros 1200']);
});

test('a driver\'s edit of a preference of another driver\'s customer waits for approval', function (): void {
    $before = foreignState($this->foreign);

    push(['customer_preferences' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->preference->id,
        'customer_id' => $this->foreign->id,
        'day_of_week' => 1,
        'time_window_start' => '14:00',
        'time_window_end' => '16:00',
        'enabled' => true,
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $this->preference->id)
        ->assertJsonPath('data.customer_preferences.0.review_reasons', [FOREIGN_REASON]);

    $rows = pendingChangeRows();

    expect(foreignState($this->foreign))->toEqual($before)
        ->and($rows[0]['change']['entity'])->toBe('customer_preference')
        ->and($rows[0]['change']['fields'])->toBe([
            ['field' => 'day_of_week', 'current' => 1, 'proposed' => 1],
            ['field' => 'time_window_start', 'current' => '09:00', 'proposed' => '14:00'],
            ['field' => 'time_window_end', 'current' => '12:00', 'proposed' => '16:00'],
            ['field' => 'enabled', 'current' => true, 'proposed' => true],
        ]);
});

test('a replayed foreign edit is stored once', function (): void {
    $record = [
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->foreign->id,
        'name' => 'Kiosco Ana renombrado',
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ];

    push(['customers' => [$record]])->assertOk();
    push(['customers' => [$record]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', [FOREIGN_REASON]);

    expect(pendingChangeRows())->toHaveCount(1);
});

test('the driver\'s own customer is still edited at once', function (): void {
    $own = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_at' => '2026-10-20 10:00:00']);

    push(['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $own->id,
        'name' => 'Editado por su repartidor',
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', []);

    expect($own->fresh()->name)->toBe('Editado por su repartidor')
        ->and(pendingChangeRows())->toBe([]);
});

test('a new customer is never foreign: it is created for the driver at once', function (): void {
    $uuid = (string) Str::uuid();

    push(['customers' => [[
        'uuid' => $uuid,
        'name' => 'Cliente nuevo',
        'address' => ['street' => 'Belgrano 1'],
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', []);

    expect(Customer::query()->where('client_uuid', $uuid)->value('driver_id'))->toBe($this->driver->id)
        ->and(pendingChangeRows())->toBe([]);
});

test('a sale on another driver\'s customer is still accepted and marked, not left pending', function (): void {
    $product = Product::factory()->create(['price' => 1000]);
    $uuid = (string) Str::uuid();

    push(['sales' => [[
        'uuid' => $uuid,
        'customer_id' => $this->foreign->id,
        'date' => '2026-10-22T10:00:00-03:00',
        'total' => 1000,
        'details' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
    ]]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reasons', [FOREIGN_REASON]);

    $sale = Sale::query()->where('client_uuid', $uuid)->firstOrFail();

    expect($sale->needs_review)->toBeTrue()
        ->and($sale->review_reasons)->toBe([FOREIGN_REASON])
        ->and(pendingChangeRows())->toBe([]);
});

test('an edit of a customer with no driver also waits for approval: only the driver\'s own customers change at once', function (): void {
    $this->foreign->forceFill(['driver_id' => null])->save();

    push(['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->foreign->id,
        'name' => 'Sin repartidor',
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', [FOREIGN_REASON]);

    expect($this->foreign->fresh()->name)->toBe('Kiosco Ana')
        ->and(pendingChangeRows())->toHaveCount(1);
});

test('a foreign row sent under the driver\'s own customer is rejected and nothing changes', function (): void {
    $own = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $before = foreignState($this->foreign);

    push([
        'customer_addresses' => [['uuid' => (string) Str::uuid(), 'server_id' => $this->address->id, 'customer_id' => $own->id, 'street' => 'Bypass', 'modified_at' => '2026-10-21T09:00:00-03:00']],
        'customer_preferences' => [['uuid' => (string) Str::uuid(), 'server_id' => $this->preference->id, 'customer_id' => $own->id, 'day_of_week' => 1, 'enabled' => false, 'modified_at' => '2026-10-21T09:00:00-03:00']],
    ])->assertOk()
        ->assertJsonPath('data.customer_addresses.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected');

    expect(foreignState($this->foreign))->toEqual($before);
});
