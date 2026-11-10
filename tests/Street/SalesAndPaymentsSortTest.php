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

    $zoeUser = User::factory()->create(['name' => 'Zoe']);
    $this->zoeVisit = Visit::factory()->create(['driver_id' => Driver::factory()->create(['user_id' => $zoeUser->id])->id]);
    $this->bruno = User::factory()->create(['name' => 'Bruno']);

    $this->hotel = Customer::factory()->create(['name' => 'Hotel']);
    $this->almacen = Customer::factory()->create(['name' => 'Almacén']);
    $this->kiosco = Customer::factory()->create(['name' => 'Kiosco']);
});

/**
 * The ids a list sends on its first page, in order.
 *
 * @param  array<string, string>  $query
 * @return list<int>
 */
function sortedStreetIds(object $test, string $route, string $prop, array $query = []): array
{
    $ids = [];

    $test->get(route($route, $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids, $prop): void {
            $ids = array_column($page->toArray()['props'][$prop]['data'], 'id');
        });

    return $ids;
}

/** Three sales of the day: the hotel's on Zoe's visit (loaded by the admin), Bruno's credit sale and the house's. */
function threeSales(object $test): array
{
    return [
        'hotel' => Sale::factory()->create(['customer_id' => $test->hotel->id, 'visit_id' => $test->zoeVisit->id, 'created_by' => $test->admin->id, 'total' => 300, 'date' => '2026-10-20 09:00:00']),
        'almacen' => Sale::factory()->creditSale()->create(['customer_id' => $test->almacen->id, 'created_by' => $test->bruno->id, 'total' => 900, 'date' => '2026-10-20 11:00:00']),
        'kiosco' => Sale::factory()->create(['customer_id' => $test->kiosco->id, 'created_by' => $test->admin->id, 'total' => 50, 'date' => '2026-10-20 10:00:00']),
    ];
}

test('without a sort the sales keep the latest first and echo no sort', function (): void {
    $sales = threeSales($this);

    expect(sortedStreetIds($this, 'sales.index', 'sales'))->toBe([$sales['almacen']->id, $sales['kiosco']->id, $sales['hotel']->id]);

    $this->get(route('sales.index'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', null)->where('filters.direction', null));
});

test('the sales sort by time, customer, total and type', function (): void {
    $sales = threeSales($this);

    expect(sortedStreetIds($this, 'sales.index', 'sales', ['sort' => 'date', 'direction' => 'asc']))
        ->toBe([$sales['hotel']->id, $sales['kiosco']->id, $sales['almacen']->id]);
    expect(sortedStreetIds($this, 'sales.index', 'sales', ['sort' => 'customer', 'direction' => 'asc']))
        ->toBe([$sales['almacen']->id, $sales['hotel']->id, $sales['kiosco']->id]);
    expect(sortedStreetIds($this, 'sales.index', 'sales', ['sort' => 'total', 'direction' => 'desc']))
        ->toBe([$sales['almacen']->id, $sales['hotel']->id, $sales['kiosco']->id]);
    // Contado before fiado, as the type column reads.
    expect(sortedStreetIds($this, 'sales.index', 'sales', ['sort' => 'type', 'direction' => 'desc'])[0])->toBe($sales['almacen']->id);

    $this->get(route('sales.index', ['sort' => 'total', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'total')->where('filters.direction', 'desc'));
});

test('the sales sort by who the list says registered them: the visit driver, else who loaded it', function (): void {
    $sales = threeSales($this);

    // Admin (kiosco), Bruno (almacén), Zoe (hotel: the admin loaded it on her visit).
    expect(sortedStreetIds($this, 'sales.index', 'sales', ['sort' => 'registered_by', 'direction' => 'asc']))
        ->toBe([$sales['kiosco']->id, $sales['almacen']->id, $sales['hotel']->id]);
});

test('an unknown sale sort keeps the latest first', function (): void {
    $sales = threeSales($this);

    expect(sortedStreetIds($this, 'sales.index', 'sales', ['sort' => 'created_by; delete from sales', 'direction' => 'asc']))
        ->toBe([$sales['almacen']->id, $sales['kiosco']->id, $sales['hotel']->id]);
    expect(Sale::query()->count())->toBe(3);
});

/** Three payments of the day: the hotel's on Zoe's visit, Bruno's transfer and the kiosk's at the counter. */
function threePayments(object $test): array
{
    $payment = fn (array $attributes): Payment => Payment::factory()->create([
        'status' => 'CONFIRMED',
        'payment_method_id' => $test->cash->id,
        'received_by' => $test->admin->id,
        ...$attributes,
    ]);

    return [
        'hotel' => $payment(['customer_id' => $test->hotel->id, 'visit_id' => $test->zoeVisit->id, 'amount' => 300, 'paid_at' => '2026-10-20 09:00:00']),
        'almacen' => $payment(['customer_id' => $test->almacen->id, 'received_by' => $test->bruno->id, 'payment_method_id' => $test->transfer->id, 'amount' => 900, 'paid_at' => '2026-10-20 11:00:00']),
        'kiosco' => $payment(['customer_id' => $test->kiosco->id, 'amount' => 50, 'paid_at' => '2026-10-20 10:00:00', 'status' => 'CANCELLED']),
    ];
}

test('without a sort the payments keep the latest first and echo no sort', function (): void {
    $payments = threePayments($this);

    expect(sortedStreetIds($this, 'payments.index', 'payments'))->toBe([$payments['almacen']->id, $payments['kiosco']->id, $payments['hotel']->id]);

    $this->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', null)->where('filters.direction', null));
});

test('the payments sort by time, customer, collector, method, status and amount', function (): void {
    $payments = threePayments($this);
    $order = fn (array $query): array => sortedStreetIds($this, 'payments.index', 'payments', $query);

    expect($order(['sort' => 'paid_at', 'direction' => 'asc']))->toBe([$payments['hotel']->id, $payments['kiosco']->id, $payments['almacen']->id]);
    expect($order(['sort' => 'customer', 'direction' => 'asc']))->toBe([$payments['almacen']->id, $payments['hotel']->id, $payments['kiosco']->id]);
    // Admin (kiosco), Bruno (almacén), Zoe (hotel, her visit).
    expect($order(['sort' => 'collected_by', 'direction' => 'asc']))->toBe([$payments['kiosco']->id, $payments['almacen']->id, $payments['hotel']->id]);
    expect($order(['sort' => 'method', 'direction' => 'desc'])[0])->toBe($payments['almacen']->id);
    expect($order(['sort' => 'status', 'direction' => 'asc'])[0])->toBe($payments['kiosco']->id);
    expect($order(['sort' => 'amount', 'direction' => 'desc']))->toBe([$payments['almacen']->id, $payments['hotel']->id, $payments['kiosco']->id]);
});

test('an unknown payment sort keeps the latest first', function (): void {
    $payments = threePayments($this);

    expect(sortedStreetIds($this, 'payments.index', 'payments', ['sort' => 'amount', 'direction' => 'up']))
        ->toBe([$payments['almacen']->id, $payments['kiosco']->id, $payments['hotel']->id]);
});
