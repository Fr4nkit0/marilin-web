<?php

namespace Database\Factories;

use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repartidor>
 */
class RepartidorFactory extends Factory
{
    protected $model = Repartidor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
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
