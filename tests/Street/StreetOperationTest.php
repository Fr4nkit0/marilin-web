<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Containers\MoveContainers;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\RecordPayment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\RecordSale;
use App\Street\Sales\SaleLine;
use App\Street\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->operation = app(StreetOperation::class);
    $this->user = User::factory()->create();
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $this->customer = Customer::factory()->create();
    $this->water = Product::factory()->create();
    $this->soda = Product::factory()->create();
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20']);
    foreach ([$this->water, $this->soda] as $product) {
        RouteInventory::factory()->create(['route_id' => $this->route->id, 'product_id' => $product->id, 'current_quantity' => 50]);
    }
});

function streetSaleOf(?Visit $visit = null, bool $credit = false, ?ReviewReason $reason = null): RecordSale
{
    return new RecordSale(
        customer: test()->customer,
        visit: $visit,
        driver: test()->driver,
        date: Carbon::parse('2026-10-20 10:00:00'),
        lines: [new SaleLine(test()->water->id, 3, '100.00'), new SaleLine(test()->soda->id, 2, '50.00')],
        total: '400.00',
        isCreditSale: $credit,
        notes: 'two products',
        recordedBy: test()->user,
        clientUuid: '6f1c2f3e-1b6a-4c55-9a53-3a5a2f0c9c11',
        reviewReasons: [$reason],
    );
}

function routeStockOf(Product $product, Route $route): float
{
    return (float) RouteInventory::query()->where('route_id', $route->id)->where('product_id', $product->id)->value('current_quantity');
}

test('a sale with a visit books the ledger and discounts the route of the visit', function (): void {
    $otherRoute = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-21']);
    $stop = RouteStop::factory()->create(['route_id' => $otherRoute->id, 'customer_id' => $this->customer->id]);
    $visit = Visit::factory()->create(['route_stop_id' => $stop->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id]);
    foreach ([$this->water, $this->soda] as $product) {
        RouteInventory::factory()->create(['route_id' => $otherRoute->id, 'product_id' => $product->id, 'current_quantity' => 10]);
    }

    $sale = $this->operation->recordSale(streetSaleOf($visit, credit: true, reason: ReviewReason::ForeignCustomer));

    expect($sale->details)->toHaveCount(2)
        ->and($sale->visit_id)->toBe($visit->id)
        ->and($sale->is_credit_sale)->toBeTrue()
        ->and($sale->needs_review)->toBeTrue()
        ->and($sale->review_reasons)->toBe([ReviewReason::ForeignCustomer->value])
        ->and(routeStockOf($this->water, $otherRoute))->toBe(7.0)
        ->and(routeStockOf($this->soda, $otherRoute))->toBe(8.0)
        ->and(routeStockOf($this->water, $this->route))->toBe(50.0);

    $entry = CustomerAccountEntry::query()->where('sale_id', $sale->id)->sole();
    expect($entry->entry_type)->toBe('SALE')
        ->and((float) $entry->amount)->toBe(400.0)
        ->and((float) CustomerBalance::query()->where('customer_id', $this->customer->id)->value('current_balance'))->toBe(400.0);
});

test('a sale without a visit discounts the route the driver has on the sale day', function (): void {
    $sale = $this->operation->recordSale(streetSaleOf());

    expect($sale->visit_id)->toBeNull()
        ->and($sale->needs_review)->toBeFalse()
        ->and(routeStockOf($this->water, $this->route))->toBe(47.0)
        ->and(routeStockOf($this->soda, $this->route))->toBe(48.0);
});

test('a sale of a product the route has no inventory row for leaves that row below zero', function (): void {
    $ice = Product::factory()->create();

    $this->operation->recordSale(new RecordSale(
        customer: $this->customer,
        visit: null,
        driver: $this->driver,
        date: Carbon::parse('2026-10-20 10:00:00'),
        lines: [new SaleLine($ice->id, 4, '100.00'), new SaleLine($this->water->id, 1, '100.00')],
        total: '500.00',
        isCreditSale: false,
        notes: null,
        recordedBy: $this->user,
    ));

    $row = RouteInventory::query()->where('route_id', $this->route->id)->where('product_id', $ice->id)->sole();
    expect((float) $row->current_quantity)->toBe(-4.0)
        ->and($row->enabled)->toBeTrue()
        ->and($row->created_by)->toBe($this->user->id)
        ->and(routeStockOf($this->water, $this->route))->toBe(49.0);
});

test('a sale with no matching route discounts nothing but still books the ledger', function (): void {
    $this->route->update(['date' => '2026-10-19']);

    $sale = $this->operation->recordSale(streetSaleOf());

    expect(routeStockOf($this->water, $this->route))->toBe(50.0)
        ->and(CustomerAccountEntry::query()->where('sale_id', $sale->id)->count())->toBe(1);
});

test('a sale with no driver and no visit discounts nothing', function (): void {
    $sale = $this->operation->recordSale(new RecordSale(
        customer: $this->customer,
        visit: null,
        driver: null,
        date: Carbon::parse('2026-10-20 10:00:00'),
        lines: [new SaleLine($this->water->id, 3, '100.00')],
        total: '300.00',
        isCreditSale: false,
        notes: null,
        recordedBy: $this->user,
    ));

    expect(routeStockOf($this->water, $this->route))->toBe(50.0)
        ->and($sale->client_uuid)->toBeNull()
        ->and((float) CustomerBalance::query()->where('customer_id', $this->customer->id)->value('current_balance'))->toBe(300.0);
});

test('a payment books a negative ledger entry', function (): void {
    $method = PaymentMethod::factory()->create();

    $payment = $this->operation->recordPayment(new RecordPayment(
        customer: $this->customer,
        visit: null,
        sale: null,
        paymentMethodId: $method->id,
        amount: '150.00',
        paidAt: Carbon::parse('2026-10-20 11:00:00'),
        receivedBy: $this->user,
        clientUuid: '0b4d5c0e-7d7a-4a53-8a47-0d0c9f4a7e22',
    ));

    $entry = CustomerAccountEntry::query()->where('payment_id', $payment->id)->sole();
    expect($payment->transaction_id)->not->toBeNull()
        ->and($payment->needs_review)->toBeFalse()
        ->and($entry->entry_type)->toBe('PAYMENT')
        ->and($entry->direction)->toBe(-1)
        ->and((float) CustomerBalance::query()->where('customer_id', $this->customer->id)->value('current_balance'))->toBe(-150.0);
});

test('container movements move the balance up on delivery and down on return and remember the last movement', function (): void {
    $type = ContainerType::factory()->create();
    $move = fn (ContainerMovementType $movementType, int $quantity) => $this->operation->moveContainers(new MoveContainers(
        customer: $this->customer,
        containerTypeId: $type->id,
        type: $movementType,
        quantity: $quantity,
        visit: null,
        recordedBy: $this->user,
    ));

    $move(ContainerMovementType::Delivered, 5);
    $returned = $move(ContainerMovementType::Returned, 2);

    $balance = CustomerContainerBalance::query()->where('customer_id', $this->customer->id)->where('container_type_id', $type->id)->sole();
    expect($balance->balance)->toBe(3)
        ->and($balance->last_movement_id)->toBe($returned->id)
        ->and($returned->direction)->toBe(-1);
});

test('a truck load adds to the route inventory through the module', function (): void {
    $this->operation->loadRoute($this->route, [$this->soda->id => 5, $this->water->id => 10], $this->user);

    expect(routeStockOf($this->water, $this->route))->toBe(60.0)
        ->and(routeStockOf($this->soda, $this->route))->toBe(55.0);
});
