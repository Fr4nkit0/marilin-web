<?php

namespace Database\Factories;

use App\Catalog\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleDetail>
 */
class SaleDetailFactory extends Factory
{
    protected $model = SaleDetail::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 500, 2500);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'customer_agreement_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];
    }
}
