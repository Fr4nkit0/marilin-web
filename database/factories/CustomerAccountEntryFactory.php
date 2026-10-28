<?php

namespace Database\Factories;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Models\CustomerAccountEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccountEntry>
 */
class CustomerAccountEntryFactory extends Factory
{
    protected $model = CustomerAccountEntry::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'entry_type' => 'SALE',
            'direction' => 1,
            'amount' => fake()->randomFloat(2, 500, 10000),
            'sale_id' => null,
            'payment_id' => null,
            'description' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function saleEntry(): static
    {
        return $this->state(fn (array $attributes) => [
            'entry_type' => 'SALE',
            'direction' => 1,
        ]);
    }

    public function paymentEntry(): static
    {
        return $this->state(fn (array $attributes) => [
            'entry_type' => 'PAYMENT',
            'direction' => -1,
        ]);
    }
}
