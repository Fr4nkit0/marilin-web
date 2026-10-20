<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Visit;
use App\Services\CustomerLedger;
use Illuminate\Support\Carbon;

class PaymentPusher extends EntityPusher
{
    public function __construct(private readonly CustomerLedger $ledger) {}

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', 'required_without:customer_uuid'],
            'customer_uuid' => ['nullable', 'uuid', 'required_without:customer_id'],
            'visit_id' => ['nullable', 'integer'],
            'visit_uuid' => ['nullable', 'uuid'],
            'sale_id' => ['nullable', 'integer'],
            'sale_uuid' => ['nullable', 'uuid'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $existing = Payment::query()->where('client_uuid', $record['uuid'])->first();

        if ($existing !== null) {
            return $this->accepted($record['uuid'], $existing->id, $existing->needs_review ? $existing->review_reason : null);
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
            'paid_at' => Carbon::parse($record['paid_at']),
            'status' => 'CONFIRMED',
            'needs_review' => $reason !== null,
            'review_reason' => $reason?->value,
        ]);

        $this->ledger->bookPayment($customer, $payment, $user);

        return $this->accepted($record['uuid'], $payment->id, $reason?->value);
    }
}
