<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAgreement;
use App\Models\Driver;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Operations\CustomerAddressDetails;
use App\Operations\CustomerEdit;
use App\Operations\CustomerIntake;
use App\Operations\CustomerRegistration;
use App\Operations\PriceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function show(Customer $customer, PriceResolver $prices): Response
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

        $canRecordAtHome = Gate::allows('manage-agreements');

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'accountEntries' => $accountEntries,
            'containerMovements' => $containerMovements,
            'agreements' => $agreements,
            'agreementProducts' => Product::query()->enabled()->orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'price' => (float) $product->price]),
            'canManageAgreements' => Gate::allows('manage-agreements'),
            'canRecordAtHome' => $canRecordAtHome,
            'homeProducts' => $canRecordAtHome ? $this->homeProducts($customer, $prices) : [],
            'paymentMethods' => $canRecordAtHome ? PaymentMethod::query()->enabled()->orderBy('name')->get(['id', 'name']) : [],
            'containerTypes' => $canRecordAtHome ? ContainerType::query()->enabled()->orderBy('description')->get(['id', 'description']) : [],
        ]);
    }

    /**
     * The products of a sale at home with the price the server will charge today, read-only for the admin.
     *
     * @return list<array{id: int, name: string, unitPrice: float, source: 'agreement'|'list'}>
     */
    private function homeProducts(Customer $customer, PriceResolver $prices): array
    {
        return Product::query()->enabled()->orderBy('name')->get()
            ->map(function (Product $product) use ($customer, $prices): array {
                $resolved = $prices->priceFor($customer, $product, now());

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unitPrice' => (float) $resolved->unitPrice,
                    'source' => $resolved->agreement === null ? 'list' : 'agreement',
                ];
            })
            ->all();
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request, CustomerIntake $intake): RedirectResponse
    {
        $validated = $request->validated();

        $intake->register(new CustomerRegistration(
            name: $validated['name'],
            phoneNumber: $validated['phone_number'] ?? null,
            driverId: isset($validated['repartidor_id']) ? (int) $validated['repartidor_id'] : null,
            address: $this->addressDetails($validated),
            createdBy: $request->user(),
            // numeric also accepts forms like 1e2; the ledger works with a fixed two-decimal string.
            initialDebt: number_format((float) ($validated['initial_balance'] ?? 0), 2, '.', ''),
            initialContainers: [
                'BIDON_20L' => (int) ($validated['bidon_20l_balance'] ?? 0),
                'SIFON_2L' => (int) ($validated['sifon_2l_balance'] ?? 0),
            ],
        ));

        return redirect()->route('customers.index')->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerIntake $intake): RedirectResponse
    {
        $validated = $request->validated();

        $intake->update($customer, new CustomerEdit(
            name: $validated['name'],
            phoneNumber: $validated['phone_number'] ?? null,
            address: $this->addressDetails($validated),
            modifiedBy: $request->user(),
            driverId: isset($validated['repartidor_id']) ? (int) $validated['repartidor_id'] : null,
        ));

        return redirect()->back()->with('success', 'Cliente actualizado exitosamente');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function addressDetails(array $validated): CustomerAddressDetails
    {
        return new CustomerAddressDetails(
            street: $validated['street'],
            apartment: $validated['apartment'] ?? null,
            floor: $validated['floor'] ?? null,
            visualReference: $validated['visual_reference'] ?? null,
        );
    }
}
