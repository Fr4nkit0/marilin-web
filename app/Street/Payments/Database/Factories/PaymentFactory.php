<?php

namespace App\Street\Payments\Database\Factories;

use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Street\Payments\Models\Payment;
use App\Street\Payments\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 500, 10000);

        return [
            'transaction_id' => Transaction::factory(['amount' => $amount, 'type' => 'IN']),
            'customer_id' => Customer::factory(),
            'visit_id' => null,
            'sale_id' => null,
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => $amount,
            'received_by' => User::factory(),
            'paid_at' => now(),
            'status' => 'CONFIRMED',
        ];
    }
}
