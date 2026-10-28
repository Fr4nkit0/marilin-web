<?php

namespace App\Catalog\Database\Factories;

use App\Catalog\Models\ContainerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerType>
 */
class ContainerTypeFactory extends Factory
{
    protected $model = ContainerType::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('CONTAINER_???'),
            'description' => fake()->sentence(3),
            'enabled' => true,
        ];
    }

    public function bidon20L(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'BIDON_20L',
            'description' => 'Bidón 20 Litros Retornable',
        ]);
    }

    public function sifon2L(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'SIFON_2L',
            'description' => 'Sifón Soda 2 Litros Retornable',
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
