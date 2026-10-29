<?php

declare(strict_types=1);

namespace App\Street\Ledger;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;

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
     * Reverses a cancelled sale: a credit note for its whole total, linked to the sale.
     */
    public function bookSaleCancellation(Customer $customer, Sale $sale, User $user, ?string $reason): CustomerAccountEntry
    {
        return $this->book($customer, 'CREDIT_NOTE', -1, (string) $sale->total, $user, sale: $sale, description: $this->withReason('Venta anulada por el repartidor', $reason));
    }

    /**
     * Reverses a cancelled payment: an adjustment that puts its amount back on the account, linked to the payment.
     */
    public function bookPaymentCancellation(Customer $customer, Payment $payment, User $user, ?string $reason): CustomerAccountEntry
    {
        return $this->book($customer, 'ADJUSTMENT', 1, (string) $payment->amount, $user, payment: $payment, description: $this->withReason('Cobro anulado por el repartidor', $reason));
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

    private function withReason(string $description, ?string $reason): string
    {
        return $reason === null || trim($reason) === '' ? $description : "{$description}: {$reason}";
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
