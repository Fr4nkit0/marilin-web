<?php

namespace App\Street\Sales\Http\Controllers;

use App\Accounts\Models\Driver;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Http\Controllers\Controller;
use App\Shared\TextMatch;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\CancelSale;
use App\Street\Sales\Http\Requests\CancelSaleRequest;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
                // Only the payments still in force: they say how the sale was paid and stay when it is cancelled.
                'payments' => fn (HasMany $payments) => $payments->confirmed()->with('paymentMethod:id,name')->orderBy('paid_at')->orderBy('id'),
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

        $sales = $query->latest('date')->paginate(15)->withQueryString()->through(fn (Sale $sale): array => $this->listed($sale));

        // An empty date query parameter arrives as null and falls back to today, so the totals are always one day's.
        $day = $figures->forDay($date ?: Carbon::today()->toDateString(), $scope);
        $totalSalesAmount = $day->totalSales;
        $creditSalesAmount = $day->creditSales;
        $immediateSalesAmount = $day->immediateSales;

        $drivers = Driver::with('user')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->user?->name ?? 'Repartidor',
        ]);

        return Inertia::render('Street/Sales/Index', [
            'sales' => $sales,
            'metrics' => [
                'totalSalesAmount' => $totalSalesAmount,
                'creditSalesAmount' => $creditSalesAmount,
                'immediateSalesAmount' => $immediateSalesAmount,
                // "Cobrado" is the day's payments, the same figure the panel and the cash close show.
                'collectedAmount' => $day->totalCollected,
                'count' => $confirmedCount,
            ],
            'canCancel' => $request->user()->can('cancel-street-records'),
            'filters' => [
                'search' => $search,
                'date' => $date,
                'repartidor_id' => $driverFilter,
                'sale_type' => $saleType ?? 'all',
            ],
            'repartidores' => $drivers,
        ]);
    }

    /**
     * A sale as the list shows it: every method it was paid with ("Efectivo + Transf.") and the payments still in force,
     * which the cancel modal lists because cancelling the sale does not cancel them.
     *
     * @return array<string, mixed>
     */
    private function listed(Sale $sale): array
    {
        $payments = $sale->payments;

        return [
            ...Arr::except($sale->toArray(), 'payments'),
            'payment_methods' => $payments->pluck('paymentMethod.name')->filter()->unique()->values()->all(),
            'active_payments' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->paymentMethod?->name,
            ])->all(),
        ];
    }

    /**
     * The admin cancels a sale of any day: the ledger, the route stock and the containers are reversed and who, when and why are kept.
     */
    public function cancel(CancelSaleRequest $request, Sale $sale, StreetOperation $operation): RedirectResponse
    {
        $operation->cancelSale(new CancelSale(
            sale: $sale,
            cancelledBy: $request->user(),
            reason: $request->validated('reason'),
            byAdmin: true,
        ));

        return back()->with('success', 'Venta anulada.');
    }
}
