<?php

namespace App\Http\Controllers;

use App\Models\Repartidor;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /**
     * Display a listing of sales with filters (date, repartidor, payment status/method).
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $date = $request->query('date', Carbon::today()->toDateString());
        $repartidorId = $request->query('repartidor_id');
        $saleType = $request->query('sale_type'); // 'all', 'credit', 'cash_immediate'

        $query = Sale::query()
            ->with([
                'customer.primaryAddress',
                'visit.repartidor.user',
                'details.product',
                'payments.paymentMethod',
            ])
            ->where('enabled', true);

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($repartidorId) {
            $query->whereHas('visit', fn ($q) => $q->where('repartidor_id', $repartidorId));
        }

        if ($saleType === 'credit') {
            $query->where('is_credit_sale', true);
        } elseif ($saleType === 'cash_immediate') {
            $query->where('is_credit_sale', false);
        }

        if ($search) {
            $query->whereHas('customer', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
        }

        $sales = $query->latest('date')->paginate(15)->withQueryString();

        // Calculate summary metrics for the day
        $totalSalesAmount = (float) Sale::where('enabled', true)
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            ->sum('total');

        $creditSalesAmount = (float) Sale::where('enabled', true)
            ->where('is_credit_sale', true)
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            ->sum('total');

        $repartidores = Repartidor::with('user')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->user?->name ?? 'Repartidor',
        ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'metrics' => [
                'totalSalesAmount' => $totalSalesAmount,
                'creditSalesAmount' => $creditSalesAmount,
                'immediateSalesAmount' => $totalSalesAmount - $creditSalesAmount,
                'count' => $sales->total(),
            ],
            'filters' => [
                'search' => $search,
                'date' => $date,
                'repartidor_id' => $repartidorId,
                'sale_type' => $saleType ?? 'all',
            ],
            'repartidores' => $repartidores,
        ]);
    }
}
