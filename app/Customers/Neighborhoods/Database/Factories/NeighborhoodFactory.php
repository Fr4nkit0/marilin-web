<?php

namespace App\Customers\Neighborhoods\Database\Factories;

use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Neighborhood>
 */
class NeighborhoodFactory extends Factory
{
    protected $model = Neighborhood::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName(),
            'enabled' => true,
        ];
    }
}
