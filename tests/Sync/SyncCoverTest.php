<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Customers\Customers\Models\CustomerReassignment;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

uses(RefreshDatabase::class);

// The cover (ADR 0004): Marcos covers today part of the route of Esteban, who is absent; Esteban still owns the customers.
beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $old = '2026-10-01 10:00:00';
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    $this->absent = Driver::factory()->create(['enabled' => true]);

    $this->absentRoute = Route::factory()->undispatched()->create(['assigned_driver_id' => $this->absent->id, 'date' => '2026-10-22']);
    $this->route = Route::factory()->create([
        'assigned_driver_id' => $this->driver->id,
        'date' => '2026-10-22',
        'dispatched_at' => '2026-10-22 07:00:00',
        'updated_at' => '2026-10-22 07:00:00',
    ]);

    $this->covered = Customer::factory()->create(['driver_id' => $this->absent->id, 'enabled' => true, 'updated_at' => $old]);
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->covered->id, 'is_primary' => true, 'updated_at' => $old]);
    // Neither the address of its stop nor its primary one: a visit does not need it (S14).
    $this->otherAddress = CustomerAddress::factory()->create(['customer_id' => $this->covered->id, 'is_primary' => false, 'updated_at' => $old]);
    $this->preference = CustomerPreference::factory()->create(['customer_id' => $this->covered->id, 'updated_at' => $old]);
    $this->agreement = CustomerAgreement::factory()->create(['customer_id' => $this->covered->id, 'updated_at' => $old]);
    $this->balance = CustomerBalance::factory()->create(['customer_id' => $this->covered->id, 'current_balance' => 5000, 'updated_at' => $old]);
    $this->bidon = ContainerType::factory()->create(['enabled' => true]);
    $this->containerBalance = CustomerContainerBalance::factory()->create([
        'customer_id' => $this->covered->id, 'container_type_id' => $this->bidon->id, 'balance' => 3, 'updated_at' => $old,
    ]);
    $this->coveredStop = RouteStop::factory()->create([
        'route_id' => $this->route->id,
        'customer_id' => $this->covered->id,
        'address_id' => $this->address->id,
        'moved_from_route_id' => $this->absentRoute->id,
        'updated_at' => '2026-10-21 20:00:00',
    ]);

    // A customer of Esteban reassigned after the route was built: its stop was never moved, so it stays hidden.
    $this->foreign = Customer::factory()->create(['driver_id' => $this->absent->id, 'enabled' => true]);
    $this->foreignAddress = CustomerAddress::factory()->create(['customer_id' => $this->foreign->id]);
    CustomerBalance::factory()->create(['customer_id' => $this->foreign->id, 'current_balance' => 5000]);
    $this->foreignStop = RouteStop::factory()->create(['route_id' => $this->route->id, 'customer_id' => $this->foreign->id, 'address_id' => $this->foreignAddress->id]);

    $this->own = Customer::factory()->create(['driver_id' => $this->driver->id, 'enabled' => true]);
    $this->ownAddress = CustomerAddress::factory()->create(['customer_id' => $this->own->id]);
    $this->ownStop = RouteStop::factory()->create(['route_id' => $this->route->id, 'customer_id' => $this->own->id, 'address_id' => $this->ownAddress->id]);

    Sanctum::actingAs($this->user, ['*']);
});

function coverPull(?string $cursor): TestResponse
{
    return test()->getJson('/api/v1/sync/pull'.($cursor === null ? '' : '?cursor='.urlencode($cursor)))->assertOk();
}

function coverIds(TestResponse $response, string $entity): array
{
    return collect($response->json("data.{$entity}"))->pluck('id')->sort()->values()->all();
}

