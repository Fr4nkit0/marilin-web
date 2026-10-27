<?php

declare(strict_types=1);

use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAgreement;
use App\Models\CustomerBalance;
use App\Models\CustomerContainerBalance;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 10:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->driverUser = User::factory()->create();
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $this->bidon = Product::factory()->create(['name' => 'Bidón 20L', 'price' => 6500, 'enabled' => true]);
    $this->soda = Product::factory()->create(['name' => 'Soda', 'price' => 1000, 'enabled' => true]);
    $this->cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo', 'enabled' => true]);
    $this->container = ContainerType::factory()->create(['code' => 'BIDON_20L', 'enabled' => true]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-22', 'status' => 'IN_PROGRESS']);
    RouteInventory::factory()->create(['route_id' => $this->route->id, 'product_id' => $this->bidon->id, 'current_quantity' => 50]);
});

/**
 * @return array<string, mixed>
 */
function homeSale(array $overrides = []): array
{
    return [
        'lines' => [['product_id' => test()->bidon->id, 'quantity' => 2], ['product_id' => test()->soda->id, 'quantity' => 3]],
        ...$overrides,
    ];
}

function homeBalance(): float
{
    return (float) (CustomerBalance::query()->where('customer_id', test()->customer->id)->value('current_balance') ?? 0);
}

function nothingWasWritten(): void
{
    expect(Sale::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(CustomerAccountEntry::count())->toBe(0)
        ->and(CustomerContainerBalance::count())->toBe(0)
        ->and(homeBalance())->toBe(0.0);
}

test('the admin records a sale at home priced by the server', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.home-sales.store', $this->customer), homeSale(['notes' => 'Vino a buscarlo']))
        ->assertRedirect(route('customers.show', $this->customer));

    $sale = Sale::query()->with('details')->sole();
    expect($sale->visit_id)->toBeNull()
        ->and($sale->created_by)->toBe($this->admin->id)
        ->and((float) $sale->total)->toBe(16000.0)
        ->and($sale->notes)->toBe('Vino a buscarlo')
        ->and($sale->is_credit_sale)->toBeFalse()
        ->and($sale->needs_review)->toBeFalse()
        ->and($sale->date->toDateString())->toBe('2026-10-22')
        ->and($sale->details)->toHaveCount(2)
        ->and(CustomerAccountEntry::query()->where('sale_id', $sale->id)->sole()->direction)->toBe(1)
        ->and(homeBalance())->toBe(16000.0);
});

test('an agreement valid that day sets the price and an expired one falls back to the list price', function (): void {
    $valid = CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->bidon->id, 'unit_price' => 5000, 'valid_from' => '2026-10-01', 'valid_to' => null]);
    CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->soda->id, 'unit_price' => 10, 'valid_from' => '2026-09-01', 'valid_to' => '2026-09-30']);

    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale());

    $sale = Sale::query()->with('details')->sole();
    $bidonLine = $sale->details->firstWhere('product_id', $this->bidon->id);
    $sodaLine = $sale->details->firstWhere('product_id', $this->soda->id);
    expect((float) $bidonLine->unit_price)->toBe(5000.0)
        ->and($bidonLine->customer_agreement_id)->toBe($valid->id)
        ->and((float) $sodaLine->unit_price)->toBe(1000.0)
        ->and($sodaLine->customer_agreement_id)->toBeNull()
        ->and((float) $sale->total)->toBe(13000.0);
});

test('a price or total sent by the client is ignored', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale([
        'total' => 1,
        'lines' => [['product_id' => $this->bidon->id, 'quantity' => 2, 'unit_price' => 1, 'total' => 1]],
    ]))->assertSessionHasNoErrors();

    $sale = Sale::query()->with('details')->sole();
    expect((float) $sale->total)->toBe(13000.0)
        ->and((float) $sale->details->sole()->unit_price)->toBe(6500.0);
});

test('a credit sale is flagged as credit', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale(['is_credit_sale' => true]));

    expect(Sale::query()->sole()->is_credit_sale)->toBeTrue();
});

test('a sale at home does not discount the stock of the route of the customer driver', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale());

    expect((float) RouteInventory::query()->where('route_id', $this->route->id)->sole()->current_quantity)->toBe(50.0);
});

test('containers in the same submission update the container balance', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale([
        'containers' => [
            ['container_type_id' => $this->container->id, 'type' => 'DELIVERED', 'quantity' => 5],
            ['container_type_id' => $this->container->id, 'type' => 'RETURNED', 'quantity' => 2],
        ],
    ]));

    expect(CustomerContainerBalance::query()->where('customer_id', $this->customer->id)->sole()->balance)->toBe(3)
        ->and($this->customer->containerMovements()->count())->toBe(2)
        ->and($this->customer->containerMovements()->whereNotNull('visit_id')->count())->toBe(0);
});

