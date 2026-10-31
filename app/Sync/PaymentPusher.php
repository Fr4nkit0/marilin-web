<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Visits\Models\Visit;
use App\Shared\ClientTime;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\Payments\RecordPayment;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;

class PaymentPusher extends EntityPusher
{
    /**
     * How far ahead of the server's clock a payment date may be: the phone's clock drifts, a payment is never made in the future.
     */
    private const int FUTURE_TOLERANCE_MINUTES = 5;

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

        $paidAt = ClientTime::parse($record['paid_at']);

        if ($paidAt->greaterThan(now()->addMinutes(self::FUTURE_TOLERANCE_MINUTES))) {
            throw new RejectedRecord('The payment date is in the future.');
        }

        $reasons = [
            $this->reviewReasonForCustomer($customer, $driver),
            $this->exceedsDebt($customer, (string) $record['amount']) ? ReviewReason::PaymentExceedsDebt : null,
        ];

        $payment = $this->operation->recordPayment(new RecordPayment(
            customer: $customer,
            visit: $visit,
            sale: $sale,
            paymentMethodId: (int) $record['payment_method_id'],
            amount: $record['amount'],
            paidAt: $paidAt,
            receivedBy: $user,
            clientUuid: $record['uuid'],
            reviewReasons: $reasons,
        ));

        return $this->accepted($record['uuid'], $payment->id, ReviewReason::values(...$reasons));
    }

    /**
     * Accept-and-mark (ADR 0001): a payment larger than what the customer owes before it (never less than zero) is kept,
     * but it is likely a typo and needs the admin's eyes. The balance already carries the day's sales, which a push
     * books before its payments, so they are not added again.
     */
    private function exceedsDebt(Customer $customer, string $amount): bool
    {
        $balance = (string) (CustomerBalance::query()->where('customer_id', $customer->id)->value('current_balance') ?? '0');
        $debt = bccomp($balance, '0', 2) > 0 ? $balance : '0';

        return bccomp($this->twoDecimals($amount), $debt, 2) > 0;
    }

    /**
     * Validation accepts any numeric input (numeric strings, exponent notation), so it is normalized before comparing.
     */
    private function twoDecimals(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
