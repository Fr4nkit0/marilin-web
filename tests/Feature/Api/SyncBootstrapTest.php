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
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\VisitOutcome;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\RouteStock\Models\RouteInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function driverWithToday(): array
{
    $user = User::factory()->create(['enabled' => true]);
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    $route = Route::factory()->create([
        'assigned_driver_id' => $driver->id,
        'date' => now()->toDateString(),
        'status' => 'PLANNED',
    ]);

    return [$user, $driver, $route];
}

test('bootstrap returns the full day of the authenticated driver', function (): void {
    [$user, $driver, $route] = driverWithToday();
    Sanctum::actingAs($user, ['*']);

    $product = Product::factory()->create(['enabled' => true, 'price' => 1250.50]);
    $containerType = ContainerType::factory()->create(['enabled' => true]);
    $paymentMethod = PaymentMethod::factory()->create(['enabled' => true]);
    $outcome = VisitOutcome::factory()->create(['enabled' => true]);
    $disabledProduct = Product::factory()->create(['enabled' => false]);

    $customer = Customer::factory()->create(['driver_id' => $driver->id, 'enabled' => true]);
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'floor' => 'PB']);
    CustomerBalance::factory()->create(['customer_id' => $customer->id, 'current_balance' => 1500.50]);
    CustomerContainerBalance::factory()->create([
        'customer_id' => $customer->id,
        'container_type_id' => $containerType->id,
        'balance' => 4,
    ]);

    $current = CustomerAgreement::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'unit_price' => 900,
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
        'client_uuid' => '7b0a3c9e-5d0e-4f7e-9d57-0c6a4c1b2f10',
    ]);
    $expired = CustomerAgreement::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'valid_from' => now()->subMonths(6)->toDateString(),
        'valid_to' => now()->subMonths(2)->toDateString(),
    ]);

    $original = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
    ]);
    $rescheduled = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
        'is_rescheduled_from' => $original->id,
        'scheduled_time' => '16:00:00',
    ]);
    RouteInventory::factory()->create(['route_id' => $route->id, 'product_id' => $product->id, 'current_quantity' => 20]);

    $response = $this->getJson('/api/v1/sync/bootstrap')->assertOk();

    expect($response->json('data.route.id'))->toBe($route->id)
        ->and($response->json('data.server_time'))->not->toBeNull()
        ->and(collect($response->json('data.products'))->pluck('id'))->toContain($product->id)
        ->and(collect($response->json('data.products'))->firstWhere('id', $product->id)['price'])->toBe('1250.50')
        ->and(collect($response->json('data.products'))->pluck('id'))->not->toContain($disabledProduct->id)
        ->and(collect($response->json('data.container_types'))->pluck('id'))->toContain($containerType->id)
        ->and(collect($response->json('data.payment_methods'))->pluck('id'))->toContain($paymentMethod->id)
        ->and(collect($response->json('data.visit_outcomes'))->pluck('id'))->toContain($outcome->id)
        ->and($response->json('data.truck_inventory'))->toHaveCount(1);

    $stops = collect($response->json('data.stops'))->keyBy('id');
    expect($stops)->toHaveCount(2)
        ->and($stops[$original->id]['scheduled_time'])->toBeNull()
        ->and($stops[$rescheduled->id]['scheduled_time'])->toBe('16:00:00')
        ->and($stops[$rescheduled->id]['is_rescheduled_from'])->toBe($original->id);

    $customers = $response->json('data.customers');
    expect($customers)->toHaveCount(1);
    $payload = $customers[0];
    expect($payload['id'])->toBe($customer->id)
        ->and($payload['addresses'])->toHaveCount(1)
        ->and($payload['addresses'][0]['floor'])->toBe('PB')
        ->and($payload)->toHaveKey('preferences')
        ->and(collect($payload['agreements'])->pluck('id')->all())->toBe([$current->id])
        ->and($payload['balance']['current_balance'])->toEqual('1500.50')
        ->and($payload['container_balances'][0]['balance'])->toBe(4)
        ->and($payload['container_balances'][0]['container_type']['id'])->toBe($containerType->id)
        ->and($payload['agreements'][0]['client_uuid'])->toBe('7b0a3c9e-5d0e-4f7e-9d57-0c6a4c1b2f10')
        ->and($payload['agreements'][0]['product']['id'])->toBe($product->id)
        ->and($response->json('data.truck_inventory.0.product.id'))->toBe($product->id)
        ->and(collect($payload['agreements'])->pluck('id'))->not->toContain($expired->id);
});

