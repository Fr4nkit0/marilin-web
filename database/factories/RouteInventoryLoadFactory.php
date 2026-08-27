<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Route;
use App\Models\RouteInventoryLoad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteInventoryLoad>
 */
class RouteInventoryLoadFactory extends Factory
{
    protected $model = RouteInventoryLoad::class;

    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(2, 10, 100),
            'loaded_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
