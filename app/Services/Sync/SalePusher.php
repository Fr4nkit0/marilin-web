<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Agreements\PriceResolver;
use App\Customers\Customers\Models\Customer;
use App\Models\Sale;
use App\Operations\RecordSale;
use App\Operations\SaleLine;
use App\Operations\StreetOperation;
use App\Review\ReviewReason;
use App\Routes\Visits\Models\Visit;
use App\Shared\ClientTime;
use Illuminate\Support\Carbon;

class SalePusher extends EntityPusher
{
    public function __construct(
        private readonly StreetOperation $operation,
        private readonly PriceResolver $prices,
    ) {}

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

        $date = ClientTime::parse($record['date']);
        $reason = $this->reviewReasonForCustomer($customer, $driver)
            ?? ($this->chargesAnotherPrice($customer, $record['details'], $date) ? ReviewReason::PriceMismatch : null);

        $sale = $this->operation->recordSale(new RecordSale(
            customer: $customer,
            visit: $visit,
            driver: $driver,
            date: $date,
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
     * Accept-and-mark (ADR 0001): the sale keeps the prices the app sent, but a line that is not charged what the server resolves for that customer, product and day (agreement valid that day, else list price) needs the admin's eyes.
     *
     * @param  array<int|string, array<string, mixed>>  $details
     */
    private function chargesAnotherPrice(Customer $customer, array $details, Carbon $date): bool
    {
        $products = Product::query()->whereIn('id', array_map(fn (array $detail): int => (int) $detail['product_id'], $details))->get()->keyBy('id');

        foreach ($details as $detail) {
            $expected = $this->prices->priceFor($customer, $products[(int) $detail['product_id']], $date)->unitPrice;

            if ($this->twoDecimals($detail['unit_price']) !== $this->twoDecimals($expected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validation accepts any numeric input (numeric strings, exponent notation), so it is normalized before comparing.
     */
    private function twoDecimals(string|int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
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
