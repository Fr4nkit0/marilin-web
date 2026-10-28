<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->day = '2026-10-20';
    $this->cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo']);
    $this->transfer = PaymentMethod::factory()->create(['code' => 'TRANSFER', 'name' => 'Transferencia']);
    $this->bidon = ContainerType::factory()->create(['code' => 'BIDON_20L', 'description' => 'Bidón 20L']);

    $this->juanUser = User::factory()->create();
    $this->juan = Driver::factory()->create(['user_id' => $this->juanUser->id]);
    $this->anaUser = User::factory()->create();
    $this->ana = Driver::factory()->create(['user_id' => $this->anaUser->id]);
    $this->admin = User::factory()->superUser()->create();

    $this->juanVisit = Visit::factory()->create(['driver_id' => $this->juan->id]);
    $this->anaVisit = Visit::factory()->create(['driver_id' => $this->ana->id]);
});

function dailySale(array $attributes): Sale
{
    return Sale::factory()->create([
        'date' => '2026-10-20 10:00:00',
        'is_credit_sale' => false,
        ...$attributes,
    ]);
}

function dailyPayment(array $attributes): Payment
{
    return Payment::factory()->create([
        'paid_at' => '2026-10-20 10:00:00',
        'status' => 'CONFIRMED',
        'payment_method_id' => test()->cash->id,
        'amount' => 100,
        ...$attributes,
    ]);
}

function dailyContainers(array $attributes): ContainerMovement
{
    return ContainerMovement::factory()->create([
        'container_type_id' => test()->bidon->id,
        'movement_type' => 'DELIVERED',
        'direction' => 1,
        'quantity' => 2,
        'created_at' => '2026-10-20 10:00:00',
        ...$attributes,
    ]);
}

function seedDay(): void
{
    $t = test();
    // Juan: on a visit (made by anyone) and loose recorded by him.
    dailySale(['visit_id' => $t->juanVisit->id, 'created_by' => $t->admin->id, 'total' => 1000]);
    $creditSale = dailySale(['visit_id' => null, 'created_by' => $t->juanUser->id, 'total' => 500, 'is_credit_sale' => true]);
    dailyPayment(['visit_id' => $t->juanVisit->id, 'received_by' => $t->juanUser->id, 'amount' => 300, 'sale_id' => null]);
    dailyPayment(['visit_id' => null, 'received_by' => $t->juanUser->id, 'amount' => 70, 'sale_id' => $creditSale->id, 'payment_method_id' => $t->transfer->id]);
    dailyContainers(['visit_id' => $t->juanVisit->id, 'created_by' => $t->juanUser->id, 'quantity' => 4]);
    // Ana.
    dailySale(['visit_id' => $t->anaVisit->id, 'created_by' => $t->anaUser->id, 'total' => 200]);
    dailyPayment(['visit_id' => null, 'received_by' => $t->anaUser->id, 'amount' => 25]);
    dailyContainers(['visit_id' => null, 'created_by' => $t->anaUser->id, 'movement_type' => 'RETURNED', 'direction' => -1, 'quantity' => 1]);
    // House: no visit, recorded by an administrator.
    dailySale(['visit_id' => null, 'created_by' => $t->admin->id, 'total' => 80]);
    dailyPayment(['visit_id' => null, 'received_by' => $t->admin->id, 'amount' => 40, 'payment_method_id' => $t->transfer->id]);
    dailyContainers(['visit_id' => null, 'created_by' => $t->admin->id, 'quantity' => 3]);
    // Cancelled payment and another day never count.
    dailyPayment(['visit_id' => null, 'received_by' => $t->admin->id, 'amount' => 999, 'status' => 'CANCELLED']);
    dailySale(['visit_id' => null, 'created_by' => $t->admin->id, 'total' => 777, 'date' => '2026-10-19 10:00:00']);
}

test('a driver scope holds records on their visits and the ones they recorded without a visit, and nothing else', function (): void {
    seedDay();

    $juan = app(DailyFigures::class)->forDay($this->day, DayScope::driver($this->juan));

    expect($juan->totalSales)->toBe(1500.0)
        ->and($juan->creditSales)->toBe(500.0)
        ->and($juan->cashAmount)->toBe(300.0)
        ->and($juan->transferAmount)->toBe(70.0)
        ->and($juan->totalCollected)->toBe(370.0)
        ->and($juan->debtCollected)->toBe(300.0)
        ->and($juan->containersDelivered)->toBe(4)
        ->and($juan->containersReturned)->toBe(0)
        ->and($juan->sales)->toHaveCount(2)
        ->and($juan->creditSaleRows)->toHaveCount(1);

    $ana = app(DailyFigures::class)->forDay($this->day, DayScope::driver($this->ana));
    expect($ana->totalSales)->toBe(200.0)
        ->and($ana->cashAmount)->toBe(25.0)
        ->and($ana->containersReturned)->toBe(1)
        ->and($ana->containersDelivered)->toBe(0);
});

test('the house scope holds only records without a visit recorded by someone who is not a driver', function (): void {
    seedDay();

    $house = app(DailyFigures::class)->forDay($this->day, DayScope::house());

    expect($house->totalSales)->toBe(80.0)
        ->and($house->creditSales)->toBe(0.0)
        ->and($house->cashAmount)->toBe(0.0)
        ->and($house->transferAmount)->toBe(40.0)
        ->and($house->totalCollected)->toBe(40.0)
        ->and($house->debtCollected)->toBe(40.0)
        ->and($house->containersDelivered)->toBe(3);
});

test('the all scope equals every driver plus the house, with cancelled payments excluded', function (): void {
    seedDay();
    $figures = app(DailyFigures::class);

    $all = $figures->forDay($this->day, DayScope::all());
    $parts = [
        $figures->forDay($this->day, DayScope::driver($this->juan)),
        $figures->forDay($this->day, DayScope::driver($this->ana)),
        $figures->forDay($this->day, DayScope::house()),
    ];

    foreach (['totalSales', 'creditSales', 'cashAmount', 'transferAmount', 'totalCollected', 'debtCollected', 'containersDelivered', 'containersReturned'] as $figure) {
        expect($all->{$figure})->toBe(array_sum(array_map(fn ($part) => $part->{$figure}, $parts)), $figure);
    }

    expect($all->totalSales)->toBe(1780.0)
        ->and($all->totalCollected)->toBe(435.0)
        ->and($all->payments->pluck('amount')->map(fn ($amount) => (float) $amount)->contains(999.0))->toBeFalse();
});
