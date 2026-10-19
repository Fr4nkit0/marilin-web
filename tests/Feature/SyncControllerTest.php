<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('syncCustomers creates new customers and addresses with neighborhood', function () {
    $user = User::factory()->create();
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    Sanctum::actingAs($user);

    $uuid = Str::uuid()->toString();

    $response = $this->postJson(route('api.v1.sync.customers'), [
        'customers' => [
            [
                'uuid' => $uuid,
                'name' => 'John Doe',
                'phone_number' => '123456789',
                'address' => [
                    'street' => '123 Main St',
                    'apartment' => '4B',
                    'floor' => 4,
                    'visual_reference' => 'Near the park',
                    'neighborhood' => 'Downtown',
                    'latitude' => -34.6037,
                    'longitude' => -58.3816,
                ],
            ],
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.processed_customers', 1);

    $this->assertDatabaseHas('customers', [
        'name' => 'John Doe',
        'driver_id' => $driver->id,
    ]);

    $this->assertDatabaseHas('customer_addresses', [
        'street' => '123 Main St',
        'neighborhood' => 'Downtown',
        'is_primary' => true,
    ]);
});

test('syncCustomers is idempotent', function () {
    $user = User::factory()->create();
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    Sanctum::actingAs($user);

    $uuid = Str::uuid()->toString();

    $payload = [
        'customers' => [
            [
                'uuid' => $uuid,
                'name' => 'Jane Doe',
                'address' => [
                    'street' => '456 Oak St',
                    'neighborhood' => 'Uptown',
                ],
            ],
        ],
    ];

    $response1 = $this->postJson(route('api.v1.sync.customers'), $payload);
    $response1->assertStatus(200);

    $response2 = $this->postJson(route('api.v1.sync.customers'), $payload);
    $response2->assertStatus(200);
    $response2->assertJsonPath('data.processed_customers', 0); // Second time it should skip

    $this->assertEquals(1, Customer::where('name', 'Jane Doe')->count());
});

test('bootstrap includes customer addresses with neighborhood', function () {
    $user = User::factory()->create();
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    Sanctum::actingAs($user);

    $customer = Customer::factory()->create(['driver_id' => $driver->id, 'enabled' => true]);
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'neighborhood' => 'Test Neighborhood',
        'enabled' => true,
    ]);

    $response = $this->getJson(route('api.v1.sync.bootstrap'));
    $response->assertStatus(200);

    $response->assertJsonFragment([
        'neighborhood' => 'Test Neighborhood',
    ]);
});
