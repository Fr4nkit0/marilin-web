<?php

declare(strict_types=1);

namespace App\Services\CashClose;

use App\Enums\ContainerMovementType;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Models\ContainerMovement;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Route as DeliveryRoute;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * A driver's day in money and containers, with the records behind each figure.
 *
 * A record belongs to the driver when it was made on one of their visits, or, without a visit
 * (sold or collected from the customer's page in the app), when the driver recorded it.
 */
class CashCloseReport
{
    /**
     * @return array<string, mixed>|null null when the driver has no route that day
     */
    public function forDriver(Driver $driver, string $date): ?array
    {
        $route = DeliveryRoute::with(['inventories.product', 'inventoryLoads.product'])
            ->where('assigned_driver_id', $driver->id)
            ->whereDate('date', $date)
            ->first();

        if ($route === null) {
            return null;
        }

        $sales = $this->sales($driver, $date);
        $payments = $this->payments($driver, $date);
        $containers = $this->containerMovements($driver, $date);

        $cash = $payments->filter(fn (Payment $payment): bool => $payment->paymentMethod?->code === PaymentMethodCode::Cash->value);
        $transfers = $payments->filter(fn (Payment $payment): bool => $payment->paymentMethod?->code === PaymentMethodCode::Transfer->value);
        $credit = $sales->where('is_credit_sale', true);

        $cashAmount = (float) $cash->sum('amount');
        $transferAmount = (float) $transfers->sum('amount');

        return [
            'routeId' => $route->id,
            'status' => $route->status,
            'totalSales' => (float) $sales->sum('total'),
            'creditSales' => (float) $credit->sum('total'),
            'cashAmount' => $cashAmount,
            'transferAmount' => $transferAmount,
            'totalCollected' => $cashAmount + $transferAmount,
            'debtCollected' => (float) $payments->whereNull('sale_id')->sum('amount'),
            'containersDelivered' => (int) $containers->where('movement_type', ContainerMovementType::Delivered->value)->sum('quantity'),
            'containersReturned' => (int) $containers->where('movement_type', ContainerMovementType::Returned->value)->sum('quantity'),
            // The first load and every reload of a product add up to what the truck took out.
            'stockLoaded' => $route->inventoryLoads
                ->groupBy('product_id')
                ->map(fn ($loads): array => [
                    'product' => $loads->first()->product?->name,
                    'quantity' => (float) $loads->sum('quantity'),
                    'loads' => $loads->count(),
                ])
                ->values(),
            'stockRemaining' => $route->inventories->map(fn ($inventory): array => [
                'product' => $inventory->product?->name,
                'quantity' => (float) $inventory->current_quantity,
            ]),
            'details' => [
                'cash' => $this->paymentRows($cash),
                'transfer' => $this->paymentRows($transfers),
                'credit' => $credit->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'customer' => $sale->customer?->name,
                    'at' => $this->minute($sale->date),
                    'amount' => (float) $sale->total,
                    'origin' => $this->origin($sale->visit_id),
                ])->values()->all(),
                'containers' => $containers->map(fn (ContainerMovement $movement): array => [
                    'id' => $movement->id,
                    'customer' => $movement->customer?->name,
                    'at' => $this->minute($movement->created_at),
                    'container' => $movement->containerType?->description,
                    'movement' => $movement->movement_type,
                    'quantity' => (int) $movement->quantity,
                    'origin' => $this->origin($movement->visit_id),
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @return Collection<int, Sale>
     */
    private function sales(Driver $driver, string $date): Collection
    {
        return Sale::query()
            ->with('customer:id,name')
            ->where(fn (Builder $query) => $this->ownedBy($query, $driver, 'created_by'))
            ->whereDate('date', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    private function payments(Driver $driver, string $date): Collection
    {
        return Payment::query()
            ->with(['customer:id,name', 'paymentMethod:id,code'])
            ->where(fn (Builder $query) => $this->ownedBy($query, $driver, 'received_by'))
            ->whereDate('paid_at', $date)
            ->where('status', PaymentStatus::Confirmed->value)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ContainerMovement>
     */
    private function containerMovements(Driver $driver, string $date): Collection
    {
        return ContainerMovement::query()
            ->with(['customer:id,name', 'containerType:id,description'])
            ->where(fn (Builder $query) => $this->ownedBy($query, $driver, 'created_by'))
            ->whereDate('created_at', $date)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Made on one of the driver's visits, or recorded by the driver without a visit.
     *
     * @param  Builder<*>  $query
     */
    private function ownedBy(Builder $query, Driver $driver, string $recordedByColumn): void
    {
        $query->whereHas('visit', fn (Builder $visit) => $visit->where('driver_id', $driver->id))
            ->orWhere(fn (Builder $loose) => $loose
                ->whereNull('visit_id')
                ->where($recordedByColumn, $driver->user_id));
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function paymentRows(Collection $payments): array
    {
        return $payments->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            'customer' => $payment->customer?->name,
            'at' => $this->minute($payment->paid_at),
            'amount' => (float) $payment->amount,
            'concept' => $payment->sale_id === null ? 'Cobro de deuda' : "Venta #{$payment->sale_id}",
            'origin' => $this->origin($payment->visit_id),
        ])->values()->all();
    }

    private function minute(mixed $moment): ?string
    {
        return $moment === null ? null : Carbon::parse($moment)->format('Y-m-d H:i');
    }

    /**
     * Whether the record was made at a route stop or on its own from the customer's page.
     */
    private function origin(?int $visitId): string
    {
        return $visitId === null ? 'loose' : 'stop';
    }
}
