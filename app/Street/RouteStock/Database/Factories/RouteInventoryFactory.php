<?php

namespace App\Street\RouteStock\Database\Factories;

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Routes\Routes\Models\Route;
use App\Street\RouteStock\Models\RouteInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteInventory>
 */
class RouteInventoryFactory extends Factory
{
    protected $model = RouteInventory::class;

    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'product_id' => Product::factory(),
            'current_quantity' => fake()->randomFloat(2, 5, 100),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }
}
