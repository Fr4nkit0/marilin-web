<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
});

test('the products page lists every product with its list price', function (): void {
    $water = Product::factory()->create(['name' => 'Agua 20L', 'price' => 1500]);
    $disabled = Product::factory()->disabled()->create(['name' => 'Dispenser', 'price' => 0]);

    $this->actingAs($this->admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Products')
            ->has('products', 2)
            ->where('products.0.id', $water->id)
            ->where('products.0.price', '1500.00')
            ->where('products.1.id', $disabled->id)
            ->where('products.1.enabled', false));
});

test('an admin sets the list price of a product', function (): void {
    $product = Product::factory()->create(['price' => 0]);

    $this->actingAs($this->admin)
        ->patch(route('products.price', $product), ['price' => '1250.50'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($product->fresh()->price)->toBe('1250.50');
});

test('the list price cannot be negative or missing', function (mixed $price): void {
    $product = Product::factory()->create(['price' => 900]);

    $this->actingAs($this->admin)
        ->patch(route('products.price', $product), ['price' => $price])
        ->assertSessionHasErrors('price');

    expect($product->fresh()->price)->toBe('900.00');
})->with([-1, '', 'abc']);

test('only an admin can see or change list prices', function (): void {
    $driver = User::factory()->create(['is_super_user' => false]);
    $product = Product::factory()->create(['price' => 900]);

    $this->actingAs($driver)->get(route('products.index'))->assertForbidden();
    $this->actingAs($driver)->patch(route('products.price', $product), ['price' => 1])->assertForbidden();

    expect($product->fresh()->price)->toBe('900.00');
});
