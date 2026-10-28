<?php

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;

test('container type has products and container movements', function () {
    $containerType = ContainerType::factory()->bidon20L()->create();

    $product = Product::factory()->create([
        'container_type_id' => $containerType->id,
        'requires_container' => true,
    ]);

    expect($containerType->products)->toHaveCount(1);
    expect($containerType->products->first()->id)->toBe($product->id);
    expect($product->containerType->id)->toBe($containerType->id);
});

test('container movement tracks delivered and returned containers', function () {
    $customer = Customer::factory()->create();
    $containerType = ContainerType::factory()->bidon20L()->create();
    $user = User::factory()->create();

    $movement = ContainerMovement::factory()->delivered()->create([
        'customer_id' => $customer->id,
        'container_type_id' => $containerType->id,
        'quantity' => 5,
        'created_by' => $user->id,
    ]);

    expect($movement->movement_type)->toBe('DELIVERED');
    expect($movement->direction)->toBe(1);
    expect($movement->customer->id)->toBe($customer->id);
    expect($movement->containerType->id)->toBe($containerType->id);
    expect($movement->creator->id)->toBe($user->id);

    expect(ContainerMovement::ofType('DELIVERED')->count())->toBe(1);
});

test('customer container balance links customer, container type and last movement', function () {
    $customer = Customer::factory()->create();
    $containerType = ContainerType::factory()->bidon20L()->create();
    $movement = ContainerMovement::factory()->delivered()->create([
        'customer_id' => $customer->id,
        'container_type_id' => $containerType->id,
    ]);

    $balance = CustomerContainerBalance::factory()->create([
        'customer_id' => $customer->id,
        'container_type_id' => $containerType->id,
        'last_movement_id' => $movement->id,
        'balance' => 5,
    ]);

    expect($balance->customer->id)->toBe($customer->id);
    expect($balance->containerType->id)->toBe($containerType->id);
    expect($balance->lastMovement->id)->toBe($movement->id);
    expect($balance->balance)->toBe(5);
});

test('route inventory and loads manage truck product stock', function () {
    $route = Route::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $load = RouteInventoryLoad::factory()->create([
        'route_id' => $route->id,
        'product_id' => $product->id,
        'quantity' => 30.00,
        'created_by' => $user->id,
    ]);

    $inventory = RouteInventory::factory()->create([
        'route_id' => $route->id,
        'product_id' => $product->id,
        'current_quantity' => 30.00,
        'created_by' => $user->id,
    ]);

    expect($inventory->route->id)->toBe($route->id);
    expect($inventory->product->id)->toBe($product->id);
    expect($load->route->id)->toBe($route->id);
    expect($load->product->id)->toBe($product->id);
    expect($route->inventories)->toHaveCount(1);
    expect($route->inventoryLoads)->toHaveCount(1);
});
