<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'repartidor_id' => Repartidor::factory(),
            'name' => fake()->name(),
            'phone_number' => fake()->phoneNumber(),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
