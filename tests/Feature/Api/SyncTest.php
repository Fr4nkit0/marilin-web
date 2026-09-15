<?php

declare(strict_types=1);

use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContainerBalance;
use App\Models\Driver;
use App\Models\IdempotencyRecord;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

test('sync pull returns modified records since timestamp', function (): void {
    $since = now()->subHour()->toIso8601String();

    $customer = Customer::factory()->create([
        'driver_id' => $this->driver->id,
        'created_by' => $this->user->id,
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/sync/pull?updated_since='.urlencode($since));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'routes',
                'route_stops',
                'customers',
                'customer_addresses',
                'customer_agreements',
                'products',
                'route_inventories',
                'server_time',
            ],
            'message',
        ]);

    expect(collect($response->json('data.customers'))->pluck('id'))->toContain($customer->id);
});

test('sync route-visits atomically persists visits, movements, sales, and payments with idempotency', function (): void {
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
        'street' => 'Mitre 500',
        'latitude' => -24.786,
        'longitude' => -65.412,
        'created_by' => $this->user->id,
        'is_primary' => true,
        'enabled' => true,
    ]);

    $stop = RouteStop::create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
        'enabled' => true,
    ]);

    RouteInventory::create([
        'route_id' => $route->id,
        'product_id' => $product->id,
        'current_quantity' => 10,
        'created_by' => $this->user->id,
        'enabled' => true,
    ]);

    $visitUuid = (string) Str::uuid();
    $cmUuid = (string) Str::uuid();
    $saleUuid = (string) Str::uuid();
    $payUuid = (string) Str::uuid();

    $payload = [
        'visits' => [
            [
                'uuid' => $visitUuid,
                'route_stop_id' => $stop->id,
                'customer_id' => $customer->id,
                'arrived_at' => now()->toIso8601String(),
                'latitude' => -24.786,
                'longitude' => -65.412,
                'status' => 'FINISHED',
                'notes' => 'Visit completed smoothly',
                'container_movements' => [
                    [
                        'uuid' => $cmUuid,
                        'container_type_id' => $containerType->id,
                        'quantity' => 2,
                        'direction' => 1,
                        'movement_type' => 'DELIVERED',
                    ],
                ],
                'sales' => [
                    [
                        'uuid' => $saleUuid,
                        'total' => 1500.00,
                        'is_credit_sale' => false,
                        'details' => [
                            [
                                'product_id' => $product->id,
                                'quantity' => 2,
                                'unit_price' => 750.00,
                            ],
                        ],
                    ],
                ],
                'payments' => [
                    [
                        'uuid' => $payUuid,
                        'amount' => 1500.00,
                        'payment_method_id' => $paymentMethod->id,
                        'sale_uuid' => $saleUuid,
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/sync/route-visits', $payload);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'processed_visits' => 1,
            ],
            'message' => 'Route visits synchronized successfully',
        ]);

    // Check DB persistence
    expect(Visit::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Sale::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Payment::where('customer_id', $customer->id)->count())->toBe(1);

    // Check container balance updated
    $balance = CustomerContainerBalance::where('customer_id', $customer->id)
        ->where('container_type_id', $containerType->id)
        ->first();
    expect($balance)->not->toBeNull();
    expect($balance->balance)->toBe(2);

    // Check truck inventory decremented
    $inventory = RouteInventory::where('route_id', $route->id)
        ->where('product_id', $product->id)
        ->first();
    expect((float) $inventory->current_quantity)->toBe(8.0);

    // Check idempotency records
    expect(IdempotencyRecord::where('uuid', $visitUuid)->exists())->toBeTrue();
    expect(IdempotencyRecord::where('uuid', $cmUuid)->exists())->toBeTrue();
    expect(IdempotencyRecord::where('uuid', $saleUuid)->exists())->toBeTrue();
    expect(IdempotencyRecord::where('uuid', $payUuid)->exists())->toBeTrue();

    // Replay exact same payload: should return 200 without duplicate insertions
    $replayResponse = $this->postJson('/api/v1/sync/route-visits', $payload);

    $replayResponse->assertOk()
        ->assertJson([
            'data' => [
                'processed_visits' => 0,
            ],
        ]);

    expect(Visit::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Sale::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Payment::where('customer_id', $customer->id)->count())->toBe(1);
    expect((float) $inventory->fresh()->current_quantity)->toBe(8.0);
});

test('sync route-visits rolls back database transaction on failure', function (): void {
    $visitUuid = (string) Str::uuid();

    // Customer 999999 does not exist, which will trigger a foreign key constraint violation in PostgreSQL
    $payload = [
        'visits' => [
            [
                'uuid' => $visitUuid,
                'customer_id' => 999999,
                'status' => 'FINISHED',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/sync/route-visits', $payload);

    $response->assertStatus(500);

    // Assert that nothing was persisted to idempotency_records or visits
    expect(IdempotencyRecord::where('uuid', $visitUuid)->exists())->toBeFalse();
    expect(Visit::where('customer_id', 999999)->exists())->toBeFalse();
});
