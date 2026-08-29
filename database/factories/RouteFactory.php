<?php

namespace Database\Factories;

use App\Models\Repartidor;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'assigned_driver_id' => Driver::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'status' => 'PLANNED',
            'started_at' => null,
            'completed_at' => null,
            'enabled' => true,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'IN_PROGRESS',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'COMPLETED',
            'started_at' => now()->subHours(4),
            'completed_at' => now(),
        ]);
    }
}
