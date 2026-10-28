<?php

namespace App\Street\Sales\Http\Controllers;

use App\Accounts\Models\Driver;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Http\Controllers\Controller;
use App\Street\Sales\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /**
     * Display a listing of sales with filters (date, repartidor/driver, payment status/method).
     */
    public function index(Request $request, DailyFigures $figures): Response
    {
        $search = $request->query('search');
        $date = $request->query('date', Carbon::today()->toDateString());
        $driverId = $request->query('repartidor_id') ?? $request->query('driver_id');
        $saleType = $request->query('sale_type'); // 'all', 'credit', 'cash_immediate'

        $query = Sale::query()
            ->with([
                'customer.primaryAddress',
                'visit.driver.user',
                'details.product',
                'payments.paymentMethod',
            ])
            ->where('enabled', true);

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($driverId) {
            $query->whereHas('visit', fn ($q) => $q->where('driver_id', $driverId));
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

        // An empty date query parameter arrives as null and falls back to today, so the totals are always one day's.
        $day = $figures->forDay($date ?: Carbon::today()->toDateString(), DayScope::all());
        $totalSalesAmount = $day->totalSales;
        $creditSalesAmount = $day->creditSales;

        $drivers = Driver::with('user')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->user?->name ?? 'Repartidor',
        ]);

        return Inertia::render('Street/Sales/Index', [
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
                'repartidor_id' => $driverId,
                'sale_type' => $saleType ?? 'all',
            ],
            'repartidores' => $drivers,
        ]);
    }
}
