<?php

namespace Database\Factories;

use App\Accounts\Models\User;
use App\Models\Customer;
use App\Models\CustomerPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPreference>
 */
class CustomerPreferenceFactory extends Factory
{
    protected $model = CustomerPreference::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'time_window_start' => '09:00:00',
            'time_window_end' => '12:00:00',
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
