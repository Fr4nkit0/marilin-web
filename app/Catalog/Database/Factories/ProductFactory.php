<?php

namespace App\Catalog\Database\Factories;

use App\Catalog\Models\ContainerType;
use App\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 100, 3000),
            'requires_container' => true,
            'container_type_id' => ContainerType::factory(),
            'available_quantity' => fake()->randomFloat(2, 10, 500),
            'enabled' => true,
        ];
    }

    public function noContainer(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_container' => false,
            'container_type_id' => null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
