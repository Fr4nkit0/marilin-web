<?php

namespace Database\Factories;

use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerContainerBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerContainerBalance>
 */
class CustomerContainerBalanceFactory extends Factory
{
    protected $model = CustomerContainerBalance::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'container_type_id' => ContainerType::factory(),
            'last_movement_id' => null,
            'balance' => fake()->numberBetween(0, 20),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }
}
