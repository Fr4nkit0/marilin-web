<?php

namespace App\Http\Controllers;

use App\Models\Repartidor;
use App\Models\Route as DeliveryRoute;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepartidorController extends Controller
{
    /**
     * List all repartidores with their current day route status, efficiency, and metrics.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today()->toDateString();
        $search = $request->query('search');

        $repartidores = Repartidor::with(['user', 'customers'])
            ->where('enabled', true)
            ->get()
            ->map(function ($repartidor) use ($today) {
                // Find today's route
                $route = DeliveryRoute::with(['stops.visits'])
                    ->where('assigned_repartidor_id', $repartidor->id)
                    ->whereDate('date', $today)
                    ->first();

                $totalStops = $route ? $route->stops->count() : 0;
                $completedStops = $route ? $route->stops->filter(function ($stop) {
                    return $stop->visits->whereIn('status', ['FINISHED', 'DELIVERED', 'COMPLETED'])->isNotEmpty();
                })->count() : 0;

                $progressPercent = $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0;

                // Status calculation
                $status = 'SIN_ACTIVIDAD';
                if ($route) {
                    $status = $route->status; // PLANNED, IN_PROGRESS, COMPLETED
                }

                return [
                    'id' => $repartidor->id,
                    'name' => $repartidor->user?->name ?? 'Repartidor',
                    'email' => $repartidor->user?->email,
                    'totalCustomers' => $repartidor->customers->count(),
                    'currentRoute' => $route ? [
                        'id' => $route->id,
                        'status' => $status,
                        'totalStops' => $totalStops,
                        'completedStops' => $completedStops,
                        'progressPercent' => $progressPercent,
                        'startedAt' => $route->started_at?->format('H:i'),
                        'completedAt' => $route->completed_at?->format('H:i'),
                    ] : null,
                ];
            });

        if ($search) {
            $repartidores = $repartidores->filter(function ($r) use ($search) {
                return str_contains(strtolower($r['name']), strtolower($search)) ||
                       str_contains(strtolower($r['email'] ?? ''), strtolower($search));
            })->values();
        }

        return Inertia::render('Repartidores/Index', [
            'repartidores' => $repartidores,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show single repartidor detail, today's route, inventory on truck and stop timeline.
     */
    public function show(Repartidor $repartidor, Request $request): Response
    {
        $targetDate = $request->query('date', Carbon::today()->toDateString());

        $repartidor->load(['user', 'customers']);

        $route = DeliveryRoute::with([
            'stops.customer.primaryAddress',
            'stops.visits.outcome',
            'stops.visits.sales.details.product',
            'stops.visits.payments.paymentMethod',
            'stops.visits.containerMovements.containerType',
            'inventories.product',
            'inventoryLoads.product',
        ])
            ->where('assigned_repartidor_id', $repartidor->id)
            ->whereDate('date', $targetDate)
            ->first();

        // Calculate Route Timeline Stops
        $stops = [];
        $totalSalesAmount = 0;
        $totalCollectedAmount = 0;

        if ($route) {
            foreach ($route->stops as $stop) {
                $lastVisit = $stop->visits->last();
                $salesSum = $lastVisit ? (float) $lastVisit->sales->sum('total_amount') : 0;
                $paymentsSum = $lastVisit ? (float) $lastVisit->payments->sum('amount') : 0;

                $totalSalesAmount += $salesSum;
                $totalCollectedAmount += $paymentsSum;

                $stops[] = [
                    'id' => $stop->id,
                    'customer' => [
                        'id' => $stop->customer->id,
                        'name' => $stop->customer->name,
                        'address' => $stop->address?->address_line ?? $stop->customer->primaryAddress?->address_line,
                        'phone' => $stop->customer->phone_number,
                    ],
                    'status' => $lastVisit ? $lastVisit->status : 'PENDING',
                    'arrivedAt' => $lastVisit?->arrived_at?->format('H:i'),
                    'salesAmount' => $salesSum,
                    'paymentsAmount' => $paymentsSum,
                    'notes' => $lastVisit?->notes,
                ];
            }
        }

        // Inventories on truck
        $truckInventory = $route ? $route->inventories->map(function ($inv) {
            return [
                'id' => $inv->id,
                'productName' => $inv->product?->name ?? 'Producto',
                'currentQuantity' => (float) $inv->current_quantity,
            ];
        }) : [];

        return Inertia::render('Repartidores/Show', [
            'repartidor' => [
                'id' => $repartidor->id,
                'name' => $repartidor->user?->name ?? 'Repartidor',
                'email' => $repartidor->user?->email,
                'totalAssignedCustomers' => $repartidor->customers->count(),
            ],
            'route' => $route ? [
                'id' => $route->id,
                'date' => $route->date->format('Y-m-d'),
                'status' => $route->status,
                'startedAt' => $route->started_at?->format('H:i'),
                'completedAt' => $route->completed_at?->format('H:i'),
                'totalSalesAmount' => $totalSalesAmount,
                'totalCollectedAmount' => $totalCollectedAmount,
                'stops' => $stops,
                'inventory' => $truckInventory,
            ] : null,
            'targetDate' => $targetDate,
        ]);
    }
}
