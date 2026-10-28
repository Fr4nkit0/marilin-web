<?php

namespace App\Http\Controllers;

use App\Catalog\Models\PaymentMethod;
use App\Models\Payment;
use App\Operations\DailyFigures\DailyFigures;
use App\Operations\DailyFigures\DayScope;
use Carbon\Carbon;
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

        if ($paymentMethodId) {
            $query->where('payment_method_id', $paymentMethodId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('customer', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
        }

        $payments = $query->latest('paid_at')->paginate(15)->withQueryString();

        // An empty date query parameter arrives as null and falls back to today, so the totals are always one day's.
        $day = $figures->forDay($date ?: Carbon::today()->toDateString(), DayScope::all());
        $totalCollected = $day->totalCollected;
        $cashTotal = $day->cashAmount;
        $transferTotal = $day->transferAmount;

        $paymentMethods = PaymentMethod::where('enabled', true)->get();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'metrics' => [
                'totalCollected' => $totalCollected,
                'cashTotal' => $cashTotal,
                'transferTotal' => $transferTotal,
            ],
            'filters' => [
                'search' => $search,
                'date' => $date,
                'payment_method_id' => $paymentMethodId,
                'status' => $status ?? 'all',
            ],
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
