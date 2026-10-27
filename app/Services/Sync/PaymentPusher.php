<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Visit;
use App\Services\CustomerLedger;
use App\Support\ClientTime;

class PaymentPusher extends EntityPusher
{
    public function __construct(private readonly CustomerLedger $ledger) {}

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules('customer', required: true),
            ...$this->referenceRules('visit'),
            ...$this->referenceRules('sale'),
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        if ($replay = $this->alreadyPushed(Payment::class, $record)) {
            return $replay;
        }

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');
        $visit = $this->reference(Visit::class, $record, 'visit');
        $sale = $this->reference(Sale::class, $record, 'sale');
        $this->assertBelongsToCustomer($visit, $customer, 'visit');
        $this->assertBelongsToCustomer($sale, $customer, 'sale');

        $reason = $this->reviewReasonForCustomer($customer, $driver);

        $transaction = Transaction::create(['type' => 'IN', 'amount' => $record['amount']]);

        $payment = Payment::create([
            'client_uuid' => $record['uuid'],
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'visit_id' => $visit?->id,
            'sale_id' => $sale?->id,
            'payment_method_id' => $record['payment_method_id'],
            'amount' => $record['amount'],
            'received_by' => $user->id,
            'paid_at' => ClientTime::parse($record['paid_at']),
            'status' => PaymentStatus::Confirmed->value,
            ...$this->reviewAttributes($reason),
        ]);

        $this->ledger->bookPayment($customer, $payment, $user);

        return $this->accepted($record['uuid'], $payment->id, $reason?->value);
    }
}
