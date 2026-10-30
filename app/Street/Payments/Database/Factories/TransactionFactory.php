<?php

namespace App\Street\Payments\Database\Factories;

use App\Street\Payments\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'type' => 'IN',
            'amount' => fake()->randomFloat(2, 500, 10000),
        ];
    }

    public function in(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'IN',
        ]);
    }

    public function out(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'OUT',
        ]);
    }
}
