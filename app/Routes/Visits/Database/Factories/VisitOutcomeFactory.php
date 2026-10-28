<?php

namespace App\Routes\Visits\Database\Factories;

use App\Routes\Visits\Models\VisitOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitOutcome>
 */
class VisitOutcomeFactory extends Factory
{
    protected $model = VisitOutcome::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'triggers_reschedule' => false,
            'enabled' => true,
        ];
    }

    public function triggersReschedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggers_reschedule' => true,
        ]);
    }
}
