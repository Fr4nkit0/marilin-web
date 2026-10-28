<?php

namespace App\Street\Sales\Database\Factories;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'visit_id' => null,
            'date' => now(),
            'total' => fake()->randomFloat(2, 500, 15000),
            'status' => 'confirmed',
            'is_credit_sale' => false,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }

    public function creditSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_credit_sale' => true,
        ]);
    }
}
