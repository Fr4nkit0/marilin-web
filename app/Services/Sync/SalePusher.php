<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\CustomerAgreement;
use App\Models\Driver;
use App\Models\Sale;
use App\Models\User;
use App\Models\Visit;
use App\Operations\RecordSale;
use App\Operations\SaleLine;
use App\Operations\StreetOperation;
use App\Support\ClientTime;

class SalePusher extends EntityPusher
{
    public function __construct(private readonly StreetOperation $operation) {}

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules('customer', required: true),
            ...$this->referenceRules('visit'),
            'date' => ['required', 'date'],
            'total' => ['required', 'numeric', 'min:0'],
            'is_credit_sale' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'details.*.customer_agreement_id' => ['nullable', 'integer', 'exists:customer_agreements,id'],
            'details.*.customer_agreement_uuid' => ['nullable', 'uuid'],
            'details.*.quantity' => ['required', 'integer', 'gt:0'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        if ($replay = $this->alreadyPushed(Sale::class, $record)) {
            return $replay;
        }

        $this->assertTotalMatchesDetails($record);

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');
        $visit = $this->reference(Visit::class, $record, 'visit');
        $this->assertBelongsToCustomer($visit, $customer, 'visit');

        $agreements = array_map(function (array $detail) use ($customer): ?CustomerAgreement {
            $agreement = $this->reference(CustomerAgreement::class, $detail, 'customer_agreement');
            $this->assertBelongsToCustomer($agreement, $customer, 'customer agreement');

            return $agreement;
        }, $record['details']);

        $reason = $this->reviewReasonForCustomer($customer, $driver);

        $sale = $this->operation->recordSale(new RecordSale(
            customer: $customer,
            visit: $visit,
            driver: $driver,
            date: ClientTime::parse($record['date']),
            // Validation accepts numeric strings and keyed details, so the adapter casts what the module types strictly.
            lines: array_map(fn (array $detail, int|string $index): SaleLine => new SaleLine(
                (int) $detail['product_id'],
                (int) $detail['quantity'],
                $detail['unit_price'],
                $agreements[$index],
            ), $record['details'], array_keys($record['details'])),
            total: $record['total'],
            isCreditSale: (bool) ($record['is_credit_sale'] ?? false),
            notes: $record['notes'] ?? null,
            recordedBy: $user,
            clientUuid: $record['uuid'],
            reviewReason: $reason,
        ));

        return $this->accepted($record['uuid'], $sale->id, $reason?->value);
    }

    /**
     * The ledger books the total as the app sent it, so a total that does not add up would leave the customer's account wrong.
     *
     * @param  array<string, mixed>  $record
     */
    private function assertTotalMatchesDetails(array $record): void
    {
        $sum = array_sum(array_map(
            fn (array $detail): float => (float) $detail['quantity'] * (float) $detail['unit_price'],
            $record['details'],
        ));

        if (abs($sum - (float) $record['total']) > 0.005) {
            throw new RejectedRecord('The total does not match the sum of the sale lines.');
        }
    }
}
