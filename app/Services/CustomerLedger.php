<?php

declare(strict_types=1);

namespace App\Services;

use App\Accounts\Models\User;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerBalance;
use App\Models\Payment;
use App\Models\Sale;

/**
 * The customer's current account: every sale or payment books one entry and moves the balance with it.
 * A positive balance means the customer owes money.
 *
 * @internal Owned by StreetOperation and CustomerIntake, the only writers of the ledger.
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

    /**
     * Opens the account of a new customer: the initial debt is one adjustment entry, none when there is no debt.
     * The balance row exists either way.
     */
    public function openAccount(Customer $customer, string $initialDebt, User $user): void
    {
        if (bccomp($initialDebt, '0', 2) > 0) {
            $this->book($customer, 'ADJUSTMENT', 1, $initialDebt, $user, description: 'Saldo deudor inicial registrado al dar de alta');

            return;
        }

        $this->ensureBalance($customer, $user);
    }

    private function book(Customer $customer, string $type, int $direction, string $amount, User $user, ?Sale $sale = null, ?Payment $payment = null, ?string $description = null): CustomerAccountEntry
    {
        $entry = CustomerAccountEntry::create([
            'customer_id' => $customer->id,
            'entry_type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'sale_id' => $sale?->id,
            'payment_id' => $payment?->id,
            'description' => $description,
            'created_by' => $user->id,
        ]);

        $this->ensureBalance($customer, $user);

        CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->increment('current_balance', ($direction < 0 ? '-' : '').$amount, [
                'last_entry_id' => $entry->id,
                'last_modified_by' => $user->id,
            ]);

        return $entry;
    }

    /**
     * The row has to exist before the atomic increment so two first entries of a customer cannot race.
     */
    private function ensureBalance(Customer $customer, User $user): void
    {
        CustomerBalance::query()->insertOrIgnore([
            'customer_id' => $customer->id,
            'current_balance' => 0,
            'created_by' => $user->id,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
