<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
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