test('a payment at home lowers the balance without visit nor sale', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale());

    $this->actingAs($this->admin)
        ->post(route('customers.home-payments.store', $this->customer), ['amount' => '4000.50', 'payment_method_id' => $this->cash->id])
        ->assertRedirect(route('customers.show', $this->customer));

    $payment = Payment::query()->sole();
    expect($payment->visit_id)->toBeNull()
        ->and($payment->sale_id)->toBeNull()
        ->and($payment->received_by)->toBe($this->admin->id)
        ->and($payment->needs_review)->toBeFalse()
        ->and(homeBalance())->toBe(11999.5);
});

test('a driver user cannot record a sale or a payment at home', function (): void {
    $this->actingAs($this->driverUser)
        ->post(route('customers.home-sales.store', $this->customer), homeSale())
        ->assertForbidden();
    $this->actingAs($this->driverUser)
        ->post(route('customers.home-payments.store', $this->customer), ['amount' => 100, 'payment_method_id' => $this->cash->id])
        ->assertForbidden();

    nothingWasWritten();
});

test('invalid sales return validation errors and write nothing', function (array $payload, string $field): void {
    $this->actingAs($this->admin)
        ->from(route('customers.show', $this->customer))
        ->post(route('customers.home-sales.store', $this->customer), $payload)
        ->assertSessionHasErrors($field);

    nothingWasWritten();
})->with([
    'no lines' => [fn () => ['lines' => []], 'lines'],
    'quantity zero' => [fn () => ['lines' => [['product_id' => 1, 'quantity' => 0]]], 'lines.0.quantity'],
    'fractional quantity' => [fn () => ['lines' => [['product_id' => 1, 'quantity' => 1.5]]], 'lines.0.quantity'],
    'unknown product' => [fn () => ['lines' => [['product_id' => 999999, 'quantity' => 1]]], 'lines.0.product_id'],
    'container quantity zero' => [fn () => ['lines' => [['product_id' => 1, 'quantity' => 1]], 'containers' => [['container_type_id' => 1, 'type' => 'DELIVERED', 'quantity' => 0]]], 'containers.0.quantity'],
    'container type not allowed' => [fn () => ['lines' => [['product_id' => 1, 'quantity' => 1]], 'containers' => [['container_type_id' => 1, 'type' => 'ADJUSTMENT', 'quantity' => 1]]], 'containers.0.type'],
]);

test('invalid payments return validation errors and write nothing', function (array $payload, string $field): void {
    $this->actingAs($this->admin)
        ->from(route('customers.show', $this->customer))
        ->post(route('customers.home-payments.store', $this->customer), $payload)
        ->assertSessionHasErrors($field);

    nothingWasWritten();
})->with([
    'amount zero' => [fn () => ['amount' => 0, 'payment_method_id' => 1], 'amount'],
    'negative amount' => [fn () => ['amount' => -5, 'payment_method_id' => 1], 'amount'],
    'unknown method' => [fn () => ['amount' => 10, 'payment_method_id' => 999999], 'payment_method_id'],
]);

test('a disabled payment method is rejected', function (): void {
    $disabled = PaymentMethod::factory()->create(['enabled' => false]);

    $this->actingAs($this->admin)
        ->post(route('customers.home-payments.store', $this->customer), ['amount' => 10, 'payment_method_id' => $disabled->id])
        ->assertSessionHasErrors('payment_method_id');

    expect(Payment::count())->toBe(0);
});

test('a sale at home is not part of the cash close of the driver of the customer', function (): void {
    $this->travelTo('2026-10-22 18:00:00');
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale(['containers' => [['container_type_id' => $this->container->id, 'type' => 'DELIVERED', 'quantity' => 1]]]));
    $this->actingAs($this->admin)->post(route('customers.home-payments.store', $this->customer), ['amount' => 100, 'payment_method_id' => $this->cash->id]);

    $settlement = null;
    $this->actingAs($this->admin)
        ->get(route('cash-close.index', ['repartidor_id' => $this->driver->id, 'date' => '2026-10-22']))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$settlement): void {
            $settlement = $page->toArray()['props']['settlement'];
        });

    expect(Sale::count())->toBe(1)
        ->and(Payment::count())->toBe(1)
        ->and($settlement['totalSales'])->toEqual(0)
        ->and($settlement['cashAmount'])->toEqual(0)
        ->and($settlement['debtCollected'])->toEqual(0)
        ->and($settlement['containersDelivered'])->toBe(0);
});

test('the customer page exposes the home operations only to the admin, with resolved read-only prices', function (): void {
    CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->bidon->id, 'unit_price' => 5000, 'valid_from' => '2026-10-01', 'valid_to' => null]);

    $this->actingAs($this->admin)
        ->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canRecordAtHome', true)
            ->where('homeProducts.0.id', $this->bidon->id)
            ->where('homeProducts.0.unitPrice', 5000)
            ->where('homeProducts.0.source', 'agreement')
            ->where('homeProducts.1.unitPrice', 1000)
            ->where('homeProducts.1.source', 'list')
            ->has('paymentMethods', 1)
            ->where('containerTypes', fn ($types) => collect($types)->pluck('id')->contains($this->container->id)));

    $this->actingAs($this->driverUser)
        ->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page->where('canRecordAtHome', false)->where('homeProducts', [])->where('paymentMethods', [])->where('containerTypes', []));
});
