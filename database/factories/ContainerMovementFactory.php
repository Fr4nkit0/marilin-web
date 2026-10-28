<?php

namespace Database\Factories;

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Models\ContainerMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerMovement>
 */
class ContainerMovementFactory extends Factory
{
    protected $model = ContainerMovement::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'container_type_id' => ContainerType::factory(),
            'visit_id' => null,
            'movement_type' => 'DELIVERED',
            'quantity' => fake()->numberBetween(1, 10),
            'direction' => 1,
            'reason' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'DELIVERED',
            'direction' => 1,
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'RETURNED',
            'direction' => -1,
        ]);
    }
}
