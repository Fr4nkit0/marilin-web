<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Containers\MoveContainers;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\RecordPayment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\CancelSale;
use App\Street\Sales\RecordSale;
use App\Street\Sales\SaleLine;
use App\Street\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-30 18:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->driverUser = User::factory()->create(['name' => 'Juan']);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $this->water = Product::factory()->create();
    $this->bidon = ContainerType::factory()->create();
    // Days ago and already closed: the admin cancels on any date.
    $this->route = Route::factory()->completed()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-25']);
    RouteInventory::factory()->create(['route_id' => $this->route->id, 'product_id' => $this->water->id, 'current_quantity' => 20]);

    $operation = app(StreetOperation::class);
    $this->sale = $operation->recordSale(new RecordSale(
        customer: $this->customer,
        visit: null,
        driver: $this->driver,
        date: Carbon::parse('2026-10-25 10:00:00'),
        lines: [new SaleLine($this->water->id, 3, '700.00')],
        total: '2100.00',
        isCreditSale: true,
        notes: null,
        recordedBy: $this->driverUser,
    ));
    $operation->moveContainers(new MoveContainers(
        customer: $this->customer,
        containerTypeId: $this->bidon->id,
        type: ContainerMovementType::Delivered,
        quantity: 3,
        visit: null,
        recordedBy: $this->driverUser,
        sale: $this->sale,
    ));
    $this->payment = $operation->recordPayment(new RecordPayment(
        customer: $this->customer,
        visit: null,
        sale: null,
        paymentMethodId: PaymentMethod::factory()->create()->id,
        amount: '500.00',
        paidAt: Carbon::parse('2026-10-25 11:00:00'),
        receivedBy: $this->driverUser,
    ));
});

function adminBalance(): float
{
    return (float) CustomerBalance::query()->where('customer_id', test()->customer->id)->value('current_balance');
}

function adminWaterStock(): float
{
    return (float) RouteInventory::query()->where('route_id', test()->route->id)->where('product_id', test()->water->id)->value('current_quantity');
}

function adminContainers(): int
{
    return (int) CustomerContainerBalance::query()->where('customer_id', test()->customer->id)->where('container_type_id', test()->bidon->id)->value('balance');
}

