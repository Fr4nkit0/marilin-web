<?php

namespace App\Http\Controllers;

use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\Repartidor;
use App\Models\Route as DeliveryRoute;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashCloseController extends Controller
{
    /**
     * Display cash close / route settlement and reconciliation per driver.
     */
    public function index(Request $request): Response
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $repartidorId = $request->query('repartidor_id');

        $repartidores = Repartidor::with('user')->where('enabled', true)->get();

        // Selected driver or first driver
        $selectedRepartidor = $repartidorId
            ? $repartidores->firstWhere('id', $repartidorId)
            : $repartidores->first();

        $settlement = null;

        if ($selectedRepartidor) {
            $route = DeliveryRoute::with([
                'inventories.product',
                'inventoryLoads.product',
                'stops.visits.sales',
                'stops.visits.payments.paymentMethod',
                'stops.visits.containerMovements',
            ])
                ->where('assigned_repartidor_id', $selectedRepartidor->id)
                ->whereDate('date', $date)
                ->first();

            if ($route) {
                // Payments breakdown in route
                $cashAmount = (float) Payment::whereHas('visit', fn ($q) => $q->where('repartidor_id', $selectedRepartidor->id))
                    ->whereDate('paid_at', $date)
                    ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'CASH'))
                    ->where('status', 'CONFIRMED')
                    ->sum('amount');

                $transferAmount = (float) Payment::whereHas('visit', fn ($q) => $q->where('repartidor_id', $selectedRepartidor->id))
                    ->whereDate('paid_at', $date)
                    ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'TRANSFER'))
                    ->where('status', 'CONFIRMED')
                    ->sum('amount');

                $totalSales = (float) Sale::whereHas('visit', fn ($q) => $q->where('repartidor_id', $selectedRepartidor->id))
                    ->whereDate('date', $date)
                    ->sum('total');

                $creditSales = (float) Sale::whereHas('visit', fn ($q) => $q->where('repartidor_id', $selectedRepartidor->id))
                    ->whereDate('date', $date)
                    ->where('is_credit_sale', true)
                    ->sum('total');

                // Containers movement in route
                $containersDelivered = (int) ContainerMovement::whereHas('visit', fn ($q) => $q->where('repartidor_id', $selectedRepartidor->id))
                    ->whereDate('created_at', $date)
                    ->where('type', 'DELIVERED')
                    ->sum('quantity');

                $containersReturned = (int) ContainerMovement::whereHas('visit', fn ($q) => $q->where('repartidor_id', $selectedRepartidor->id))
                    ->whereDate('created_at', $date)
                    ->where('type', 'RETURNED')
                    ->sum('quantity');

                $settlement = [
                    'routeId' => $route->id,
                    'status' => $route->status,
                    'totalSales' => $totalSales,
                    'creditSales' => $creditSales,
                    'cashAmount' => $cashAmount,
                    'transferAmount' => $transferAmount,
                    'totalCollected' => $cashAmount + $transferAmount,
                    'containersDelivered' => $containersDelivered,
                    'containersReturned' => $containersReturned,
                    'stockLoaded' => $route->inventoryLoads->map(fn ($l) => [
                        'product' => $l->product?->name,
                        'quantity' => (float) $l->quantity,
                    ]),
                    'stockRemaining' => $route->inventories->map(fn ($i) => [
                        'product' => $i->product?->name,
                        'quantity' => (float) $i->current_quantity,
                    ]),
                ];
            }
        }

        return Inertia::render('CashClose/Index', [
            'repartidores' => $repartidores->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user?->name ?? 'Repartidor',
            ]),
            'selectedRepartidorId' => $selectedRepartidor?->id,
            'settlement' => $settlement,
            'date' => $date,
        ]);
    }
}