test('bootstrap sends the covered stop and its customer with only what a visit needs', function (): void {
    $response = $this->getJson('/api/v1/sync/bootstrap')->assertOk();

    $customers = collect($response->json('data.customers'))->keyBy('id');
    $covered = $customers[$this->covered->id];

    expect(collect($response->json('data.stops'))->pluck('id')->sort()->values()->all())->toBe(collect([$this->coveredStop->id, $this->ownStop->id])->sort()->values()->all())
        ->and($customers->keys()->sort()->values()->all())->toBe(collect([$this->covered->id, $this->own->id])->sort()->values()->all())
        ->and($covered['driver_id'])->toBe($this->absent->id)
        ->and(collect($covered['addresses'])->pluck('id')->all())->toBe([$this->address->id])
        ->and($covered['preferences'])->toBe([])
        ->and(collect($covered['agreements'])->pluck('id')->all())->toBe([$this->agreement->id])
        ->and($covered['balance']['id'])->toBe($this->balance->id)
        ->and(collect($covered['container_balances'])->pluck('id')->all())->toBe([$this->containerBalance->id])
        ->and($customers->keys())->not->toContain($this->foreign->id);
});

test('bootstrap leaves out a cover of a route not dispatched yet', function (): void {
    $this->route->update(['dispatched_at' => null]);

    $response = $this->getJson('/api/v1/sync/bootstrap')->assertOk();

    expect($response->json('data.stops'))->toBe([])
        ->and(collect($response->json('data.customers'))->pluck('id')->all())->toBe([$this->own->id]);
});

test('bootstrap stops sending the covered customer once the cover date is past', function (): void {
    $this->travelTo('2026-10-23 08:00:00');

    $response = $this->getJson('/api/v1/sync/bootstrap')->assertOk();

    expect(collect($response->json('data.customers'))->pluck('id')->all())->toBe([$this->own->id])
        ->and($response->json('data.stops'))->toBe([]);
});

test('the first pull sends the covered customer, its rows and its stop, and still hides a stop that was not moved', function (): void {
    $response = coverPull(null);

    expect(coverIds($response, 'customers'))->toBe(collect([$this->covered->id, $this->own->id])->sort()->values()->all())
        ->and(coverIds($response, 'route_stops'))->toBe(collect([$this->coveredStop->id, $this->ownStop->id])->sort()->values()->all())
        ->and(coverIds($response, 'customer_addresses'))->toBe(collect([$this->address->id, $this->ownAddress->id])->sort()->values()->all())
        ->and(coverIds($response, 'customer_preferences'))->toBe([])
        ->and(coverIds($response, 'customer_agreements'))->toBe([$this->agreement->id])
        ->and(coverIds($response, 'customer_balances'))->toBe([$this->balance->id])
        ->and(coverIds($response, 'customer_container_balances'))->toBe([$this->containerBalance->id])
        ->and($response->json('data.customers_removed'))->toBe([]);
});

test('a covered customer arrives with only what a visit needs when the covering route is dispatched after the cursor', function (): void {
    $this->route->update(['dispatched_at' => '2026-10-22 11:30:00']);
    $this->own->forceFill(['updated_at' => '2026-10-01 10:00:00'])->save();
    $this->ownAddress->forceFill(['updated_at' => '2026-10-01 10:00:00'])->save();

    $response = coverPull('2026-10-22T11:00:00-03:00');

    expect(coverIds($response, 'customers'))->toBe([$this->covered->id])
        ->and(coverIds($response, 'route_stops'))->toBe(collect([$this->coveredStop->id, $this->ownStop->id])->sort()->values()->all())
        ->and(coverIds($response, 'customer_addresses'))->toBe([$this->address->id])
        ->and(coverIds($response, 'customer_preferences'))->toBe([])
        ->and(coverIds($response, 'customer_agreements'))->toBe([$this->agreement->id])
        ->and(coverIds($response, 'customer_balances'))->toBe([$this->balance->id])
        ->and(coverIds($response, 'customer_container_balances'))->toBe([$this->containerBalance->id]);
});

test('a pull sends neither the cover nor its customer before the covering route is dispatched', function (): void {
    $this->route->update(['dispatched_at' => null]);

    $response = coverPull(null);

    expect(coverIds($response, 'customers'))->toBe([$this->own->id])
        ->and(coverIds($response, 'route_stops'))->toBe([])
        ->and(coverIds($response, 'customer_addresses'))->toBe([$this->ownAddress->id])
        ->and(coverIds($response, 'customer_balances'))->toBe([]);
});

