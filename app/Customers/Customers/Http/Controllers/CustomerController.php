<?php

namespace App\Customers\Customers\Http\Controllers;

use App\Accounts\Models\Driver;
use App\Catalog\Enums\PaymentMethodCode;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Agreements\PriceResolver;
use App\Customers\Customers\AddressLocation;
use App\Customers\Customers\CustomerAddressDetails;
use App\Customers\Customers\CustomerEdit;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\CustomerPreferenceChange;
use App\Customers\Customers\CustomerRegistration;
use App\Customers\Customers\Http\Requests\StoreCustomerRequest;
use App\Customers\Customers\Http\Requests\UpdateCustomerRequest;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Http\Controllers\Controller;
use App\Shared\TextMatch;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Sales\Models\SaleDetail;
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

        TextMatch::search($query, ['name', 'phone_number', 'primaryAddress.street'], $search);

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

        return Inertia::render('Customers/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'repartidor_id' => $driverId,
                'debt_status' => $debtStatus ?? 'all',
            ],
            'repartidores' => $drivers,
            'neighborhoods' => Neighborhood::query()->enabled()->orderBy('name')->pluck('name'),
            'containerTypes' => ContainerType::query()->enabled()->orderBy('description')->get(['id', 'description']),
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
            ->with(['creator', 'payment.paymentMethod', 'sale.details.product'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (CustomerAccountEntry $entry) => [
                'id' => $entry->id,
                'entryType' => $entry->entry_type,
                'direction' => $entry->direction,
                'amount' => (float) $entry->amount,
                'concept' => $this->concept($entry),
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

        $preferences = $customer->preferences()
            ->enabled()
            ->orderBy('day_of_week')
            ->orderByRaw('time_window_start nulls first')
            ->get()
            ->map(fn (CustomerPreference $preference): array => [
                'id' => $preference->id,
                'dayOfWeek' => $preference->day_of_week,
                'timeWindowStart' => $this->hourMinute($preference->time_window_start),
                'timeWindowEnd' => $this->hourMinute($preference->time_window_end),
            ]);

        $canRecordAtHome = Gate::allows('manage-agreements');

        return Inertia::render('Customers/Customers/Show', [
            'customer' => $customer,
            'accountEntries' => $accountEntries,
            'containerMovements' => $containerMovements,
            'agreements' => $agreements,
            'preferences' => $preferences,
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
     * One wording for a sale or a payment, whether the street or the house recorded it: built from what was sold
     * ("Venta 2x Bidón 20L, 1x Sifón 1.5L") or how it was paid ("Cobro en efectivo"), never from the stored free text.
     * Cancellations and adjustments keep their own description.
     */
    private function concept(CustomerAccountEntry $entry): ?string
    {
        if ($entry->entry_type === 'SALE' && $entry->sale !== null) {
            $lines = $entry->sale->details
                ->sortBy('id')
                ->map(fn (SaleDetail $detail): string => "{$detail->quantity}x {$detail->product?->name}")
                ->implode(', ');

            return $lines === '' ? 'Venta' : "Venta {$lines}";
        }

        if ($entry->entry_type === 'PAYMENT' && $entry->payment !== null) {
            $method = $entry->payment->paymentMethod;

            return match ($method?->code) {
                PaymentMethodCode::Cash->value => 'Cobro en efectivo',
                PaymentMethodCode::Transfer->value => 'Cobro por transferencia',
                default => $method === null ? 'Cobro' : "Cobro ({$method->name})",
            };
        }

        return $entry->description;
    }

    private function hourMinute(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
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

        CustomerPreferenceController::asFieldErrors(fn () => $intake->register(new CustomerRegistration(
            name: $validated['name'],
            phoneNumber: $validated['phone_number'] ?? null,
            driverId: isset($validated['repartidor_id']) ? (int) $validated['repartidor_id'] : null,
            address: $this->addressDetails($validated, null),
            createdBy: $request->user(),
            // numeric also accepts forms like 1e2; the ledger works with a fixed two-decimal string.
            initialDebt: number_format((float) ($validated['initial_balance'] ?? 0), 2, '.', ''),
            initialContainers: array_map(fn (mixed $quantity): int => (int) $quantity, $validated['initial_containers'] ?? []),
            preferences: $this->preferenceRows($validated, $request),
        )));

        return redirect()->route('customers.index')->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerIntake $intake): RedirectResponse
    {
        $validated = $request->validated();

        CustomerPreferenceController::asFieldErrors(fn () => $intake->update($customer, new CustomerEdit(
            name: $validated['name'],
            phoneNumber: $validated['phone_number'] ?? null,
            address: $this->addressDetails($validated, $customer->primaryAddress),
            modifiedBy: $request->user(),
            driverId: isset($validated['repartidor_id']) ? (int) $validated['repartidor_id'] : null,
            preferences: $this->preferenceRows($validated, $request),
        )));

        return redirect()->back()->with('success', 'Cliente actualizado exitosamente');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<CustomerPreferenceChange>
     */
    private function preferenceRows(array $validated, Request $request): array
    {
        return array_values(array_map(
            fn (array $row): CustomerPreferenceChange => CustomerPreferenceController::change($row, $request),
            $validated['preferences'] ?? [],
        ));
    }

    /**
     * The web sets the neighborhood only; the coordinates come from the app, so the current ones are kept.
     * A request without the neighborhood field leaves the stored location untouched.
     *
     * @param  array<string, mixed>  $validated
     */
    private function addressDetails(array $validated, ?CustomerAddress $current): CustomerAddressDetails
    {
        return new CustomerAddressDetails(
            street: $validated['street'],
            apartment: $validated['apartment'] ?? null,
            floor: $validated['floor'] ?? null,
            visualReference: $validated['visual_reference'] ?? null,
            location: array_key_exists('neighborhood', $validated) ? new AddressLocation(
                neighborhood: $validated['neighborhood'],
                latitude: $current?->latitude,
                longitude: $current?->longitude,
            ) : null,
        );
    }
}
