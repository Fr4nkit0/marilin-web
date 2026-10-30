<?php

namespace App\Street\Sales\Http\Controllers;

use App\Accounts\Models\Driver;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Http\Controllers\Controller;
use App\Shared\TextMatch;
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
        $driverFilter = $request->query('repartidor_id') ?? $request->query('driver_id');
        // The same ownership the cash close uses, so a driver's list adds up to their cash close.
        $scope = DayScope::fromFilter($driverFilter);
        $saleType = $request->query('sale_type'); // 'all', 'credit', 'cash_immediate'

        $query = Sale::query()
            ->with([
                'customer.primaryAddress',
                'visit.driver.user',
                'creator:id,name',
                'details.product',
                'payments.paymentMethod',
            ])
            ->where('enabled', true);

        if ($date) {
            $query->whereDate('date', $date);
        }

        $scope->apply($query, 'created_by');

        if ($saleType === 'credit') {
            $query->where('is_credit_sale', true);
        } elseif ($saleType === 'cash_immediate') {
            $query->where('is_credit_sale', false);
        }

        TextMatch::search($query, ['customer.name'], $search);

        // A cancelled sale stays listed, marked as such, but it is not one of the day's operations.
        $confirmedCount = (clone $query)->confirmed()->count();

        $sales = $query->latest('date')->paginate(15)->withQueryString();

        // An empty date query parameter arrives as null and falls back to today, so the totals are always one day's.
        $day = $figures->forDay($date ?: Carbon::today()->toDateString(), $scope);
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
                'count' => $confirmedCount,
            ],
            'filters' => [
                'search' => $search,
                'date' => $date,
                'repartidor_id' => $driverFilter,
                'sale_type' => $saleType ?? 'all',
            ],
            'repartidores' => $drivers,
        ]);
    }
}