test('the pull removes the covered customer once, when its cover date ends, keeping the owned ones', function (): void {
    $this->travelTo('2026-10-23 08:00:00');

    $first = coverPull('2026-10-22T20:00:00-03:00');
    $again = coverPull('2026-10-23T07:00:00-03:00');
    $fresh = coverPull(null);

    expect($first->json('data.customers_removed'))->toBe([[
        'customer_id' => $this->covered->id,
        'client_uuid' => $this->covered->client_uuid,
        'removed_at' => '2026-10-23T03:00:00.000000Z',
    ]])
        ->and(coverIds($first, 'customers'))->toBe([])
        ->and($again->json('data.customers_removed'))->toBe([])
        ->and(coverIds($fresh, 'customers'))->toBe([$this->own->id])
        ->and(coverIds($fresh, 'customer_addresses'))->toBe([$this->ownAddress->id])
        ->and(coverIds($fresh, 'customer_balances'))->toBe([]);
});

test('a cover that ended is not removed while the driver covers the customer again or owns it now', function (string $case): void {
    if ($case === 'covers again') {
        $tomorrow = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-23']);
        RouteStop::factory()->create([
            'route_id' => $tomorrow->id,
            'customer_id' => $this->covered->id,
            'address_id' => $this->address->id,
            'moved_from_route_id' => Route::factory()->undispatched()->create(['assigned_driver_id' => $this->absent->id, 'date' => '2026-10-23'])->id,
        ]);
    } else {
        $this->covered->update(['driver_id' => $this->driver->id]);
    }
    $this->travelTo('2026-10-23 08:00:00');

    $response = coverPull('2026-10-22T20:00:00-03:00');

    expect($response->json('data.customers_removed'))->toBe([])
        ->and(coverIds(coverPull(null), 'customers'))->toContain($this->covered->id);
})->with(['covers again', 'owns it now']);

test('street records on a covered customer are not flagged as another driver\'s customer', function (): void {
    $water = Product::factory()->create(['enabled' => true, 'price' => 700]);
    RouteInventory::query()->create(['route_id' => $this->route->id, 'product_id' => $water->id, 'current_quantity' => 20, 'created_by' => $this->user->id]);
    $method = PaymentMethod::factory()->create(['enabled' => true]);
    $uuids = ['visit' => (string) Str::uuid(), 'sale' => (string) Str::uuid(), 'payment' => (string) Str::uuid(), 'movement' => (string) Str::uuid()];

    $response = push([
        'visits' => [['uuid' => $uuids['visit'], 'customer_id' => $this->covered->id, 'route_stop_id' => $this->coveredStop->id, 'arrived_at' => '2026-10-22T13:00:00Z']],
        'sales' => [[
            'uuid' => $uuids['sale'], 'customer_id' => $this->covered->id, 'visit_uuid' => $uuids['visit'], 'date' => '2026-10-22T13:05:00Z',
            'total' => 1400, 'is_credit_sale' => true, 'details' => [['product_id' => $water->id, 'quantity' => 2, 'unit_price' => 700]],
        ]],
        'payments' => [['uuid' => $uuids['payment'], 'customer_id' => $this->covered->id, 'visit_uuid' => $uuids['visit'], 'payment_method_id' => $method->id, 'amount' => 1000, 'paid_at' => '2026-10-22T13:10:00Z']],
        'container_movements' => [['uuid' => $uuids['movement'], 'customer_id' => $this->covered->id, 'visit_uuid' => $uuids['visit'], 'container_type_id' => $this->bidon->id, 'movement_type' => 'DELIVERED', 'quantity' => 1]],
    ])->assertOk();

    foreach (['visits', 'sales', 'payments', 'container_movements'] as $entity) {
        $response->assertJsonPath("data.{$entity}.0.status", 'accepted')
            ->assertJsonPath("data.{$entity}.0.review_reasons", []);
    }
    expect(Visit::query()->where('client_uuid', $uuids['visit'])->firstOrFail()->only(['needs_review', 'review_reasons']))->toBe(['needs_review' => false, 'review_reasons' => []])
        ->and(Sale::query()->where('client_uuid', $uuids['sale'])->firstOrFail()->only(['needs_review', 'review_reasons']))->toBe(['needs_review' => false, 'review_reasons' => []])
        ->and(Payment::query()->where('client_uuid', $uuids['payment'])->firstOrFail()->only(['needs_review', 'review_reasons']))->toBe(['needs_review' => false, 'review_reasons' => []])
        ->and(ContainerMovement::query()->where('client_uuid', $uuids['movement'])->firstOrFail()->only(['needs_review', 'review_reasons']))->toBe(['needs_review' => false, 'review_reasons' => []])
        ->and($this->covered->fresh()->driver_id)->toBe($this->absent->id);
});

