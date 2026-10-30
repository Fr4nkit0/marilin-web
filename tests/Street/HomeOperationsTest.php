<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
use App\Street\Sales\Models\SaleDetail;
use App\Street\StreetOperation;
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
        'payment_method_id' => test()->cash->id,
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
        ->and(CustomerAccountEntry::query()->where('sale_id', $sale->id)->where('entry_type', 'SALE')->sole()->direction)->toBe(1);
});

test('a cash sale at home is paid on the spot with the chosen method, so the balance does not move', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.home-sales.store', $this->customer), homeSale())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.show', $this->customer));

    $sale = Sale::query()->sole();
    $payment = Payment::query()->sole();
    expect($payment->status)->toBe('CONFIRMED')
        ->and($payment->sale_id)->toBe($sale->id)
        ->and($payment->visit_id)->toBeNull()
        ->and($payment->payment_method_id)->toBe($this->cash->id)
        ->and((float) $payment->amount)->toBe(16000.0)
        ->and($payment->received_by)->toBe($this->admin->id)
        ->and($payment->paid_at->toDateTimeString())->toBe('2026-10-22 10:00:00')
        ->and(CustomerAccountEntry::query()->orderBy('id')->pluck('entry_type')->all())->toBe(['SALE', 'PAYMENT'])
        ->and(homeBalance())->toBe(0.0)
        ->and(app(DailyFigures::class)->forDay('2026-10-22', DayScope::house())->totalCollected)->toBe(16000.0);
});

test('a cash sale at home without a payment method is rejected and writes nothing', function (): void {
    $this->actingAs($this->admin)
        ->from(route('customers.show', $this->customer))
        ->post(route('customers.home-sales.store', $this->customer), homeSale([
            'payment_method_id' => null,
            'containers' => [['container_type_id' => $this->container->id, 'type' => 'DELIVERED', 'quantity' => 1]],
        ]))
        ->assertSessionHasErrors('payment_method_id');

    nothingWasWritten();
    expect(ContainerMovement::count())->toBe(0)
        ->and((float) RouteInventory::query()->where('route_id', $this->route->id)->sole()->current_quantity)->toBe(50.0);
});

test('a cash sale at home with an unknown or disabled payment method is rejected', function (): void {
    $disabled = PaymentMethod::factory()->create(['enabled' => false]);

    foreach ([$disabled->id, 999999] as $methodId) {
        $this->actingAs($this->admin)
            ->post(route('customers.home-sales.store', $this->customer), homeSale(['payment_method_id' => $methodId]))
            ->assertSessionHasErrors('payment_method_id');
    }

    nothingWasWritten();
});

test('a credit sale at home records no payment and the customer owes the total, even if a method is sent', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.home-sales.store', $this->customer), homeSale(['is_credit_sale' => true, 'payment_method_id' => null]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)
        ->post(route('customers.home-sales.store', $this->customer), homeSale(['is_credit_sale' => true, 'payment_method_id' => 999999]))
        ->assertSessionHasNoErrors();

    expect(Sale::count())->toBe(2)
        ->and(Payment::count())->toBe(0)
        ->and(CustomerAccountEntry::query()->pluck('entry_type')->all())->toBe(['SALE', 'SALE'])
        ->and(homeBalance())->toBe(32000.0);
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

test('containers in the same submission update the container balance and are linked to the home sale', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale([
        'containers' => [
            ['container_type_id' => $this->container->id, 'type' => 'DELIVERED', 'quantity' => 5],
            ['container_type_id' => $this->container->id, 'type' => 'RETURNED', 'quantity' => 2],
        ],
    ]));

    expect(CustomerContainerBalance::query()->where('customer_id', $this->customer->id)->sole()->balance)->toBe(3)
        ->and($this->customer->containerMovements()->count())->toBe(2)
        ->and($this->customer->containerMovements()->whereNotNull('visit_id')->count())->toBe(0)
        ->and($this->customer->containerMovements()->where('sale_id', Sale::query()->sole()->id)->count())->toBe(2);
});

test('a payment at home lowers the balance without visit nor sale', function (): void {
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale(['is_credit_sale' => true]));

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
    'zero with decimals' => [fn () => ['amount' => '0.00', 'payment_method_id' => 1], 'amount'],
    'three decimals' => [fn () => ['amount' => '10.001', 'payment_method_id' => 1], 'amount'],
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
        ->and(Payment::count())->toBe(2)
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

test('a guest is redirected to login on both home operations and nothing is written', function (): void {
    $this->post(route('customers.home-sales.store', $this->customer), homeSale())->assertRedirect(route('login'));
    $this->post(route('customers.home-payments.store', $this->customer), ['amount' => 100, 'payment_method_id' => $this->cash->id])->assertRedirect(route('login'));

    nothingWasWritten();
});

test('an agreement of another customer, another product or a future one never sets the price', function (): void {
    $other = Customer::factory()->create();
    CustomerAgreement::factory()->create(['customer_id' => $other->id, 'product_id' => $this->bidon->id, 'unit_price' => 1, 'valid_from' => '2026-10-01', 'valid_to' => null]);
    CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->soda->id, 'unit_price' => 2, 'valid_from' => '2026-10-01', 'valid_to' => null]);
    CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->bidon->id, 'unit_price' => 3, 'valid_from' => '2026-10-23', 'valid_to' => null]);

    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale(['lines' => [['product_id' => $this->bidon->id, 'quantity' => 1]]]));

    $line = Sale::query()->with('details')->sole()->details->sole();
    expect((float) $line->unit_price)->toBe(6500.0)
        ->and($line->customer_agreement_id)->toBeNull();
});

