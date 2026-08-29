<?php

namespace App\Actions;

use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\Route as DeliveryRoute;
use App\Models\Sale;
use Carbon\Carbon;

class GetDailyDashboardMetricsAction
{
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
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'CASH'))
            ->where('status', 'CONFIRMED')
            ->sum('amount');

        $transferPayments = (float) Payment::whereDate('paid_at', $targetDate)
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'TRANSFER'))
            ->where('status', 'CONFIRMED')
            ->sum('amount');

        $totalCollected = $cashPayments + $transferPayments;

        // 3. Route progress and stops
        $routes = DeliveryRoute::with(['stops.visits', 'driver.user'])->whereDate('date', $targetDate)->get();
        $totalStops = 0;
        $completedStops = 0;

        $repartidoresStatus = [];

        foreach ($routes as $route) {
            $routeStopsCount = $route->stops->count();
            $routeCompletedCount = $route->stops->filter(function ($stop) {
                return $stop->visits->whereIn('status', ['COMPLETED', 'DELIVERED', 'FINISHED'])->isNotEmpty();
            })->count();

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
            ->where('movement_type', 'DELIVERED')
            ->sum('quantity');

        $containersReturned = (int) ContainerMovement::whereDate('created_at', $targetDate)
            ->where('movement_type', 'RETURNED')
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
        ];
    }
}
