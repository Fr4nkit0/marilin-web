<?php

namespace App\Http\Controllers;

use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAddress;
use App\Models\CustomerAgreement;
use App\Models\CustomerBalance;
use App\Models\CustomerContainerBalance;
use App\Models\Driver;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with debt, address, and repartidor/driver info.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $driverId = $request->query('repartidor_id') ?? $request->query('driver_id');
        $debtStatus = $request->query('debt_status'); // 'with_debt', 'clean', 'all'

        $query = Customer::query()
            ->with([
                'primaryAddress',
                'driver.user',
                'balance',
                'containerBalances.containerType',
            ])
            ->where('enabled', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhereHas('primaryAddress', fn ($sq) => $sq->where('street', 'ilike', "%{$search}%"));
            });
        }

        if ($driverId) {
            $query->where('driver_id', $driverId);
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

        $drivers = Driver::with('user')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->user?->name ?? 'Sin nombre',
        ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'repartidor_id' => $driverId,
                'debt_status' => $debtStatus ?? 'all',
            ],
            'repartidores' => $drivers,
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
            'driver.user',
            'balance',
            'containerBalances.containerType',
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

        $today = now()->toDateString();
        $agreements = $customer->agreements()
            ->with(['product:id,name', 'creator:id,name'])
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CustomerAgreement $agreement): array => [
                'id' => $agreement->id,
                'product' => $agreement->product?->name,
                'unitPrice' => (float) $agreement->unit_price,
                'validFrom' => $agreement->valid_from->toDateString(),
                'validTo' => $agreement->valid_to?->toDateString(),
                'reason' => $agreement->reason,
                'createdBy' => $agreement->creator?->name,
                'status' => $agreement->statusOn($today),
            ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'accountEntries' => $accountEntries,
            'containerMovements' => $containerMovements,
            'agreements' => $agreements,
            'agreementProducts' => Product::query()->enabled()->orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'price' => (float) $product->price]),
            'canManageAgreements' => Gate::allows('manage-agreements'),
        ]);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone_number' => 'nullable|string|max:50',
            'repartidor_id' => 'nullable|exists:drivers,id',
            'street' => 'required|string|max:200',
            'apartment' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:20',
            'visual_reference' => 'nullable|string|max:500',
            'initial_balance' => 'nullable|numeric|min:0',
            'bidon_20l_balance' => 'nullable|integer|min:0',
            'sifon_2l_balance' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = $request->user();

            $customer = Customer::create([
                'name' => $validated['name'],
                'phone_number' => $validated['phone_number'] ?? null,
                'driver_id' => $validated['repartidor_id'] ?? null,
                'created_by' => $user->id,
                'enabled' => true,
            ]);

            // Address
            CustomerAddress::create([
                'customer_id' => $customer->id,
                'street' => $validated['street'],
                'apartment' => $validated['apartment'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'visual_reference' => $validated['visual_reference'] ?? null,
                'is_primary' => true,
                'created_by' => $user->id,
                'enabled' => true,
            ]);

            // Customer Balance
            $initialDebt = (float) ($validated['initial_balance'] ?? 0);
            CustomerBalance::create([
                'customer_id' => $customer->id,
                'current_balance' => $initialDebt,
                'created_by' => $user->id,
                'enabled' => true,
            ]);

            if ($initialDebt > 0) {
                CustomerAccountEntry::create([
                    'customer_id' => $customer->id,
                    'entry_type' => 'ADJUSTMENT',
                    'direction' => 1,
                    'amount' => $initialDebt,
                    'description' => 'Saldo deudor inicial registrado al dar de alta',
                    'created_by' => $user->id,
                ]);
            }

            // Container Balances (20L and Sifon)
            $bidonType = ContainerType::where('code', 'BIDON_20L')->first();
            if ($bidonType && isset($validated['bidon_20l_balance']) && (int) $validated['bidon_20l_balance'] > 0) {
                CustomerContainerBalance::create([
                    'customer_id' => $customer->id,
                    'container_type_id' => $bidonType->id,
                    'balance' => (int) $validated['bidon_20l_balance'],
                    'created_by' => $user->id,
                    'enabled' => true,
                ]);
            }

            $sifonType = ContainerType::where('code', 'SIFON_2L')->first();
            if ($sifonType && isset($validated['sifon_2l_balance']) && (int) $validated['sifon_2l_balance'] > 0) {
                CustomerContainerBalance::create([
                    'customer_id' => $customer->id,
                    'container_type_id' => $sifonType->id,
                    'balance' => (int) $validated['sifon_2l_balance'],
                    'created_by' => $user->id,
                    'enabled' => true,
                ]);
            }
        });

        return redirect()->route('customers.index')->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone_number' => 'nullable|string|max:50',
            'repartidor_id' => 'nullable|exists:drivers,id',
            'street' => 'required|string|max:200',
            'apartment' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:20',
            'visual_reference' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $request, $customer) {
            $user = $request->user();

            $customer->update([
                'name' => $validated['name'],
                'phone_number' => $validated['phone_number'] ?? null,
                'driver_id' => $validated['repartidor_id'] ?? null,
            ]);

            // Update or Create Primary Address
            $primaryAddress = $customer->primaryAddress;
            if ($primaryAddress) {
                $primaryAddress->update([
                    'street' => $validated['street'],
                    'apartment' => $validated['apartment'] ?? null,
                    'floor' => $validated['floor'] ?? null,
                    'visual_reference' => $validated['visual_reference'] ?? null,
                    'last_modified_by' => $user->id,
                ]);
            } else {
                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'street' => $validated['street'],
                    'apartment' => $validated['apartment'] ?? null,
                    'floor' => $validated['floor'] ?? null,
                    'visual_reference' => $validated['visual_reference'] ?? null,
                    'is_primary' => true,
                    'created_by' => $user->id,
                    'enabled' => true,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Cliente actualizado exitosamente');
    }
}
