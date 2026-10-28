<?php

namespace App\Actions;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Enums\PaymentMethodCode;
use App\Enums\ContainerMovementType;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\Sale;
use App\Operations\DailyFigures\DailyFigures;
use App\Operations\DailyFigures\DayFigures;
use App\Operations\DailyFigures\DayScope;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Routes\Routes\Models\RouteStop;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class GetDailyDashboardMetricsAction
{
    /**
     * A driver on route whose app has not reached the server for longer than this is flagged.
     */
    private const int STALE_SYNC_MINUTES = 30;

    public function __construct(private readonly DailyFigures $figures) {}

    /**
     * Execute the action to get aggregated KPIs for the given date.
     *
     * @return array<string, mixed>
     */
    public function execute(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        // 1. Day figures: every driver plus the house (Venta/Cobro en casa)
        $day = $this->figures->forDay($targetDate, DayScope::all());
        $totalSales = $day->totalSales;
        $yesterdaySales = $this->figures->forDay(Carbon::parse($targetDate)->subDay()->toDateString(), DayScope::all())->totalSales;
        $salesDelta = $yesterdaySales > 0
            ? round((($totalSales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : 0;

        // 3. Route progress and stops
        $routes = DeliveryRoute::with(['stops.visit', 'stops.rescheduledTo', 'stops.customer:id,name', 'driver.user'])
            ->whereDate('date', $targetDate)
            ->get();
        $totalStops = 0;
        $completedStops = 0;

        $repartidoresStatus = [];

        foreach ($routes as $route) {
            $routeStopsCount = $route->stops->count();
            $routeCompletedCount = $route->stops->filter(fn (RouteStop $stop) => $stop->isDone())->count();

            $totalStops += $routeStopsCount;
            $completedStops += $routeCompletedCount;

            $repartidoresStatus[] = [
                'id' => $route->driver?->id ?? $route->id,
                'name' => $route->driver?->user?->name ?? 'Chofer sin asignar',
                'status' => $route->status, // PLANNED, IN_PROGRESS, COMPLETED
                'totalStops' => $routeStopsCount,
                'completedStops' => $routeCompletedCount,
                'progressPercent' => $routeStopsCount > 0 ? round(($routeCompletedCount / $routeStopsCount) * 100) : 0,
                ...$this->money($route->driver === null ? null : $this->figures->forDay($targetDate, DayScope::driver($route->driver))),
                'startedAt' => $route->started_at?->format('H:i'),
                'completedAt' => $route->completed_at?->format('H:i'),
            ];
        }

        return [
            'date' => $targetDate,
            'kpis' => [
                'totalSales' => $totalSales,
                'salesDelta' => $salesDelta,
                'totalCollected' => $day->totalCollected,
                'cashPayments' => $day->cashAmount,
                'transferPayments' => $day->transferAmount,
                'totalStops' => $totalStops,
                'completedStops' => $completedStops,
                'stopsProgressPercent' => $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0,
                'containersDelivered' => $day->containersDelivered,
                'containersReturned' => $day->containersReturned,
            ],
            'repartidores' => $repartidoresStatus,
            'casa' => ['name' => 'Casa', ...$this->money($this->figures->forDay($targetDate, DayScope::house()))],
            'details' => $this->details($day, $routes),
            'sync' => $this->syncStatus(),
        ];
    }

    /**
     * @return array{totalSales: float, totalCollected: float}
     */
    private function money(?DayFigures $figures): array
    {
        return [
            'totalSales' => $figures?->totalSales ?? 0.0,
            'totalCollected' => $figures?->totalCollected ?? 0.0,
        ];
    }

    /**
     * Each enabled driver's last contact from the app (any API call with their token), as of now.
     *
     * @return array<int, array{id: int, name: string, lastSyncAt: string|null, onRoute: bool, stale: bool}>
     */
    private function syncStatus(): array
    {
        $drivers = Driver::with('user:id,name')->where('enabled', true)->get();

        $lastUsed = PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $drivers->pluck('user_id'))
            ->groupBy('tokenable_id')
            ->selectRaw('tokenable_id, max(last_used_at) as last_used_at')
            ->pluck('last_used_at', 'tokenable_id');

        $onRoute = DeliveryRoute::query()
            ->whereDate('date', Carbon::today()->toDateString())
            ->where('status', RouteStatus::InProgress->value)
            ->pluck('assigned_driver_id')
            ->flip();

        return $drivers
            ->map(function (Driver $driver) use ($lastUsed, $onRoute): array {
                $last = $lastUsed[$driver->user_id] ?? null;
                $lastAt = $last === null ? null : Carbon::parse($last);
                $isOnRoute = $onRoute->has($driver->id);

                return [
                    'id' => $driver->id,
                    'name' => $driver->user?->name ?? 'Repartidor',
                    'lastSyncAt' => $lastAt?->toIso8601String(),
                    'onRoute' => $isOnRoute,
                    'stale' => $isOnRoute && ($lastAt === null || $lastAt->lt(now()->subMinutes(self::STALE_SYNC_MINUTES))),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * The records behind each KPI card, in the order they happened.
     *
     * @param  Collection<int, DeliveryRoute>  $routes
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function details(DayFigures $day, Collection $routes): array
    {
        $minute = fn ($moment): ?string => $moment === null ? null : Carbon::parse($moment)->format('Y-m-d H:i');

        return [
            'sales' => $day->sales
                ->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'customer' => $sale->customer?->name,
                    'driver' => $sale->creator?->name,
                    'at' => $minute($sale->date),
                    'amount' => (float) $sale->total,
                    'credit' => (bool) $sale->is_credit_sale,
                ])
                ->all(),
            'payments' => $day->payments
                ->filter(fn (Payment $payment): bool => in_array($payment->paymentMethod?->code, [PaymentMethodCode::Cash->value, PaymentMethodCode::Transfer->value], true))
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'customer' => $payment->customer?->name,
                    'driver' => $payment->recipient?->name,
                    'at' => $minute($payment->paid_at),
                    'method' => $payment->paymentMethod?->code,
                    'amount' => (float) $payment->amount,
                ])
                ->values()
                ->all(),
            'stops' => $routes
                ->flatMap(fn (DeliveryRoute $route) => $route->stops->map(fn (RouteStop $stop): array => [
                    'id' => $stop->id,
                    'driver' => $route->driver?->user?->name,
                    'customer' => $stop->customer?->name,
                    'time' => $stop->scheduled_time === null ? null : substr((string) $stop->scheduled_time, 0, 5),
                    'status' => $stop->effectiveStatus()->value,
                    'done' => $stop->isDone(),
                ]))
                ->sortBy([['driver', 'asc'], ['time', 'asc'], ['id', 'asc']])
                ->values()
                ->all(),
            // Deliveries and returns only, like the card's totals; a new customer's initial balance is an adjustment.
            'containers' => $day->containerMovements
                ->whereIn('movement_type', [ContainerMovementType::Delivered->value, ContainerMovementType::Returned->value])
                ->values()
                ->map(fn (ContainerMovement $movement): array => [
                    'id' => $movement->id,
                    'customer' => $movement->customer?->name,
                    'driver' => $movement->creator?->name,
                    'at' => $minute($movement->created_at),
                    'container' => $movement->containerType?->description,
                    'movement' => $movement->movement_type,
                    'quantity' => (int) $movement->quantity,
                ])
                ->all(),
        ];
    }
}
