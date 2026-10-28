<?php

namespace App\Street\Containers\Database\Factories;

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Street\Containers\Models\CustomerContainerBalance;
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