test('bootstrap does not expose another driver customers or route', function (): void {
    [$user, $driver] = driverWithToday();
    Sanctum::actingAs($user, ['*']);

    $otherDriver = Driver::factory()->create(['enabled' => true]);
    $otherRoute = Route::factory()->create([
        'assigned_driver_id' => $otherDriver->id,
        'date' => now()->toDateString(),
    ]);
    $otherCustomer = Customer::factory()->create(['driver_id' => $otherDriver->id, 'enabled' => true]);
    RouteStop::factory()->create(['route_id' => $otherRoute->id, 'customer_id' => $otherCustomer->id]);

    $response = $this->getJson('/api/v1/sync/bootstrap')->assertOk();

    expect($response->json('data.customers'))->toBe([])
        ->and($response->json('data.stops'))->toBe([])
        ->and($response->json('data.route.assigned_driver_id'))->not->toBe($otherDriver->id);
});

test('bootstrap returns today route even when an older route is still in progress', function (): void {
    [$user, $driver, $today] = driverWithToday();
    Sanctum::actingAs($user, ['*']);

    Route::factory()->create([
        'assigned_driver_id' => $driver->id,
        'date' => now()->subDay()->toDateString(),
        'status' => 'IN_PROGRESS',
    ]);

    $this->getJson('/api/v1/sync/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.route.id', $today->id);
});

test('bootstrap uses the Argentine day, so a late sync still gets that day route', function (): void {
    // 01:30 UTC on the 22nd is 22:30 on the 21st in Argentina.
    $this->travelTo(Carbon::parse('2026-10-22 01:30:00', 'UTC'));
    $user = User::factory()->create(['enabled' => true]);
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    $route = Route::factory()->create([
        'assigned_driver_id' => $driver->id,
        'date' => '2026-10-21',
        'status' => 'IN_PROGRESS',
    ]);
    Sanctum::actingAs($user, ['*']);

    expect(config('app.timezone'))->toBe('America/Argentina/Buenos_Aires');
    $this->getJson('/api/v1/sync/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.route.id', $route->id);
});

test('bootstrap sends no route when the only open one is from another day', function (): void {
    $user = User::factory()->create(['enabled' => true]);
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    Route::factory()->create([
        'assigned_driver_id' => $driver->id,
        'date' => now()->subDay()->toDateString(),
        'status' => 'IN_PROGRESS',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/sync/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.route', null)
        ->assertJsonPath('data.stops', []);
});

test('bootstrap orders stops by scheduled time and omits stops of customers no longer assigned', function (): void {
    [$user, $driver, $route] = driverWithToday();
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['driver_id' => $driver->id, 'enabled' => true]);
    $late = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $customer->id, 'scheduled_time' => '17:00:00']);
    $early = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $customer->id, 'scheduled_time' => '09:00:00']);
    $unscheduled = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $customer->id]);

    $disabled = Customer::factory()->create(['driver_id' => $driver->id, 'enabled' => false]);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $disabled->id]);
    $reassigned = Customer::factory()->create(['driver_id' => Driver::factory()->create()->id, 'enabled' => true]);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $reassigned->id]);

    $ids = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.stops'))->pluck('id')->all();

    expect($ids)->toBe([$unscheduled->id, $early->id, $late->id]);
});

test('bootstrap server_time carries the safety margin so the first pull cannot miss a same-second write', function (): void {
    [$user] = driverWithToday();
    Sanctum::actingAs($user, ['*']);

    $serverTime = Carbon::parse($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.server_time'));

    expect($serverTime->lte(now()->subSeconds((int) config('sync.pull_cursor_margin_seconds'))->addSecond()))->toBeTrue();
});
