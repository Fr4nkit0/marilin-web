<?php

namespace App\Routes\ScheduledVisits\Database\Factories;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledVisit>
 */
class ScheduledVisitFactory extends Factory
{
    protected $model = ScheduledVisit::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => null,
            'note' => fake()->optional()->sentence(),
            'status' => 'SCHEDULED',
            'route_stop_id' => null,
            'created_stop' => false,
            'created_by' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CANCELLED',
            'cancelled_by' => User::factory(),
            'cancelled_at' => now(),
        ]);
    }
}
