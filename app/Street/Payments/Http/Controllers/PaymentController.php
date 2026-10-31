<?php

namespace App\Street\Payments\Http\Controllers;

use App\Accounts\Models\Driver;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Shared\TextMatch;
use App\Street\Payments\CancelPayment;
use App\Street\Payments\Http\Requests\CancelPaymentRequest;
use App\Street\Payments\Models\Payment;
use App\Street\StreetOperation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments and reconciliation status.
     */
    public function index(Request $request, DailyFigures $figures): Response
    {
        $search = $request->query('search');
        $date = $request->query('date', Carbon::today()->toDateString());
        $driverFilter = $request->query('repartidor_id');
        // The same ownership the cash close uses, so a driver's list adds up to their cash close.
        $scope = DayScope::fromFilter($driverFilter);
        $paymentMethodId = $request->query('payment_method_id');
        $status = $request->query('status'); // 'CONFIRMED', 'CANCELLED', 'all'

        $query = Payment::query()
            ->with([
                'customer',
                'paymentMethod',
                'recipient',
                'visit.driver.user',
                'sale',
            ]);

        if ($date) {
            $query->whereDate('paid_at', $date);
        }

        $scope->apply($query, 'received_by');

        if ($paymentMethodId) {
            $query->where('payment_method_id', $paymentMethodId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        TextMatch::search($query, ['customer.name'], $search);

        $payments = $query->latest('paid_at')->paginate(15)->withQueryString();

        // An empty date query parameter arrives as null and falls back to today, so the totals are always one day's.
        $day = $figures->forDay($date ?: Carbon::today()->toDateString(), $scope);
        $totalCollected = $day->totalCollected;
        $cashTotal = $day->cashAmount;
        $transferTotal = $day->transferAmount;

        $paymentMethods = PaymentMethod::where('enabled', true)->get();

        return Inertia::render('Street/Payments/Index', [
            'payments' => $payments,
            'metrics' => [
                'totalCollected' => $totalCollected,
                'cashTotal' => $cashTotal,
                'transferTotal' => $transferTotal,
                'cardTotal' => $day->cardAmount,
            ],
            'canCancel' => $request->user()->can('cancel-street-records'),
            'filters' => [
                'search' => $search,
                'date' => $date,
                'repartidor_id' => $driverFilter,
                'payment_method_id' => $paymentMethodId,
                'status' => $status ?? 'all',
            ],
            'paymentMethods' => $paymentMethods,
            'repartidores' => Driver::with('user')->get()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user?->name ?? 'Repartidor',
            ]),
        ]);
    }

    /**
     * The admin cancels a payment of any day: its amount goes back on the ledger and who, when and why are kept.
     */
    public function cancel(CancelPaymentRequest $request, Payment $payment, StreetOperation $operation): RedirectResponse
    {
        $operation->cancelPayment(new CancelPayment(
            payment: $payment,
            cancelledBy: $request->user(),
            reason: $request->validated('reason'),
            byAdmin: true,
        ));

        return back()->with('success', 'Cobro anulado.');
    }
}
