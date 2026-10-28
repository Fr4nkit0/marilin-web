<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Operations\RecordPayment;
use App\Operations\StreetOperation;
use App\Routes\Visits\Models\Visit;
use App\Shared\ClientTime;

class PaymentPusher extends EntityPusher
{
    public function __construct(private readonly StreetOperation $operation) {}

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

        $payment = $this->operation->recordPayment(new RecordPayment(
            customer: $customer,
            visit: $visit,
            sale: $sale,
            paymentMethodId: (int) $record['payment_method_id'],
            amount: $record['amount'],
            paidAt: ClientTime::parse($record['paid_at']),
            receivedBy: $user,
            clientUuid: $record['uuid'],
            reviewReason: $reason,
        ));

        return $this->accepted($record['uuid'], $payment->id, $reason?->value);
    }
}
