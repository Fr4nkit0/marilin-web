<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->juan = Driver::factory()->create(['enabled' => true]);
    $this->carlos = Driver::factory()->create(['enabled' => true]);
    $this->customer = Customer::factory()->create([
        'driver_id' => $this->juan->id,
        'client_uuid' => '11111111-2222-4333-8444-555555555555',
        'name' => 'Cliente 27',
        'created_at' => '2026-10-20 10:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
    $this->address = CustomerAddress::factory()->create([
        'customer_id' => $this->customer->id, 'street' => 'Mitre 50', 'is_primary' => true,
        'created_at' => '2026-10-20 10:00:00', 'updated_at' => '2026-10-20 10:00:00',
    ]);
    $this->preference = CustomerPreference::factory()->create([
        'customer_id' => $this->customer->id, 'day_of_week' => 2, 'updated_at' => '2026-10-20 10:00:00',
    ]);
});

function reassign(Customer $customer, ?Driver $driver): void
{
    test()->actingAs(test()->admin, 'web')
        ->put(route('customers.update', $customer), ['name' => $customer->name, 'street' => 'Mitre 50', 'repartidor_id' => $driver?->id])
        ->assertSessionHasNoErrors();
}

function pullAs(Driver $driver, ?string $cursor): TestResponse
{
    Sanctum::actingAs($driver->user, ['*']);

    return test()->getJson('/api/v1/sync/pull'.($cursor === null ? '' : '?cursor='.urlencode($cursor)))->assertOk();
}

test('the pull tells the old driver which customers left them since the cursor, with ids and when', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, $this->carlos);
    $this->travelTo('2026-10-22 13:00:00');

    $pull = pullAs($this->juan, '2026-10-22T12:00:00-03:00');

    expect($pull->json('data.customers_removed'))->toBe([[
        'customer_id' => $this->customer->id,
        'client_uuid' => '11111111-2222-4333-8444-555555555555',
        'removed_at' => '2026-10-22T15:30:00.000000Z',
    ]])
        ->and(collect($pull->json('data.customers'))->pluck('id'))->not->toContain($this->customer->id);
});

test('a customer unassigned from the driver also leaves them', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, null);

    expect(collect(pullAs($this->juan, '2026-10-22T12:00:00-03:00')->json('data.customers_removed'))->pluck('customer_id')->all())
        ->toBe([$this->customer->id]);
});

test('the new driver gets the reassigned customer with its address and preferences, and no removal', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, $this->carlos);

    $pull = pullAs($this->carlos, '2026-10-22T12:00:00-03:00');

    expect(collect($pull->json('data.customers'))->pluck('id')->all())->toBe([$this->customer->id])
        ->and(collect($pull->json('data.customer_addresses'))->pluck('id')->all())->toBe([$this->address->id])
        ->and(collect($pull->json('data.customer_preferences'))->pluck('id')->all())->toBe([$this->preference->id])
        ->and($pull->json('data.customers_removed'))->toBe([]);
});

test('the first pull, without a cursor, sends no removals', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, $this->carlos);

    expect(pullAs($this->juan, null)->json('data.customers_removed'))->toBe([]);
});

test('a removal before the cursor is not sent again', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, $this->carlos);

    expect(pullAs($this->juan, '2026-10-22T12:45:00-03:00')->json('data.customers_removed'))->toBe([]);
});

test('a customer reassigned back to the driver in the same window arrives in customers and not in customers_removed', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, $this->carlos);
    $this->travelTo('2026-10-22 12:40:00');
    reassign($this->customer, $this->juan);

    $pull = pullAs($this->juan, '2026-10-22T12:00:00-03:00');

    expect($pull->json('data.customers_removed'))->toBe([])
        ->and(collect($pull->json('data.customers'))->pluck('id')->all())->toBe([$this->customer->id])
        ->and(collect($pull->json('data.customer_addresses'))->pluck('id')->all())->toBe([$this->address->id]);
});

test('editing a customer without changing its driver removes it from nobody', function (): void {
    $this->travelTo('2026-10-22 12:30:00');
    reassign($this->customer, $this->juan);

    expect(pullAs($this->juan, '2026-10-22T12:00:00-03:00')->json('data.customers_removed'))->toBe([])
        ->and(pullAs($this->carlos, '2026-10-22T12:00:00-03:00')->json('data.customers_removed'))->toBe([]);
});

test('a removal reaches only the driver the customer left', function (): void {
    $pedro = Driver::factory()->create(['enabled' => true]);
    $cursor = now()->toIso8601String();
    $this->travel(1)->minutes();
    reassign($this->customer, $this->carlos);
    $this->travel(1)->minutes();

    expect(pullAs($pedro, $cursor)->json('data.customers_removed'))->toBe([])
        ->and(pullAs($this->carlos, $cursor)->json('data.customers_removed'))->toBe([])
        ->and(collect(pullAs($this->juan, $cursor)->json('data.customers_removed'))->pluck('customer_id')->all())->toBe([$this->customer->id]);
});
