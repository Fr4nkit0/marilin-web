<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\User;
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