test('street records on a customer the driver does not cover that day are still flagged', function (string $case): void {
    $customer = match ($case) {
        'stop not moved' => $this->foreign,
        'cover on another day' => tap($this->covered, fn () => $this->route->update(['date' => '2026-10-21'])),
        'cover not dispatched' => tap($this->covered, fn () => $this->route->update(['dispatched_at' => null])),
    };
    $method = PaymentMethod::factory()->create(['enabled' => true]);
    $uuids = ['visit' => (string) Str::uuid(), 'payment' => (string) Str::uuid(), 'movement' => (string) Str::uuid()];
    $foreign = [ReviewReason::ForeignCustomer->label()];

    $response = push([
        'visits' => [['uuid' => $uuids['visit'], 'customer_id' => $customer->id, 'arrived_at' => '2026-10-22T13:00:00Z']],
        'payments' => [['uuid' => $uuids['payment'], 'customer_id' => $customer->id, 'payment_method_id' => $method->id, 'amount' => 1, 'paid_at' => '2026-10-22T13:10:00Z']],
        'container_movements' => [['uuid' => $uuids['movement'], 'customer_id' => $customer->id, 'visit_uuid' => $uuids['visit'], 'container_type_id' => $this->bidon->id, 'movement_type' => 'DELIVERED', 'quantity' => 1]],
    ])->assertOk();

    foreach (['visits', 'payments', 'container_movements'] as $entity) {
        $response->assertJsonPath("data.{$entity}.0.status", 'accepted')
            ->assertJsonPath("data.{$entity}.0.review_reasons", $foreign);
    }
    expect(Payment::query()->where('client_uuid', $uuids['payment'])->firstOrFail()->needs_review)->toBeTrue()
        ->and(Visit::query()->where('client_uuid', $uuids['visit'])->firstOrFail()->needs_review)->toBeTrue();
})->with(['stop not moved', 'cover on another day', 'cover not dispatched']);

test('a sale on a customer covered on another day is still flagged', function (): void {
    $water = Product::factory()->create(['enabled' => true, 'price' => 700]);
    $this->route->update(['date' => '2026-10-21']);
    $uuid = (string) Str::uuid();

    push(['sales' => [[
        'uuid' => $uuid, 'customer_id' => $this->covered->id, 'date' => '2026-10-22T13:05:00Z',
        'total' => 700, 'is_credit_sale' => true, 'details' => [['product_id' => $water->id, 'quantity' => 1, 'unit_price' => 700]],
    ]]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted');

    expect(Sale::query()->where('client_uuid', $uuid)->firstOrFail()->review_reasons)->toContain(ReviewReason::ForeignCustomer->value);
});

test('an edit of a covered customer\'s data is still held for the admin', function (): void {
    $before = $this->covered->fresh()->only(['name', 'driver_id', 'updated_at']);

    push(['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $this->covered->id,
        'name' => 'Renombrado por quien cubre',
        'modified_at' => '2026-10-22T10:00:00-03:00',
    ]]])->assertOk()
        ->assertJsonPath('data.customers.0.status', 'accepted')
        ->assertJsonPath('data.customers.0.review_reasons', [ReviewReason::ForeignCustomer->label()]);

    expect($this->covered->fresh()->only(['name', 'driver_id', 'updated_at']))->toEqual($before);
});

