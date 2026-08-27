<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAgreement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAgreement>
 */
class CustomerAgreementFactory extends Factory
{
    protected $model = CustomerAgreement::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'unit_price' => fake()->randomFloat(2, 500, 3000),
            'valid_from' => now()->subMonths(3)->toDateString(),
            'valid_to' => null,
            'reason' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
