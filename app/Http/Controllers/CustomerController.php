<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Repartidor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with debt, address, and repartidor info.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $repartidorId = $request->query('repartidor_id');
        $debtStatus = $request->query('debt_status'); // 'with_debt', 'clean', 'all'

        $query = Customer::query()
            ->with([
                'primaryAddress',
                'repartidor.user',
                'balance',
                'containerBalances.containerType',
            ])
            ->where('enabled', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhereHas('primaryAddress', fn ($sq) => $sq->where('address_line', 'ilike', "%{$search}%"));
            });
        }

        if ($repartidorId) {
            $query->where('repartidor_id', $repartidorId);
        }

        if ($debtStatus === 'with_debt') {
            $query->whereHas('balance', fn ($q) => $q->where('current_balance', '>', 0));
        } elseif ($debtStatus === 'clean') {
            $query->where(function ($q) {
                $q->whereDoesntHave('balance')
                    ->orWhereHas('balance', fn ($sq) => $sq->where('current_balance', '<=', 0));
            });
        }

        $customers = $query->orderBy('name')->paginate(15)->withQueryString();

        $repartidores = Repartidor::with('user')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->user?->name ?? 'Sin nombre',
        ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'repartidor_id' => $repartidorId,
                'debt_status' => $debtStatus ?? 'all',
            ],
            'repartidores' => $repartidores,
        ]);
    }

    /**
     * Display the 360 view of a customer (Account entries, container balances, agreements, sales).
     */
    public function show(Customer $customer): Response
    {
        $customer->load([
            'primaryAddress',
            'addresses',
            'repartidor.user',
            'balance',
            'containerBalances.containerType',
            'agreements.product',
        ]);

        // Paginate / fetch last 10 account movements (Sales, Payments, Adjustments)
        $accountEntries = $customer->accountEntries()
            ->with(['creator', 'payment.paymentMethod', 'sale'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'entryType' => $entry->entry_type,
                'direction' => $entry->direction,
                'amount' => (float) $entry->amount,
                'description' => $entry->description,
                'createdAt' => $entry->created_at?->format('d/m/Y H:i'),
                'paymentMethod' => $entry->payment?->paymentMethod?->name,
            ]);

        // Container movements
        $containerMovements = $customer->containerMovements()
            ->with(['containerType', 'creator'])
            ->latest('id')
            ->limit(10)
            ->get();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'accountEntries' => $accountEntries,
            'containerMovements' => $containerMovements,
        ]);
    }
}
