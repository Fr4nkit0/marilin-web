<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerBalance;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;

/**
 * The customer's current account: every sale or payment books one entry and moves the balance with it.
 * A positive balance means the customer owes money.
 */
class CustomerLedger
{
    public function bookSale(Customer $customer, Sale $sale, User $user): CustomerAccountEntry
    {
        return $this->book($customer, 'SALE', 1, (string) $sale->total, $user, sale: $sale);
    }

    public function bookPayment(Customer $customer, Payment $payment, User $user): CustomerAccountEntry
    {
        return $this->book($customer, 'PAYMENT', -1, (string) $payment->amount, $user, payment: $payment);
    }

    private function book(Customer $customer, string $type, int $direction, string $amount, User $user, ?Sale $sale = null, ?Payment $payment = null): CustomerAccountEntry
    {
        $entry = CustomerAccountEntry::create([
            'customer_id' => $customer->id,
            'entry_type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'sale_id' => $sale?->id,
            'payment_id' => $payment?->id,
            'created_by' => $user->id,
        ]);

        // The row has to exist before the atomic increment so two first entries of a customer cannot race.
        CustomerBalance::query()->insertOrIgnore([
            'customer_id' => $customer->id,
            'current_balance' => 0,
            'created_by' => $user->id,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->increment('current_balance', ($direction < 0 ? '-' : '').$amount, [
                'last_entry_id' => $entry->id,
                'last_modified_by' => $user->id,
            ]);

        return $entry;
    }
}
