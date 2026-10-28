<?php

namespace App\Routes\Visits\Database\Factories;

use App\Accounts\Models\User;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitStatusHistory;
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
