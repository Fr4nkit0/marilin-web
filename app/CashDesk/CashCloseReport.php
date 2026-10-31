<?php

declare(strict_types=1);

namespace App\CashDesk;

use App\Accounts\Models\Driver;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * A driver's day in money and containers, with the records behind each figure.
 *
 * The figures and ownership rule come from DailyFigures; this class only shapes them for the close.
 */
class CashCloseReport
{
    public function __construct(private readonly DailyFigures $figures) {}

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

        $day = $this->figures->forDay($date, DayScope::driver($driver));
        $postClose = $this->postCloseSales($route, $day->sales);

        return [
            'routeId' => $route->id,
            'status' => $route->status,
            'totalSales' => $day->totalSales,
            'creditSales' => $day->creditSales,
            'cashAmount' => $day->cashAmount,
            'transferAmount' => $day->transferAmount,
            'cardAmount' => $day->cardAmount,
            'totalCollected' => $day->totalCollected,
            'debtCollected' => $day->debtCollected,
            'containersDelivered' => $day->containersDelivered,
            'containersReturned' => $day->containersReturned,
            'postCloseSales' => (float) $postClose->sum('total'),
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
                'cash' => $this->paymentRows($day->cashPayments),
                'transfer' => $this->paymentRows($day->transferPayments),
                'card' => $this->paymentRows($day->cardPayments),
                'credit' => $day->creditSaleRows->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'customer' => $sale->customer?->name,
                    'at' => $this->minute($sale->date),
                    'amount' => (float) $sale->total,
                    'origin' => $this->origin($sale->visit_id),
                ])->values()->all(),
                'postClose' => $postClose->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'customer' => $sale->customer?->name,
                    'at' => $this->minute($sale->date),
                    'amount' => (float) $sale->total,
                    'credit' => (bool) $sale->is_credit_sale,
                    'origin' => $this->origin($sale->visit_id),
                ])->values()->all(),
                'containers' => $day->containerMovements->map(fn (ContainerMovement $movement): array => [
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
     * The sales the driver made after the route was completed (S6): they still discount the route's stock, because the
     * truck still carries it, but the close shows them apart. "After" is judged by the sale's date, the moment the driver
     * sold on the phone, like the completion, which is the last visit's arrival; the moment the server received the sale
     * would make an offline sale made on the open route look late.
     *
     * @param  Collection<int, Sale>  $sales
     * @return Collection<int, Sale>
     */
    private function postCloseSales(DeliveryRoute $route, Collection $sales): Collection
    {
        if ($route->completed_at === null) {
            return new Collection;
        }

        return $sales->filter(fn (Sale $sale): bool => $sale->stock_route_id === $route->id && $sale->date->greaterThan($route->completed_at))->values();
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
