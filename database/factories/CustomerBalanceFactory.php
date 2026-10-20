<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBalance>
 */
class CustomerBalanceFactory extends Factory
{
    protected $model = CustomerBalance::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'current_balance' => fake()->randomFloat(2, 0, 5000),
            'last_entry_id' => null,
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }
}
