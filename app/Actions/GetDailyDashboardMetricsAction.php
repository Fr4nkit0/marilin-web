<?php

namespace App\Actions;

use App\Enums\ContainerMovementType;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Enums\RouteStatus;
use App\Models\ContainerMovement;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class GetDailyDashboardMetricsAction
{
    /**
     * A driver on route whose app has not reached the server for longer than this is flagged.
     */
    private const int STALE_SYNC_MINUTES = 30;

    /**
     * Execute the action to get aggregated KPIs for the given date.
     *
     * @return array<string, mixed>
     */
    public function execute(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        // 1. Total Daily Sales
        $totalSales = (float) Sale::whereDate('date', $targetDate)->sum('total');
        $yesterdaySales = (float) Sale::whereDate('date', Carbon::parse($targetDate)->subDay()->toDateString())->sum('total');
        $salesDelta = $yesterdaySales > 0
            ? round((($totalSales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : 0;

        // 2. Payments / Collections Breakdown
        $cashPayments = (float) Payment::whereDate('paid_at', $targetDate)
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', PaymentMethodCode::Cash->value))
            ->where('status', PaymentStatus::Confirmed->value)
            ->sum('amount');

        $transferPayments = (float) Payment::whereDate('paid_at', $targetDate)
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', PaymentMethodCode::Transfer->value))
            ->where('status', PaymentStatus::Confirmed->value)
            ->sum('amount');

        $totalCollected = $cashPayments + $transferPayments;

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
                'startedAt' => $route->started_at?->format('H:i'),
                'completedAt' => $route->completed_at?->format('H:i'),
            ];
        }

        // 4. Containers delivered vs returned
        $containersDelivered = (int) ContainerMovement::whereDate('created_at', $targetDate)
            ->where('movement_type', ContainerMovementType::Delivered->value)
            ->sum('quantity');

        $containersReturned = (int) ContainerMovement::whereDate('created_at', $targetDate)
            ->where('movement_type', ContainerMovementType::Returned->value)
            ->sum('quantity');

        return [
            'date' => $targetDate,
            'kpis' => [
                'totalSales' => $totalSales,
                'salesDelta' => $salesDelta,
                'totalCollected' => $totalCollected,
                'cashPayments' => $cashPayments,
                'transferPayments' => $transferPayments,
                'totalStops' => $totalStops,
                'completedStops' => $completedStops,
                'stopsProgressPercent' => $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0,
                'containersDelivered' => $containersDelivered,
                'containersReturned' => $containersReturned,
            ],
            'repartidores' => $repartidoresStatus,
            'details' => $this->details($targetDate, $routes),
            'sync' => $this->syncStatus(),
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
    private function details(string $date, Collection $routes): array
    {
        $minute = fn ($moment): ?string => $moment === null ? null : Carbon::parse($moment)->format('Y-m-d H:i');

        return [
            'sales' => Sale::query()
                ->with(['customer:id,name', 'creator:id,name'])
                ->whereDate('date', $date)
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'customer' => $sale->customer?->name,
                    'driver' => $sale->creator?->name,
                    'at' => $minute($sale->date),
                    'amount' => (float) $sale->total,
                    'credit' => (bool) $sale->is_credit_sale,
                ])
                ->all(),
            'payments' => Payment::query()
                ->with(['customer:id,name', 'recipient:id,name', 'paymentMethod:id,code'])
                ->whereDate('paid_at', $date)
                ->where('status', PaymentStatus::Confirmed->value)
                ->whereHas('paymentMethod', fn ($q) => $q->whereIn('code', [PaymentMethodCode::Cash->value, PaymentMethodCode::Transfer->value]))
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'customer' => $payment->customer?->name,
                    'driver' => $payment->recipient?->name,
                    'at' => $minute($payment->paid_at),
                    'method' => $payment->paymentMethod?->code,
                    'amount' => (float) $payment->amount,
                ])
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
            'containers' => ContainerMovement::query()
                ->with(['customer:id,name', 'creator:id,name', 'containerType:id,description'])
                ->whereDate('created_at', $date)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
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
