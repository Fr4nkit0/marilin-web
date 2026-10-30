<?php

namespace App\Customers\Customers\Database\Factories;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'street' => fake()->streetAddress(),
            'apartment' => fake()->optional()->buildingNumber(),
            'floor' => fake()->optional()->randomElement(['PB', '1', '2', '3B', '10']),
            'visual_reference' => fake()->optional()->sentence(),
            'neighborhood' => fake()->optional()->citySuffix(),
            'latitude' => fake()->latitude(-34.7, -34.5),
            'longitude' => fake()->longitude(-58.6, -58.3),
            'is_primary' => true,
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