test('the covered customer gets the address of its stop and its primary address, never its other addresses nor preferences', function (): void {
    $stopAddress = CustomerAddress::factory()->create(['customer_id' => $this->covered->id, 'is_primary' => false, 'updated_at' => '2026-10-01 10:00:00']);
    $this->coveredStop->update(['address_id' => $stopAddress->id]);
    $expected = collect([$this->address->id, $stopAddress->id])->sort()->values()->all();

    $bootstrap = $this->getJson('/api/v1/sync/bootstrap')->assertOk();
    $covered = collect($bootstrap->json('data.customers'))->keyBy('id')[$this->covered->id];
    $pull = coverPull(null);

    expect(collect($covered['addresses'])->pluck('id')->sort()->values()->all())->toBe($expected)
        ->and($covered['preferences'])->toBe([])
        ->and(coverIds($pull, 'customer_addresses'))->toBe(collect([...$expected, $this->ownAddress->id])->sort()->values()->all())
        ->and(coverIds($pull, 'customer_preferences'))->toBe([]);
});

test('a change inside the window to a covered customer\'s preference or other address does not reach the covering app', function (): void {
    $this->preference->forceFill(['updated_at' => '2026-10-22 11:30:00', 'received_at' => '2026-10-22 11:30:00'])->save();
    $this->otherAddress->forceFill(['updated_at' => '2026-10-22 11:30:00', 'received_at' => '2026-10-22 11:30:00'])->save();
    $this->address->forceFill(['updated_at' => '2026-10-22 11:30:00'])->save();
    $this->ownAddress->forceFill(['updated_at' => '2026-10-01 10:00:00'])->save();

    $response = coverPull('2026-10-22T11:00:00-03:00');

    expect(coverIds($response, 'customer_preferences'))->toBe([])
        ->and(coverIds($response, 'customer_addresses'))->toBe([$this->address->id]);
});

test('an own customer still gets all its preferences and addresses', function (): void {
    $preference = CustomerPreference::factory()->create(['customer_id' => $this->own->id]);
    $secondary = CustomerAddress::factory()->create(['customer_id' => $this->own->id, 'is_primary' => false]);
    $addresses = collect([$this->ownAddress->id, $secondary->id])->sort()->values()->all();

    $own = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.customers'))->keyBy('id')[$this->own->id];
    $pull = coverPull(null);

    expect(collect($own['addresses'])->pluck('id')->sort()->values()->all())->toBe($addresses)
        ->and(collect($own['preferences'])->pluck('id')->all())->toBe([$preference->id])
        ->and(coverIds($pull, 'customer_preferences'))->toBe([$preference->id])
        ->and(coverIds($pull, 'customer_addresses'))->toBe(collect([...$addresses, $this->address->id])->sort()->values()->all());
});

test('a customer that is both own and on a moved stop is treated as own', function (): void {
    $this->covered->update(['driver_id' => $this->driver->id]);

    $covered = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.customers'))->keyBy('id')[$this->covered->id];
    $pull = coverPull(null);

    expect(collect($covered['addresses'])->pluck('id')->sort()->values()->all())->toBe(collect([$this->address->id, $this->otherAddress->id])->sort()->values()->all())
        ->and(collect($covered['preferences'])->pluck('id')->all())->toBe([$this->preference->id])
        ->and(coverIds($pull, 'customer_preferences'))->toBe([$this->preference->id])
        ->and(coverIds($pull, 'customer_addresses'))->toBe(collect([$this->address->id, $this->otherAddress->id, $this->ownAddress->id])->sort()->values()->all());
});

test('every stop carries is_cover: true on a covered stop and false on an own stop, on bootstrap and pull', function (): void {
    $bootstrap = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.stops'))->keyBy('id');
    $first = collect(coverPull(null)->json('data.route_stops'))->keyBy('id');
    $this->route->update(['dispatched_at' => '2026-10-22 11:30:00']);
    $later = collect(coverPull('2026-10-22T11:00:00-03:00')->json('data.route_stops'))->keyBy('id');

    foreach ([$bootstrap, $first, $later] as $stops) {
        expect($stops[$this->coveredStop->id]['is_cover'])->toBeTrue()
            ->and($stops[$this->ownStop->id]['is_cover'])->toBeFalse();
    }
});

