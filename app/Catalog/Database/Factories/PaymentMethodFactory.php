<?php

namespace App\Catalog\Database\Factories;

use App\Catalog\Enums\PaymentMethodCode;
use App\Catalog\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('METHOD_???'),
            'name' => fake()->words(2, true),
            'enabled' => true,
        ];
    }

    public function efectivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => PaymentMethodCode::Cash->value,
            'name' => 'Efectivo',
        ]);
    }

    public function transferencia(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => PaymentMethodCode::Transfer->value,
            'name' => 'Transferencia Bancaria',
        ]);
    }

    public function tarjeta(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => PaymentMethodCode::Card->value,
            'name' => 'Tarjeta Débito / Crédito',
        ]);
    }
}
