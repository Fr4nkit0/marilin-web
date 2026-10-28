<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;

/**
 * A price agreement the driver closed on the street: it applies from the moment it is created, and the admin
 * sees it in the review inbox (accept-and-mark, ADR 0001) where it can be ended.
 */
class CustomerAgreementPusher extends EntityPusher
{
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules('customer', required: true),
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'unit_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        if ($replay = $this->alreadyPushed(CustomerAgreement::class, $record)) {
            return $replay;
        }

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');

        $agreement = CustomerAgreement::create([
            'client_uuid' => $record['uuid'],
            'customer_id' => $customer->id,
            'product_id' => $record['product_id'],
            'unit_price' => $record['unit_price'],
            'valid_from' => $record['valid_from'],
            'valid_to' => $record['valid_to'] ?? null,
            'reason' => $record['reason'] ?? null,
            'created_by' => $user->id,
            ...$this->reviewAttributes(ReviewReason::DriverAgreement),
        ]);

        return $this->accepted($record['uuid'], $agreement->id, ReviewReason::DriverAgreement->value);
    }
}
