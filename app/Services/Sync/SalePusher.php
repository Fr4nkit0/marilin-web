<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\Sale;
use App\Models\User;
use App\Models\Visit;
use App\Services\CustomerLedger;
use App\Support\ClientTime;

class SalePusher extends EntityPusher
{
    public function __construct(private readonly CustomerLedger $ledger) {}

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

        $reason = $this->reviewReasonForCustomer($customer, $driver);

        $sale = Sale::create([
            'client_uuid' => $record['uuid'],
            'customer_id' => $customer->id,
            'visit_id' => $visit?->id,
            'date' => ClientTime::parse($record['date']),
            'total' => $record['total'],
            'status' => 'confirmed',
            'is_credit_sale' => (bool) ($record['is_credit_sale'] ?? false),
            'notes' => $record['notes'] ?? null,
            'created_by' => $user->id,
            'enabled' => true,
            ...$this->reviewAttributes($reason),
        ]);

        foreach ($record['details'] as $detail) {
            $sale->details()->create([
                'product_id' => $detail['product_id'],
                'customer_agreement_id' => $detail['customer_agreement_id'] ?? null,
                'quantity' => $detail['quantity'],
                'unit_price' => $detail['unit_price'],
            ]);
        }

        $this->ledger->bookSale($customer, $sale, $user);
        $this->discountInventory($driver, $visit, $sale);

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

    /**
     * Takes the sold quantities out of the route stock: the route of the visit, or else the driver's route of the sale's day.
     */
    private function discountInventory(Driver $driver, ?Visit $visit, Sale $sale): void
    {
        $routeId = $visit?->routeStop?->route_id
            ?? Route::query()
                ->where('assigned_driver_id', $driver->id)
                ->whereDate('date', $sale->date->toDateString())
                ->value('id');

        if ($routeId === null) {
            return;
        }

        // Product id order, the same a truck load uses, so the two never lock rows in opposite orders.
        foreach ($sale->details->groupBy('product_id')->sortKeys() as $productId => $details) {
            RouteInventory::query()
                ->where('route_id', $routeId)
                ->where('product_id', $productId)
                ->decrement('current_quantity', (float) $details->sum('quantity'));
        }
    }
}
