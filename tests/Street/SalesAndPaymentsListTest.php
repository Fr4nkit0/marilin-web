<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Routes\Visits\Models\Visit;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->actingAs($this->admin);

    $this->cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo']);
    $this->transfer = PaymentMethod::factory()->create(['code' => 'TRANSFER', 'name' => 'Transferencia']);

    $this->juanUser = User::factory()->create(['name' => 'Juan']);
    $this->juan = Driver::factory()->create(['user_id' => $this->juanUser->id]);
    $this->anaUser = User::factory()->create(['name' => 'Ana']);
    $this->ana = Driver::factory()->create(['user_id' => $this->anaUser->id]);

    $this->juanVisit = Visit::factory()->create(['driver_id' => $this->juan->id]);
    $this->anaVisit = Visit::factory()->create(['driver_id' => $this->ana->id]);

    $this->hotel = Customer::factory()->create(['name' => 'Hotel']);

    // Juan: one sale on his visit (loaded by the admin) and one credit sale he recorded without a visit.
    $this->juanVisitSale = Sale::factory()->create(['visit_id' => $this->juanVisit->id, 'created_by' => $this->admin->id, 'customer_id' => $this->hotel->id, 'total' => 1000, 'date' => '2026-10-20 10:00:00']);
    $this->juanLooseSale = Sale::factory()->creditSale()->create(['visit_id' => null, 'created_by' => $this->juanUser->id, 'total' => 500, 'date' => '2026-10-20 11:00:00']);
    // Ana and the house.
    $this->anaSale = Sale::factory()->create(['visit_id' => $this->anaVisit->id, 'created_by' => $this->anaUser->id, 'total' => 200, 'date' => '2026-10-20 12:00:00']);
    $this->houseSale = Sale::factory()->create(['visit_id' => null, 'created_by' => $this->admin->id, 'total' => 80, 'date' => '2026-10-20 13:00:00']);

    $payment = fn (array $attributes): Payment => Payment::factory()->create([
        'paid_at' => '2026-10-20 10:00:00',
        'status' => 'CONFIRMED',
        'payment_method_id' => $this->cash->id,
        'sale_id' => null,
        ...$attributes,
    ]);
    $this->juanVisitPayment = $payment(['visit_id' => $this->juanVisit->id, 'received_by' => $this->juanUser->id, 'amount' => 300]);
    $this->juanLoosePayment = $payment(['visit_id' => null, 'received_by' => $this->juanUser->id, 'amount' => 70, 'payment_method_id' => $this->transfer->id]);
    $this->anaPayment = $payment(['visit_id' => $this->anaVisit->id, 'received_by' => $this->anaUser->id, 'amount' => 25]);
    $this->housePayment = $payment(['visit_id' => null, 'received_by' => $this->admin->id, 'amount' => 40, 'payment_method_id' => $this->transfer->id]);
});

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function listProps(string $routeName, string $component, array $query = []): array
{
    $props = [];
    test()->get(route($routeName, $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($component, &$props): void {
            $page->component($component);
            $props = $page->toArray()['props'];
        });

    return $props;
}

/**
 * @param  array<string, mixed>  $props
 * @return list<int>
 */
function listedIds(array $props, string $key): array
{
    $ids = array_column($props[$key]['data'], 'id');
    sort($ids);

    return $ids;
}

test('filtering sales by a driver lists the sales of their visits and the ones they recorded without a visit, and the totals are theirs', function (): void {
    $props = listProps('sales.index', 'Street/Sales/Index', ['repartidor_id' => $this->juan->id]);

    expect(listedIds($props, 'sales'))->toBe([$this->juanVisitSale->id, $this->juanLooseSale->id])
        ->and((float) $props['metrics']['totalSalesAmount'])->toBe(1500.0)
        ->and((float) $props['metrics']['creditSalesAmount'])->toBe(500.0)
        ->and((float) $props['metrics']['immediateSalesAmount'])->toBe(1000.0)
        ->and($props['metrics']['count'])->toBe(2)
        ->and($props['filters']['repartidor_id'])->toBe((string) $this->juan->id);
});

test('filtering sales by the house lists only the sales recorded at home', function (): void {
    $props = listProps('sales.index', 'Street/Sales/Index', ['repartidor_id' => 'house']);

    expect(listedIds($props, 'sales'))->toBe([$this->houseSale->id])
        ->and((float) $props['metrics']['totalSalesAmount'])->toBe(80.0);
});

test('a sale carries who made it: the driver of its visit, or whoever recorded it', function (): void {
    $props = listProps('sales.index', 'Street/Sales/Index');
    $byId = collect($props['sales']['data'])->keyBy('id');

    expect($byId[$this->juanVisitSale->id]['visit']['driver']['user']['name'])->toBe('Juan')
        ->and($byId[$this->juanLooseSale->id]['creator']['name'])->toBe('Juan')
        ->and($byId[$this->houseSale->id]['creator']['name'])->toBe('Admin');
});

test('without a driver, sales still list everyone and keep filtering by type and customer', function (): void {
    $all = listProps('sales.index', 'Street/Sales/Index');
    $credit = listProps('sales.index', 'Street/Sales/Index', ['sale_type' => 'credit']);
    $hotel = listProps('sales.index', 'Street/Sales/Index', ['search' => 'hot']);

    expect(listedIds($all, 'sales'))->toHaveCount(4)
        ->and((float) $all['metrics']['totalSalesAmount'])->toBe(1780.0)
        ->and(listedIds($credit, 'sales'))->toBe([$this->juanLooseSale->id])
        ->and(listedIds($hotel, 'sales'))->toBe([$this->juanVisitSale->id]);
});

test('filtering payments by a driver lists the ones of their visits and the ones they received without a visit, and the totals are theirs', function (): void {
    $props = listProps('payments.index', 'Street/Payments/Index', ['repartidor_id' => $this->juan->id]);

    expect(listedIds($props, 'payments'))->toBe([$this->juanVisitPayment->id, $this->juanLoosePayment->id])
        ->and((float) $props['metrics']['totalCollected'])->toBe(370.0)
        ->and((float) $props['metrics']['cashTotal'])->toBe(300.0)
        ->and((float) $props['metrics']['transferTotal'])->toBe(70.0)
        ->and($props['filters']['repartidor_id'])->toBe((string) $this->juan->id)
        ->and(array_column($props['repartidores'], 'name'))->toContain('Juan', 'Ana');
});

test('filtering payments by the house lists only the payments received at home', function (): void {
    $props = listProps('payments.index', 'Street/Payments/Index', ['repartidor_id' => 'house']);

    expect(listedIds($props, 'payments'))->toBe([$this->housePayment->id])
        ->and((float) $props['metrics']['totalCollected'])->toBe(40.0);
});

test('without a driver, payments still list everyone and keep filtering by method', function (): void {
    $all = listProps('payments.index', 'Street/Payments/Index');
    $transfers = listProps('payments.index', 'Street/Payments/Index', ['payment_method_id' => $this->transfer->id]);

    expect(listedIds($all, 'payments'))->toHaveCount(4)
        ->and((float) $all['metrics']['totalCollected'])->toBe(435.0)
        ->and(listedIds($transfers, 'payments'))->toBe([$this->juanLoosePayment->id, $this->housePayment->id]);
});

test('an unknown driver is a not found, not an empty list', function (): void {
    $this->get(route('sales.index', ['repartidor_id' => 999999]))->assertNotFound();
    $this->get(route('payments.index', ['repartidor_id' => 999999]))->assertNotFound();
});
