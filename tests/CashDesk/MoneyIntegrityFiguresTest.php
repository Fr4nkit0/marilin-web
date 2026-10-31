<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
use App\Street\Sales\RecordSale;
use App\Street\Sales\SaleLine;
use App\Street\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-30 20:00:00');
    $this->actingAs(User::factory()->superUser()->create());

    $this->driverUser = User::factory()->create(['name' => 'Juan']);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'name' => 'Kiosco']);
    $this->route = Route::factory()->create([
        'assigned_driver_id' => $this->driver->id,
        'date' => '2026-10-30',
        'status' => 'COMPLETED',
        'started_at' => '2026-10-30 08:00:00',
        'completed_at' => '2026-10-30 16:00:00',
    ]);
    $this->water = Product::factory()->create(['name' => 'Bidón 20L']);
    RouteInventory::factory()->create(['route_id' => $this->route->id, 'product_id' => $this->water->id, 'current_quantity' => 10]);
});

/**
 * @return array<string, mixed>
 */
function pageProps(string $routeName, string $component, array $query): array
{
    $props = [];
    test()->get(route($routeName, $query))->assertOk()->assertInertia(function (Assert $page) use ($component, &$props): void {
        $page->component($component);
        $props = $page->toArray()['props'];
    });

    return $props;
}

function streetSaleAt(string $date, ?string $receivedAt = null): Sale
{
    if ($receivedAt !== null) {
        test()->travelTo($receivedAt);
    }

    $sale = app(StreetOperation::class)->recordSale(new RecordSale(
        customer: test()->customer,
        visit: null,
        driver: test()->driver,
        date: Carbon::parse($date),
        lines: [new SaleLine(test()->water->id, 1, '700.00')],
        total: '700.00',
        isCreditSale: false,
        notes: null,
        recordedBy: test()->driverUser,
    ));
    test()->travelTo('2026-10-30 20:00:00');

    return $sale;
}

test('a sale made after the route was completed still discounts its stock and the cash close shows it apart as post-close', function (): void {
    $before = streetSaleAt('2026-10-30 15:00:00');
    // Made offline before the close, received after it: not post-close, the driver sold it on the open route.
    $late = streetSaleAt('2026-10-30 15:30:00', receivedAt: '2026-10-30 17:30:00');
    $after = streetSaleAt('2026-10-30 17:00:00');

    $settlement = pageProps('cash-close.index', 'CashDesk/Index', ['repartidor_id' => $this->driver->id, 'date' => '2026-10-30'])['settlement'];

    expect((float) RouteInventory::query()->where('route_id', $this->route->id)->value('current_quantity'))->toBe(7.0)
        ->and($settlement['totalSales'])->toEqual(2100)
        ->and($settlement['postCloseSales'])->toEqual(700)
        ->and($settlement['details']['postClose'])->toEqual([[
            'id' => $after->id,
            'customer' => 'Kiosco',
            'at' => '2026-10-30 17:00',
            'amount' => 700.0,
            'credit' => false,
            'origin' => 'loose',
        ]])
        ->and(collect($settlement['details']['postClose'])->pluck('id'))->not->toContain($before->id)
        ->and(collect($settlement['details']['postClose'])->pluck('id'))->not->toContain($late->id);
});

test('a route not completed has no post-close sales', function (): void {
    $this->route->update(['status' => 'IN_PROGRESS', 'completed_at' => null]);
    streetSaleAt('2026-10-30 17:00:00');

    $settlement = pageProps('cash-close.index', 'CashDesk/Index', ['repartidor_id' => $this->driver->id, 'date' => '2026-10-30'])['settlement'];

    expect($settlement['postCloseSales'])->toEqual(0)
        ->and($settlement['details']['postClose'])->toBe([]);
});

test('cobrado is the day\'s confirmed payments of every method and the panel, sales and payments lists and cash close agree', function (): void {
    $cash = PaymentMethod::factory()->efectivo()->create();
    $transfer = PaymentMethod::factory()->transferencia()->create();
    $card = PaymentMethod::factory()->create(['code' => 'CREDIT_CARD', 'name' => 'Tarjeta']);
    $payment = fn (int $method, float $amount, string $status = 'CONFIRMED'): Payment => Payment::factory()->create([
        'customer_id' => $this->customer->id,
        'received_by' => $this->driverUser->id,
        'paid_at' => '2026-10-30 11:00:00',
        'payment_method_id' => $method,
        'amount' => $amount,
        'status' => $status,
    ]);
    $payment($cash->id, 300);
    $payment($transfer->id, 70);
    $payment($card->id, 50);
    $payment($cash->id, 999, 'CANCELLED');
    // Sales on account and at the counter: what was sold is not what was collected.
    Sale::factory()->create(['customer_id' => $this->customer->id, 'created_by' => $this->driverUser->id, 'date' => '2026-10-30 10:00:00', 'total' => 1000, 'is_credit_sale' => false]);
    Sale::factory()->create(['customer_id' => $this->customer->id, 'created_by' => $this->driverUser->id, 'date' => '2026-10-30 10:30:00', 'total' => 500, 'is_credit_sale' => true]);

    $filter = ['date' => '2026-10-30', 'repartidor_id' => $this->driver->id];
    $dashboard = pageProps('dashboard', 'Dashboard/Index', ['date' => '2026-10-30'])['metrics'];
    $sales = pageProps('sales.index', 'Street/Sales/Index', $filter)['metrics'];
    $payments = pageProps('payments.index', 'Street/Payments/Index', $filter)['metrics'];
    $settlement = pageProps('cash-close.index', 'CashDesk/Index', $filter)['settlement'];

    expect($dashboard['kpis']['totalCollected'])->toEqual(420)
        ->and($dashboard['repartidores'][0]['totalCollected'])->toEqual(420)
        ->and($sales['collectedAmount'])->toEqual(420)
        ->and($sales['immediateSalesAmount'])->toEqual(1000)
        ->and($payments['totalCollected'])->toEqual(420)
        ->and($payments['cardTotal'])->toEqual(50)
        ->and($settlement['totalCollected'])->toEqual(420)
        ->and($settlement['cardAmount'])->toEqual(50)
        ->and($settlement['details']['card'])->toHaveCount(1)
        ->and(collect($dashboard['details']['payments'])->sum('amount'))->toEqual(420);
});
