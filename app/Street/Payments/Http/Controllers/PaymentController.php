<?php

namespace App\Street\Payments\Http\Controllers;

use App\Accounts\Models\Driver;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use App\Shared\ListSort;
use App\Shared\TextMatch;
use App\Street\Payments\CancelPayment;
use App\Street\Payments\Http\Requests\CancelPaymentRequest;
use App\Street\Payments\Models\Payment;
use App\Street\StreetOperation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

        $sort = ListSort::apply($query, $request->query('sort'), $request->query('direction'), [
            'paid_at' => 'paid_at',
            'customer' => fn (Builder $sorted, string $direction) => $sorted->orderBy(
                Customer::query()->select('name')->whereColumn('customers.id', 'payments.customer_id'),
                $direction,
            ),
            // The name the list shows under "Cobrado por": the driver of the visit, else whoever received the payment.
            'collected_by' => fn (Builder $sorted, string $direction) => $sorted->orderByRaw(
                '(select coalesce('
                .'(select users.name from visits join drivers on drivers.id = visits.driver_id join users on users.id = drivers.user_id where visits.id = payments.visit_id), '
                .'(select users.name from users where users.id = payments.received_by)'
                .")) {$direction}",
            ),
            'method' => fn (Builder $sorted, string $direction) => $sorted->orderBy(
                PaymentMethod::query()->select('name')->whereColumn('payment_methods.id', 'payments.payment_method_id'),
                $direction,
            ),
            'status' => 'status',
            'amount' => 'amount',
        ]);

        $payments = ($sort === null ? $query->latest('paid_at') : $query)->paginate(15)->withQueryString();

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
            ],
            'canCancel' => $request->user()->can('cancel-street-records'),
            'filters' => [
                'search' => $search,
                'date' => $date,
                'repartidor_id' => $driverFilter,
                'payment_method_id' => $paymentMethodId,
                'status' => $status ?? 'all',
                'sort' => $sort['sort'] ?? null,
                'direction' => $sort['direction'] ?? null,
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
