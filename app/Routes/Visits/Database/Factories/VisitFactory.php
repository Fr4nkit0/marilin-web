<?php

namespace App\Routes\Visits\Database\Factories;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'route_stop_id' => RouteStop::factory(),
            'customer_id' => Customer::factory(),
            'driver_id' => Driver::factory(),
            'outcome_id' => null,
            'arrived_at' => now(),
            'latitude' => fake()->latitude(-34.7, -34.5),
            'longitude' => fake()->longitude(-58.6, -58.3),
            'altitude' => fake()->randomFloat(2, 0, 100),
            'status' => 'PENDING',
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'FINISHED',
            'outcome_id' => VisitOutcome::factory(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'IN_PROGRESS',
        ]);
    }
}