test('the admin cancels a sale of any day: ledger, route stock and containers are reversed and the cancellation is audited', function (): void {
    expect(adminBalance())->toBe(1600.0)->and(adminWaterStock())->toBe(17.0)->and(adminContainers())->toBe(3);

    $this->actingAs($this->admin)
        ->from(route('sales.index'))
        ->post(route('sales.cancel', $this->sale), ['reason' => 'Cargada dos veces'])
        ->assertRedirect(route('sales.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Venta anulada.');

    $sale = $this->sale->fresh();
    $creditNote = CustomerAccountEntry::query()->where('sale_id', $sale->id)->where('entry_type', 'CREDIT_NOTE')->sole();

    expect($sale->status)->toBe('cancelled')
        ->and($sale->cancelled_by)->toBe($this->admin->id)
        ->and($sale->cancelled_at->toDateTimeString())->toBe('2026-10-30 18:00:00')
        ->and($sale->cancellation_reason)->toBe('Cargada dos veces')
        // The admin's own action does not go to the review inbox.
        ->and($sale->needs_review)->toBeFalse()
        ->and($sale->review_reasons)->toBe([])
        ->and($creditNote->created_by)->toBe($this->admin->id)
        ->and($creditNote->description)->toBe('Venta anulada por la administración: Cargada dos veces')
        ->and(adminBalance())->toBe(-500.0)
        ->and(adminWaterStock())->toBe(20.0)
        ->and(adminContainers())->toBe(0)
        ->and(ContainerMovement::query()->where('sale_id', $sale->id)->whereNotNull('reverses_movement_id')->sole()->movement_type)->toBe(ContainerMovementType::Returned->value);
});

test('the admin cancels a payment of any day: its amount goes back on the ledger and the cancellation is audited', function (): void {
    $this->actingAs($this->admin)
        ->from(route('payments.index'))
        ->post(route('payments.cancel', $this->payment), ['reason' => 'Cobro mal cargado'])
        ->assertRedirect(route('payments.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Cobro anulado.');

    $payment = $this->payment->fresh();
    $adjustment = CustomerAccountEntry::query()->where('payment_id', $payment->id)->where('entry_type', 'ADJUSTMENT')->sole();

    expect($payment->status)->toBe('CANCELLED')
        ->and($payment->cancelled_by)->toBe($this->admin->id)
        ->and($payment->cancelled_at->toDateTimeString())->toBe('2026-10-30 18:00:00')
        ->and($payment->cancellation_reason)->toBe('Cobro mal cargado')
        ->and($payment->needs_review)->toBeFalse()
        ->and($adjustment->created_by)->toBe($this->admin->id)
        ->and($adjustment->description)->toBe('Cobro anulado por la administración: Cobro mal cargado')
        ->and(adminBalance())->toBe(2100.0);
});

test('a cancellation without a reason is refused and changes nothing', function (string $routeName, string $record): void {
    $this->actingAs($this->admin)
        ->post(route($routeName, $this->{$record}), ['reason' => '  '])
        ->assertSessionHasErrors(['reason' => 'El campo motivo es obligatorio.']);

    expect($this->{$record}->fresh()->cancelled_at)->toBeNull()
        ->and(adminBalance())->toBe(1600.0)
        ->and(adminWaterStock())->toBe(17.0)
        ->and(adminContainers())->toBe(3)
        ->and(CustomerAccountEntry::query()->whereIn('entry_type', ['CREDIT_NOTE', 'ADJUSTMENT'])->count())->toBe(0);
})->with([
    'sale' => ['sales.cancel', 'sale'],
    'payment' => ['payments.cancel', 'payment'],
]);

test('only the admin can cancel a sale or a payment', function (string $routeName, string $record): void {
    $this->actingAs($this->driverUser)
        ->post(route($routeName, $this->{$record}), ['reason' => 'Me equivoqué'])
        ->assertForbidden();

    expect($this->{$record}->fresh()->cancelled_at)->toBeNull()
        ->and(adminBalance())->toBe(1600.0)
        ->and(CustomerAccountEntry::query()->whereIn('entry_type', ['CREDIT_NOTE', 'ADJUSTMENT'])->count())->toBe(0);
})->with([
    'sale' => ['sales.cancel', 'sale'],
    'payment' => ['payments.cancel', 'payment'],
]);

test('a record already cancelled is not cancelled again', function (string $routeName, string $record, string $message): void {
    $this->actingAs($this->admin)->post(route($routeName, $this->{$record}), ['reason' => 'Primera vez'])->assertSessionHasNoErrors();
    $balance = adminBalance();
    $stock = adminWaterStock();
    $containers = adminContainers();

    $this->actingAs($this->admin)
        ->post(route($routeName, $this->{$record}), ['reason' => 'Segunda vez'])
        ->assertSessionHasErrors(['reason' => $message]);

    expect($this->{$record}->fresh()->cancellation_reason)->toBe('Primera vez')
        ->and(adminBalance())->toBe($balance)
        ->and(adminWaterStock())->toBe($stock)
        ->and(adminContainers())->toBe($containers)
        ->and(CustomerAccountEntry::query()->whereIn('entry_type', ['CREDIT_NOTE', 'ADJUSTMENT'])->count())->toBe(1);
})->with([
    'sale' => ['sales.cancel', 'sale', 'La venta ya está anulada.'],
    'payment' => ['payments.cancel', 'payment', 'El cobro ya está anulado.'],
]);

test('the sales and payments lists tell the page whether the user can cancel', function (string $routeName, string $component): void {
    $this->withoutVite();

    $this->actingAs($this->admin)->get(route($routeName))->assertInertia(fn ($page) => $page->component($component)->where('canCancel', true));
    $this->actingAs($this->driverUser)->get(route($routeName))->assertInertia(fn ($page) => $page->component($component)->where('canCancel', false));
})->with([
    'sales' => ['sales.index', 'Street/Sales/Index'],
    'payments' => ['payments.index', 'Street/Payments/Index'],
]);

test('a driver cancellation through the app is audited the same way', function (): void {
    $this->travelTo('2026-10-25 12:00:00');
    $operation = app(StreetOperation::class);

    $operation->cancelSale(new CancelSale(sale: $this->sale, cancelledBy: $this->driverUser, reason: 'No la quiso'));

    $sale = $this->sale->fresh();
    expect($sale->cancelled_by)->toBe($this->driverUser->id)
        ->and($sale->cancelled_at->toDateTimeString())->toBe('2026-10-25 12:00:00')
        ->and($sale->cancellation_reason)->toBe('No la quiso')
        ->and(CustomerAccountEntry::query()->where('sale_id', $sale->id)->where('entry_type', 'CREDIT_NOTE')->sole()->description)
        ->toBe('Venta anulada por el repartidor: No la quiso');
});

test('a web user who is neither admin nor driver cannot cancel', function (string $routeName, string $record): void {
    $this->actingAs(User::factory()->create())
        ->post(route($routeName, $this->{$record}), ['reason' => 'x'])
        ->assertForbidden();

    expect($this->{$record}->fresh()->cancelled_at)->toBeNull()
        ->and(adminBalance())->toBe(1600.0);
})->with(['sale' => ['sales.cancel', 'sale'], 'payment' => ['payments.cancel', 'payment']]);

test('posted cancellation fields cannot forge the audit: the admin and the server time are stored', function (): void {
    $this->actingAs($this->admin)->post(route('sales.cancel', $this->sale), [
        'reason' => 'Doble',
        'cancelled_by' => $this->driverUser->id,
        'cancelled_at' => '2020-01-01',
        'status' => 'confirmed',
    ])->assertSessionHasNoErrors();

    $sale = $this->sale->fresh();
    expect($sale->status)->toBe('cancelled')
        ->and($sale->cancelled_by)->toBe($this->admin->id)
        ->and($sale->cancelled_at->toDateTimeString())->toBe('2026-10-30 18:00:00');
});
