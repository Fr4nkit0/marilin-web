<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Visit;
use App\Models\VisitOutcome;
use App\Models\VisitStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitStatusHistory>
 */
class VisitStatusHistoryFactory extends Factory
{
    protected $model = VisitStatusHistory::class;

    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'status' => 'IN_PROGRESS',
            'outcome_id' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'last_modified_by' => null,
            'enabled' => true,
        ];
    }
}
