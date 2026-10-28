<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Models\ContainerMovement;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->actingAs(User::factory()->superUser()->create());

    $this->driverUser = User::factory()->create(['name' => 'Juan Pérez']);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);
    $this->cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo']);
    $this->transfer = PaymentMethod::factory()->create(['code' => 'TRANSFER', 'name' => 'Transferencia']);
    $this->bidon = ContainerType::factory()->create(['code' => 'BIDON_20L', 'description' => 'Bidón 20L']);
    $this->hotel = Customer::factory()->create(['name' => 'Hotel Altos de la Viña', 'driver_id' => $this->driver->id]);
    $this->estudio = Customer::factory()->create(['name' => 'Estudio Jurídico', 'driver_id' => $this->driver->id]);

    $stop = RouteStop::factory()->create(['route_id' => $this->route->id, 'customer_id' => $this->hotel->id]);
    $this->visit = Visit::factory()->create([
        'route_stop_id' => $stop->id,
        'customer_id' => $this->hotel->id,
        'driver_id' => $this->driver->id,
        'status' => 'FINISHED',
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function driverSale(array $attributes): Sale
{
    return Sale::factory()->create([
        'created_by' => test()->driverUser->id,
        'date' => '2026-10-20 10:00:00',
        'status' => 'confirmed',
        'is_credit_sale' => false,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function driverPayment(array $attributes): Payment
{
    return Payment::factory()->create([
        'received_by' => test()->driverUser->id,
        'paid_at' => '2026-10-20 11:00:00',
        'status' => 'CONFIRMED',
        ...$attributes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function settlement(): array
{
    $settlement = null;

    test()->get(route('cash-close.index', ['repartidor_id' => test()->driver->id, 'date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$settlement): void {
            $page->component('CashClose/Index');
            $settlement = $page->toArray()['props']['settlement'];
        });

    return $settlement;
}

test('sales, payments and containers the driver recorded without a visit count in the cash close', function (): void {
    driverSale(['customer_id' => $this->hotel->id, 'visit_id' => $this->visit->id, 'total' => 3400, 'is_credit_sale' => true]);
    $looseSale = driverSale(['customer_id' => $this->estudio->id, 'visit_id' => null, 'total' => 9500]);
    driverSale(['customer_id' => $this->estudio->id, 'visit_id' => null, 'total' => 2000, 'is_credit_sale' => true]);
    driverPayment(['customer_id' => $this->estudio->id, 'sale_id' => $looseSale->id, 'payment_method_id' => $this->cash->id, 'amount' => 6500]);
    driverPayment(['customer_id' => $this->estudio->id, 'sale_id' => $looseSale->id, 'payment_method_id' => $this->transfer->id, 'amount' => 3000]);
    driverPayment(['customer_id' => $this->estudio->id, 'sale_id' => null, 'payment_method_id' => $this->cash->id, 'amount' => 5000]);
    ContainerMovement::factory()->create(['customer_id' => $this->estudio->id, 'visit_id' => null, 'container_type_id' => $this->bidon->id, 'movement_type' => 'DELIVERED', 'quantity' => 4, 'created_by' => $this->driverUser->id]);
    ContainerMovement::factory()->create(['customer_id' => $this->hotel->id, 'visit_id' => $this->visit->id, 'container_type_id' => $this->bidon->id, 'movement_type' => 'RETURNED', 'direction' => -1, 'quantity' => 6, 'created_by' => $this->driverUser->id]);

    $settlement = settlement();

    expect($settlement['totalSales'])->toEqual(14900)
        ->and($settlement['creditSales'])->toEqual(5400)
        ->and($settlement['cashAmount'])->toEqual(11500)
        ->and($settlement['transferAmount'])->toEqual(3000)
        ->and($settlement['debtCollected'])->toEqual(5000)
        ->and($settlement['containersDelivered'])->toBe(4)
        ->and($settlement['containersReturned'])->toBe(6);
});

test('records of another driver or of another day stay out of the cash close', function (): void {
    $otherUser = User::factory()->create();
    driverSale(['customer_id' => $this->estudio->id, 'total' => 1000, 'created_by' => $otherUser->id]);
    driverSale(['customer_id' => $this->estudio->id, 'total' => 1000, 'date' => '2026-10-19 10:00:00']);
    driverPayment(['customer_id' => $this->estudio->id, 'payment_method_id' => $this->cash->id, 'amount' => 700, 'received_by' => $otherUser->id]);
    driverPayment(['customer_id' => $this->estudio->id, 'payment_method_id' => $this->cash->id, 'amount' => 700, 'paid_at' => '2026-10-21 09:00:00']);
    ContainerMovement::factory()->create(['customer_id' => $this->estudio->id, 'container_type_id' => $this->bidon->id, 'quantity' => 3, 'created_by' => $otherUser->id]);

    $settlement = settlement();

    expect($settlement['totalSales'])->toEqual(0)
        ->and($settlement['cashAmount'])->toEqual(0)
        ->and($settlement['containersDelivered'])->toBe(0)
        ->and($settlement['details']['cash'])->toBe([]);
});

test('each card carries the records behind it with customer, time, amount and origin', function (): void {
    $looseSale = driverSale(['customer_id' => $this->estudio->id, 'visit_id' => null, 'total' => 9500]);
    driverSale(['customer_id' => $this->hotel->id, 'visit_id' => $this->visit->id, 'total' => 3400, 'is_credit_sale' => true, 'date' => '2026-10-20 09:15:00']);
    driverPayment(['customer_id' => $this->estudio->id, 'sale_id' => $looseSale->id, 'payment_method_id' => $this->cash->id, 'amount' => 6500, 'paid_at' => '2026-10-20 10:05:00']);
    driverPayment(['customer_id' => $this->estudio->id, 'sale_id' => null, 'payment_method_id' => $this->cash->id, 'amount' => 5000, 'paid_at' => '2026-10-20 08:30:00']);
    driverPayment(['customer_id' => $this->hotel->id, 'visit_id' => $this->visit->id, 'payment_method_id' => $this->transfer->id, 'amount' => 3000]);
    ContainerMovement::factory()->create(['customer_id' => $this->hotel->id, 'visit_id' => $this->visit->id, 'container_type_id' => $this->bidon->id, 'movement_type' => 'RETURNED', 'direction' => -1, 'quantity' => 6, 'created_by' => $this->driverUser->id]);

    $details = settlement()['details'];

    expect($details['cash'])->toEqual([
        ['id' => $details['cash'][0]['id'], 'customer' => 'Estudio Jurídico', 'at' => '2026-10-20 08:30', 'amount' => 5000.0, 'concept' => 'Cobro de deuda', 'origin' => 'loose'],
        ['id' => $details['cash'][1]['id'], 'customer' => 'Estudio Jurídico', 'at' => '2026-10-20 10:05', 'amount' => 6500.0, 'concept' => "Venta #{$looseSale->id}", 'origin' => 'loose'],
    ])
        ->and(collect($details['transfer'])->pluck('origin')->all())->toBe(['stop'])
        ->and($details['credit'])->toHaveCount(1)
        ->and($details['credit'][0])->toMatchArray(['customer' => 'Hotel Altos de la Viña', 'at' => '2026-10-20 09:15', 'amount' => 3400.0, 'origin' => 'stop'])
        ->and($details['containers'][0])->toMatchArray(['customer' => 'Hotel Altos de la Viña', 'container' => 'Bidón 20L', 'movement' => 'RETURNED', 'quantity' => 6, 'origin' => 'stop']);
});