test('with several valid agreements the home sale uses the most recent valid_from, then the highest id', function (): void {
    $attributes = ['customer_id' => $this->customer->id, 'product_id' => $this->bidon->id, 'valid_to' => null];
    CustomerAgreement::factory()->create([...$attributes, 'unit_price' => 5000, 'valid_from' => '2026-10-15']);
    CustomerAgreement::factory()->create([...$attributes, 'unit_price' => 4000, 'valid_from' => '2026-10-01']);
    $winner = CustomerAgreement::factory()->create([...$attributes, 'unit_price' => 4500, 'valid_from' => '2026-10-15']);

    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale(['lines' => [['product_id' => $this->bidon->id, 'quantity' => 1]]]));

    $line = Sale::query()->with('details')->sole()->details->sole();
    expect((float) $line->unit_price)->toBe(4500.0)
        ->and($line->customer_agreement_id)->toBe($winner->id);
});

test('a failing container line rolls back the home sale and its ledger entry', function (): void {
    $this->partialMock(StreetOperation::class, function ($mock): void {
        $mock->shouldReceive('moveContainers')->andThrow(new RuntimeException('container failure'));
    });

    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale([
        'containers' => [['container_type_id' => $this->container->id, 'type' => 'DELIVERED', 'quantity' => 1]],
    ]))->assertServerError();

    nothingWasWritten();
});

test('the success message of a home operation reaches the next page as a shared flash, once', function (): void {
    $this->actingAs($this->admin)
        ->followingRedirects()
        ->post(route('customers.home-payments.store', $this->customer), ['amount' => '1000', 'payment_method_id' => $this->cash->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Customers/Show')
            ->where('flash.success', 'Cobro en casa registrado.'));

    $this->actingAs($this->admin)
        ->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page->where('flash.success', null));
});

test('the customer page names every ledger entry the same way, whatever recorded it', function (): void {
    $transfer = PaymentMethod::factory()->create(['code' => 'TRANSFER', 'name' => 'Transferencia', 'enabled' => true]);

    // At home: a cash sale books the sale and its payment.
    $this->actingAs($this->admin)->post(route('customers.home-sales.store', $this->customer), homeSale());

    // On the street, with the free text the sync or the seeder stored.
    $streetSale = Sale::factory()->create(['customer_id' => $this->customer->id, 'total' => 6500]);
    SaleDetail::factory()->create(['sale_id' => $streetSale->id, 'product_id' => $this->bidon->id, 'quantity' => 1, 'unit_price' => 6500]);
    CustomerAccountEntry::factory()->saleEntry()->create(['customer_id' => $this->customer->id, 'sale_id' => $streetSale->id, 'amount' => 6500, 'description' => 'Venta 1x Bidones 20L']);
    $streetPayment = Payment::factory()->create(['customer_id' => $this->customer->id, 'payment_method_id' => $transfer->id, 'sale_id' => null, 'amount' => 500]);
    CustomerAccountEntry::factory()->paymentEntry()->create(['customer_id' => $this->customer->id, 'payment_id' => $streetPayment->id, 'amount' => 500, 'description' => 'Cobro Transferencia']);
    // A cancellation keeps its own words.
    CustomerAccountEntry::factory()->create(['customer_id' => $this->customer->id, 'entry_type' => 'CREDIT_NOTE', 'direction' => -1, 'sale_id' => $streetSale->id, 'amount' => 6500, 'description' => 'Venta anulada por el repartidor: cliente ausente']);
    CustomerAccountEntry::factory()->create(['customer_id' => $this->customer->id, 'entry_type' => 'ADJUSTMENT', 'direction' => 1, 'amount' => 100, 'description' => 'Saldo deudor inicial registrado al dar de alta']);

    $this->actingAs($this->admin)
        ->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page->where('accountEntries', fn ($entries) => collect($entries)->pluck('concept')->all() === [
            'Saldo deudor inicial registrado al dar de alta',
            'Venta anulada por el repartidor: cliente ausente',
            'Cobro por transferencia',
            'Venta 1x Bidón 20L',
            'Cobro en efectivo',
            'Venta 2x Bidón 20L, 3x Soda',
        ]));
});
