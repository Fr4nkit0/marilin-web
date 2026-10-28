<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Models\RouteInventory;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

test('sync bootstrap returns driver active route, stops, catalog and truck inventory', function (): void {
    $product = Product::factory()->create(['enabled' => true]);
    $containerType = ContainerType::factory()->create(['enabled' => true]);
    $paymentMethod = PaymentMethod::factory()->create(['enabled' => true]);

    $route = Route::create([
        'assigned_driver_id' => $this->driver->id,
        'date' => now()->toDateString(),
        'status' => 'IN_PROGRESS',
        'enabled' => true,
    ]);

    $customer = Customer::factory()->create([
        'driver_id' => $this->driver->id,
        'created_by' => $this->user->id,
        'enabled' => true,
    ]);

    $address = CustomerAddress::create([
        'customer_id' => $customer->id,
        'street' => 'Av Belgrano 100',
        'latitude' => -24.785,
        'longitude' => -65.411,
        'created_by' => $this->user->id,
        'is_primary' => true,
        'enabled' => true,
    ]);

    RouteStop::create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
        'enabled' => true,
    ]);

    RouteInventory::create([
        'route_id' => $route->id,
        'product_id' => $product->id,
        'current_quantity' => 20,
        'created_by' => $this->user->id,
        'enabled' => true,
    ]);

    $response = $this->getJson('/api/v1/sync/bootstrap');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'route' => ['id', 'assigned_driver_id', 'date', 'status'],
                'stops',
                'truck_inventory',
                'customers',
                'products',
                'container_types',
                'payment_methods',
                'visit_outcomes',
                'server_time',
            ],
            'message',
        ]);

    expect($response->json('data.route.id'))->toBe($route->id);
    expect($response->json('data.stops'))->toHaveCount(1);
    expect($response->json('data.truck_inventory'))->toHaveCount(1);
});
