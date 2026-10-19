<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContainerMovement;
use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerAgreement;
use App\Models\CustomerContainerBalance;
use App\Models\Driver;
use App\Models\IdempotencyRecord;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitOutcome;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    private function resolveDriver(Request $request): Driver
    {
        /** @var User $user */
        $user = $request->user();
        $driver = $user->driver;

        if (! $driver || ! $driver->enabled) {
            abort(403, 'Authenticated user is not an active driver.');
        }

        return $driver;
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        $today = now()->toDateString();

        /** @var Route|null $route */
        $route = Route::query()
            ->where('assigned_driver_id', $driver->id)
            ->where(function ($q) use ($today): void {
                $q->whereDate('date', $today)
                    ->orWhere('status', 'IN_PROGRESS');
            })
            ->orderByRaw("CASE WHEN status = 'IN_PROGRESS' THEN 1 WHEN date = '{$today}' THEN 2 ELSE 3 END")
            ->with([
                'stops.customer.addresses',
                'stops.customer.preferences',
                'stops.customer.agreements.product',
                'stops.customer.containerBalances.containerType',
                'stops.address',
                'inventories.product',
            ])
            ->first();

        $customers = Customer::query()
            ->where('driver_id', $driver->id)
            ->where('enabled', true)
            ->with([
                'addresses',
                'preferences',
                'agreements.product',
                'containerBalances.containerType',
            ])
            ->get();

        $products = Product::query()
            ->where('enabled', true)
            ->get();

        $containerTypes = ContainerType::query()
            ->where('enabled', true)
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->where('enabled', true)
            ->get();

        $visitOutcomes = VisitOutcome::query()
            ->where('enabled', true)
            ->get();

        return response()->json([
            'data' => [
                'route' => $route,
                'stops' => $route?->stops ?? [],
                'truck_inventory' => $route?->inventories ?? [],
                'customers' => $customers,
                'products' => $products,
                'container_types' => $containerTypes,
                'payment_methods' => $paymentMethods,
                'visit_outcomes' => $visitOutcomes,
                'server_time' => now()->toIso8601String(),
            ],
            'message' => null,
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $updatedSince = $request->filled('updated_since')
            ? Carbon::parse($request->string('updated_since')->toString())
            : now()->startOfDay();

        $routes = Route::query()
            ->where('assigned_driver_id', $driver->id)
            ->where('updated_at', '>', $updatedSince)
            ->with('inventories.product')
            ->get();

        $routeStops = RouteStop::query()
            ->whereHas('route', fn ($q) => $q->where('assigned_driver_id', $driver->id))
            ->where('updated_at', '>', $updatedSince)
            ->with(['address', 'customer'])
            ->get();

        $customers = Customer::query()
            ->where('driver_id', $driver->id)
            ->where('updated_at', '>', $updatedSince)
            ->with(['addresses', 'preferences', 'agreements.product', 'containerBalances.containerType'])
            ->get();

        $customerAddresses = CustomerAddress::query()
            ->whereHas('customer', fn ($q) => $q->where('driver_id', $driver->id))
            ->where('updated_at', '>', $updatedSince)
            ->get();

        $customerAgreements = CustomerAgreement::query()
            ->whereHas('customer', fn ($q) => $q->where('driver_id', $driver->id))
            ->where('updated_at', '>', $updatedSince)
            ->get();

        $products = Product::query()
            ->where('updated_at', '>', $updatedSince)
            ->get();

        $routeInventories = RouteInventory::query()
            ->whereHas('route', fn ($q) => $q->where('assigned_driver_id', $driver->id))
            ->where('updated_at', '>', $updatedSince)
            ->with('product')
            ->get();

        return response()->json([
            'data' => [
                'routes' => $routes,
                'route_stops' => $routeStops,
                'customers' => $customers,
                'customer_addresses' => $customerAddresses,
                'customer_agreements' => $customerAgreements,
                'products' => $products,
                'route_inventories' => $routeInventories,
                'server_time' => now()->toIso8601String(),
            ],
            'message' => null,
        ]);
    }

    public function syncRouteVisits(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'visits' => ['required', 'array'],
            'visits.*.uuid' => ['required', 'string'],
            'visits.*.customer_id' => ['required', 'integer'],
            'visits.*.route_stop_id' => ['nullable', 'integer'],
            'visits.*.outcome_id' => ['nullable', 'integer'],
            'visits.*.status' => ['nullable', 'string'],
            'visits.*.arrived_at' => ['nullable', 'string'],
            'visits.*.latitude' => ['nullable', 'numeric'],
            'visits.*.longitude' => ['nullable', 'numeric'],
            'visits.*.altitude' => ['nullable', 'numeric'],
            'visits.*.notes' => ['nullable', 'string'],
            'visits.*.container_movements' => ['nullable', 'array'],
            'visits.*.container_movements.*.uuid' => ['required', 'string'],
            'visits.*.container_movements.*.container_type_id' => ['required', 'integer'],
            'visits.*.container_movements.*.quantity' => ['required', 'integer'],
            'visits.*.sales' => ['nullable', 'array'],
            'visits.*.sales.*.uuid' => ['required', 'string'],
            'visits.*.sales.*.total' => ['required', 'numeric'],
            'visits.*.sales.*.details' => ['nullable', 'array'],
            'visits.*.sales.*.details.*.product_id' => ['required', 'integer'],
            'visits.*.sales.*.details.*.quantity' => ['required', 'numeric'],
            'visits.*.sales.*.details.*.unit_price' => ['required', 'numeric'],
            'visits.*.payments' => ['nullable', 'array'],
            'visits.*.payments.*.uuid' => ['required', 'string'],
            'visits.*.payments.*.amount' => ['required', 'numeric'],
            'visits.*.payments.*.payment_method_id' => ['required', 'integer'],
        ]);

        /** @var array<int, array<string, mixed>> $visits */
        $visits = $request->input('visits', []);

        $processedVisits = DB::transaction(function () use ($visits, $user, $driver): int {
            $processedCount = 0;

            foreach ($visits as $visitData) {
                $visitUuid = (string) $visitData['uuid'];
                $alreadyExists = IdempotencyRecord::query()->where('uuid', $visitUuid)->exists();

                if ($alreadyExists) {
                    continue;
                }

                $visit = Visit::create([
                    'route_stop_id' => $visitData['route_stop_id'] ?? null,
                    'customer_id' => $visitData['customer_id'],
                    'driver_id' => $driver->id,
                    'outcome_id' => $visitData['outcome_id'] ?? null,
                    'arrived_at' => $visitData['arrived_at'] ?? now(),
                    'latitude' => $visitData['latitude'] ?? null,
                    'longitude' => $visitData['longitude'] ?? null,
                    'altitude' => $visitData['altitude'] ?? null,
                    'status' => $visitData['status'] ?? 'FINISHED',
                    'notes' => $visitData['notes'] ?? null,
                    'created_by' => $user->id,
                    'enabled' => true,
                ]);

                IdempotencyRecord::create([
                    'uuid' => $visitUuid,
                    'entity_type' => 'visit',
                    'user_id' => $user->id,
                ]);

                $processedCount++;

                // Container movements
                foreach ($visitData['container_movements'] ?? [] as $cmData) {
                    $cmUuid = (string) $cmData['uuid'];
                    if (IdempotencyRecord::query()->where('uuid', $cmUuid)->exists()) {
                        continue;
                    }

                    $direction = (int) ($cmData['direction'] ?? 1);
                    $quantity = (int) $cmData['quantity'];

                    $movement = ContainerMovement::create([
                        'customer_id' => $visit->customer_id,
                        'container_type_id' => $cmData['container_type_id'],
                        'visit_id' => $visit->id,
                        'movement_type' => $cmData['movement_type'] ?? 'DELIVERED',
                        'quantity' => $quantity,
                        'direction' => $direction,
                        'reason' => $cmData['reason'] ?? null,
                        'created_by' => $user->id,
                        'enabled' => true,
                    ]);

                    IdempotencyRecord::create([
                        'uuid' => $cmUuid,
                        'entity_type' => 'container_movement',
                        'user_id' => $user->id,
                    ]);

                    /** @var CustomerContainerBalance $balance */
                    $balance = CustomerContainerBalance::firstOrNew([
                        'customer_id' => $visit->customer_id,
                        'container_type_id' => $cmData['container_type_id'],
                    ]);
                    $balance->balance = ((int) ($balance->balance ?? 0)) + ($quantity * $direction);
                    $balance->last_movement_id = $movement->id;
                    $balance->created_by ??= $user->id;
                    $balance->save();
                }

                // Sales
                $saleMap = [];
                foreach ($visitData['sales'] ?? [] as $saleData) {
                    $saleUuid = (string) $saleData['uuid'];
                    if (IdempotencyRecord::query()->where('uuid', $saleUuid)->exists()) {
                        continue;
                    }

                    $sale = Sale::create([
                        'customer_id' => $visit->customer_id,
                        'visit_id' => $visit->id,
                        'date' => now(),
                        'total' => $saleData['total'],
                        'status' => 'confirmed',
                        'is_credit_sale' => (bool) ($saleData['is_credit_sale'] ?? false),
                        'notes' => $saleData['notes'] ?? null,
                        'created_by' => $user->id,
                        'enabled' => true,
                    ]);

                    foreach ($saleData['details'] ?? [] as $detailData) {
                        SaleDetail::create([
                            'sale_id' => $sale->id,
                            'product_id' => $detailData['product_id'],
                            'customer_agreement_id' => $detailData['customer_agreement_id'] ?? null,
                            'quantity' => $detailData['quantity'],
                            'unit_price' => $detailData['unit_price'],
                        ]);

                        if (! empty($visit->route_stop_id)) {
                            /** @var RouteStop|null $routeStop */
                            $routeStop = RouteStop::find($visit->route_stop_id);
                            if ($routeStop) {
                                /** @var RouteInventory|null $inventory */
                                $inventory = RouteInventory::query()
                                    ->where('route_id', $routeStop->route_id)
                                    ->where('product_id', $detailData['product_id'])
                                    ->first();
                                if ($inventory) {
                                    $inventory->decrement('current_quantity', (float) $detailData['quantity']);
                                }
                            }
                        }
                    }

                    IdempotencyRecord::create([
                        'uuid' => $saleUuid,
                        'entity_type' => 'sale',
                        'user_id' => $user->id,
                    ]);

                    $saleMap[$saleUuid] = $sale;
                }

                // Payments
                foreach ($visitData['payments'] ?? [] as $payData) {
                    $payUuid = (string) $payData['uuid'];
                    if (IdempotencyRecord::query()->where('uuid', $payUuid)->exists()) {
                        continue;
                    }

                    $transaction = Transaction::create([
                        'type' => 'IN',
                        'amount' => $payData['amount'],
                    ]);

                    $saleId = null;
                    if (! empty($payData['sale_uuid']) && isset($saleMap[$payData['sale_uuid']])) {
                        $saleId = $saleMap[$payData['sale_uuid']]->id;
                    }

                    Payment::create([
                        'transaction_id' => $transaction->id,
                        'customer_id' => $visit->customer_id,
                        'visit_id' => $visit->id,
                        'sale_id' => $saleId,
                        'payment_method_id' => $payData['payment_method_id'],
                        'amount' => $payData['amount'],
                        'received_by' => $user->id,
                        'paid_at' => now(),
                        'status' => 'CONFIRMED',
                    ]);

                    IdempotencyRecord::create([
                        'uuid' => $payUuid,
                        'entity_type' => 'payment',
                        'user_id' => $user->id,
                    ]);
                }
            }

            return $processedCount;
        });

        return response()->json([
            'data' => [
                'processed_visits' => $processedVisits,
                'synced_at' => now()->toIso8601String(),
            ],
            'message' => 'Route visits synchronized successfully',
        ]);
    }
    public function syncCustomers(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'customers' => ['required', 'array'],
            'customers.*.uuid' => ['required', 'string'],
            'customers.*.server_id' => ['nullable', 'integer'],
            'customers.*.name' => ['required', 'string'],
            'customers.*.phone_number' => ['nullable', 'string'],
            'customers.*.address' => ['required', 'array'],
            'customers.*.address.street' => ['required', 'string'],
            'customers.*.address.apartment' => ['nullable', 'string'],
            'customers.*.address.floor' => ['nullable', 'integer'],
            'customers.*.address.visual_reference' => ['nullable', 'string'],
            'customers.*.address.neighborhood' => ['nullable', 'string'],
            'customers.*.address.latitude' => ['nullable', 'numeric'],
            'customers.*.address.longitude' => ['nullable', 'numeric'],
        ]);

        /** @var array<int, array<string, mixed>> $customersData */
        $customersData = $request->input('customers', []);
        $createdCustomers = [];

        $processedCount = DB::transaction(function () use ($customersData, $user, $driver, &$createdCustomers): int {
            $count = 0;

            foreach ($customersData as $customerData) {
                $uuid = (string) $customerData['uuid'];
                $serverId = $customerData['server_id'] ?? null;
                $alreadyExists = IdempotencyRecord::query()->where('uuid', $uuid)->exists();

                if ($alreadyExists) {
                    continue;
                }

                $customer = null;
                if ($serverId) {
                    $customer = Customer::find($serverId);
                }
                if (! $customer && $uuid) {
                    $customer = Customer::where('client_uuid', $uuid)->first();
                }

                if ($customer) {
                    $customer->update([
                        'name' => $customerData['name'],
                        'phone_number' => $customerData['phone_number'] ?? null,
                        'last_modified_by' => $user->id,
                    ]);

                    $addressData = $customerData['address'];
                    if ($customer->primaryAddress) {
                        $customer->primaryAddress->update([
                            'street' => $addressData['street'],
                            'apartment' => $addressData['apartment'] ?? null,
                            'floor' => $addressData['floor'] ?? null,
                            'visual_reference' => $addressData['visual_reference'] ?? null,
                            'neighborhood' => $addressData['neighborhood'] ?? null,
                            'latitude' => $addressData['latitude'] ?? null,
                            'longitude' => $addressData['longitude'] ?? null,
                            'last_modified_by' => $user->id,
                        ]);
                    }
                } else {
                    $customer = Customer::create([
                        'driver_id' => $driver->id,
                        'client_uuid' => $uuid,
                        'name' => $customerData['name'],
                        'phone_number' => $customerData['phone_number'] ?? null,
                        'created_by' => $user->id,
                        'enabled' => true,
                    ]);

                    $addressData = $customerData['address'];
                    $address = CustomerAddress::create([
                        'customer_id' => $customer->id,
                        'street' => $addressData['street'],
                        'apartment' => $addressData['apartment'] ?? null,
                        'floor' => $addressData['floor'] ?? null,
                        'visual_reference' => $addressData['visual_reference'] ?? null,
                        'neighborhood' => $addressData['neighborhood'] ?? null,
                        'latitude' => $addressData['latitude'] ?? null,
                        'longitude' => $addressData['longitude'] ?? null,
                        'is_primary' => true,
                        'created_by' => $user->id,
                        'enabled' => true,
                    ]);

                    $createdCustomers[] = [
                        'uuid' => $uuid,
                        'server_id' => $customer->id,
                        'address_server_id' => $address->id,
                    ];
                }

                IdempotencyRecord::create([
                    'uuid' => $uuid,
                    'entity_type' => 'customer',
                    'user_id' => $user->id,
                ]);

                $count++;
            }

            return $count;
        });

        return response()->json([
            'data' => [
                'processed_customers' => $processedCount,
                'created_customers' => $createdCustomers,
                'synced_at' => now()->toIso8601String(),
            ],
            'message' => 'Customers synchronized successfully',
        ]);
    }
}
