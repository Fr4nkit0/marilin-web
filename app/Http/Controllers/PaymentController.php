<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments and reconciliation status.
     */
    public function index(Request $request): Response
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

        // Calculate totals
        $totalCollected = (float) Payment::when($date, fn ($q) => $q->whereDate('paid_at', $date))
            ->where('status', 'CONFIRMED')
            ->sum('amount');

        $cashTotal = (float) Payment::when($date, fn ($q) => $q->whereDate('paid_at', $date))
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'CASH'))
            ->where('status', 'CONFIRMED')
            ->sum('amount');

        $transferTotal = (float) Payment::when($date, fn ($q) => $q->whereDate('paid_at', $date))
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'TRANSFER'))
            ->where('status', 'CONFIRMED')
            ->sum('amount');

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