test('every stop carries covered_driver_name: the absent driver of a covered stop, null on an own stop, on bootstrap and pull', function (): void {
    $this->absent->user->update(['name' => 'Esteban Ausente']);
    // The name comes from the route the stop was moved from, not from whoever owns the customer now.
    Customer::query()->whereKey($this->covered->id)->update(['driver_id' => null]);

    $bootstrap = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.stops'))->keyBy('id');
    $first = collect(coverPull(null)->json('data.route_stops'))->keyBy('id');

    foreach ([$bootstrap, $first] as $stops) {
        expect($stops[$this->coveredStop->id]['covered_driver_name'])->toBe('Esteban Ausente')
            ->and($stops[$this->ownStop->id])->toHaveKey('covered_driver_name')
            ->and($stops[$this->ownStop->id]['covered_driver_name'])->toBeNull();
    }
});

test('a covered stop is re-sent with the new name when the absent driver is renamed during the cover day', function (): void {
    $this->route->update(['dispatched_at' => '2026-10-22 08:00:00', 'updated_at' => '2026-10-22 08:00:00']);
    RouteStop::query()->whereKey([$this->coveredStop->id, $this->ownStop->id])->update(['updated_at' => '2026-10-22 08:00:00']);
    $this->travelTo('2026-10-22 12:00:00');
    $this->absent->user->update(['name' => 'Esteban Renombrado']);

    $stops = collect(coverPull('2026-10-22T10:00:00-03:00')->json('data.route_stops'))->keyBy('id');

    expect($stops[$this->coveredStop->id]['covered_driver_name'] ?? null)->toBe('Esteban Renombrado')
        ->and($stops->has($this->ownStop->id))->toBeFalse();
});

test('every customer carries is_cover: true when the driver only covers it, false when it is theirs', function (): void {
    $bootstrap = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.customers'))->keyBy('id');
    $first = collect(coverPull(null)->json('data.customers'))->keyBy('id');

    expect($bootstrap[$this->covered->id]['is_cover'])->toBeTrue()
        ->and($bootstrap[$this->own->id]['is_cover'])->toBeFalse()
        ->and($first[$this->covered->id]['is_cover'])->toBeTrue()
        ->and($first[$this->own->id]['is_cover'])->toBeFalse();
});

test('a covered customer carries is_cover true when it arrives with the dispatched route and when re-sent alone after an edit', function (): void {
    $this->route->update(['dispatched_at' => '2026-10-22 11:30:00']);
    $arrived = coverPull('2026-10-22T11:00:00-03:00');

    $this->travelTo('2026-10-22 13:00:00');
    $this->covered->update(['name' => 'Renombrado por el admin']);
    $edited = coverPull('2026-10-22T12:30:00-03:00');

    expect(collect($arrived->json('data.customers'))->keyBy('id')[$this->covered->id]['is_cover'])->toBeTrue()
        ->and(coverIds($edited, 'customers'))->toBe([$this->covered->id])
        ->and($edited->json('data.customers.0.is_cover'))->toBeTrue();
});

test('an own customer on a moved stop, or a covered one reassigned to the driver, carries is_cover false', function (): void {
    $this->covered->update(['driver_id' => $this->driver->id]);
    CustomerReassignment::query()->create([
        'customer_id' => $this->covered->id,
        'from_driver_id' => $this->absent->id,
        'to_driver_id' => $this->driver->id,
        'reassigned_by' => $this->user->id,
        'reassigned_at' => now(),
    ]);
    $this->covered->forceFill(['updated_at' => '2026-10-01 10:00:00'])->save();

    $bootstrap = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.customers'))->keyBy('id');
    $arrived = coverPull('2026-10-22T11:00:00-03:00');

    expect($bootstrap[$this->covered->id]['is_cover'])->toBeFalse()
        ->and(coverIds($arrived, 'customers'))->toContain($this->covered->id)
        ->and(collect($arrived->json('data.customers'))->keyBy('id')[$this->covered->id]['is_cover'])->toBeFalse()
        ->and(coverIds($arrived, 'customer_preferences'))->toBe([$this->preference->id]);
});
