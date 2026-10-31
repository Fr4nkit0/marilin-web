<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 18:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $this->route = Route::factory()->create([
        'assigned_driver_id' => $this->driver->id,
        'date' => '2026-10-22',
        'status' => 'IN_PROGRESS',
    ]);
    $this->water = Product::factory()->create(['enabled' => true, 'price' => 700]);
    $this->waterStock = RouteInventory::query()->create([
        'route_id' => $this->route->id,
        'product_id' => $this->water->id,
        'current_quantity' => 20,
        'created_by' => $this->user->id,
    ]);
    $this->method = PaymentMethod::factory()->create();
    CustomerBalance::factory()->create(['customer_id' => $this->customer->id, 'current_balance' => 1000]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cancellableSale(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'date' => '2026-10-22T13:30:00Z',
        'total' => 2100,
        'is_credit_sale' => true,
        'details' => [['product_id' => test()->water->id, 'quantity' => 3, 'unit_price' => 700]],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cancellablePayment(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'payment_method_id' => test()->method->id,
        // Under the customer's debt, so the payment itself is not flagged (S4).
        'amount' => 800.25,
        'paid_at' => '2026-10-22T14:45:00Z',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cancellation(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'cancelled_at' => '2026-10-22T15:00:00Z',
        'reason' => 'El cliente no la quiso',
    ], $overrides);
}

function cancellationBalance(): float
{
    return (float) CustomerBalance::query()->where('customer_id', test()->customer->id)->firstOrFail()->current_balance;
}

function waterStock(): float
{
    return (float) test()->waterStock->fresh()->current_quantity;
}

test('push cancels a sale of today: status cancelled, flagged, balance and stock back, one credit note', function (): void {
    $sale = cancellableSale();
    push(['sales' => [$sale]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');
    $record = Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail();
    expect(cancellationBalance())->toBe(3100.0)->and(waterStock())->toBe(17.0);

    $payload = cancellation(['sale_uuid' => $sale['uuid']]);
    $response = push(['sale_cancellations' => [$payload]]);

    $response->assertOk()
        ->assertJsonPath('data.sale_cancellations.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.sale_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.0.server_id', $record->id)
        ->assertJsonPath('data.sale_cancellations.0.review_reasons', ['Anulado por el repartidor.']);

    $record->refresh();
    $note = CustomerAccountEntry::query()->where('entry_type', 'CREDIT_NOTE')->sole();

    expect($record->status)->toBe('cancelled')
        ->and($record->needs_review)->toBeTrue()
        ->and($record->review_reasons)->toBe(['Cancelled by a driver.'])
        ->and(cancellationBalance())->toBe(1000.0)
        ->and($note->direction)->toBe(-1)
        ->and((float) $note->amount)->toBe(2100.0)
        ->and($note->sale_id)->toBe($record->id)
        ->and($note->customer_id)->toBe($this->customer->id)
        ->and($note->description)->toBe('Venta anulada por el repartidor: El cliente no la quiso')
        ->and(CustomerAccountEntry::query()->count())->toBe(2)
        ->and(waterStock())->toBe(20.0);
});

test('push cancels a sale by its server id and without a reason', function (): void {
    $sale = cancellableSale();
    push(['sales' => [$sale]])->assertOk();
    $record = Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail();

    push(['sale_cancellations' => [cancellation(['sale_id' => $record->id, 'reason' => null])]])
        ->assertOk()
        ->assertJsonPath('data.sale_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.0.server_id', $record->id);

    expect($record->fresh()->status)->toBe('cancelled')
        ->and(CustomerAccountEntry::query()->where('entry_type', 'CREDIT_NOTE')->sole()->description)->toBe('Venta anulada por el repartidor')
        ->and(cancellationBalance())->toBe(1000.0);
});

test('push cancelling a sale leaves its payments alone', function (): void {
    $sale = cancellableSale();
    $payment = cancellablePayment(['sale_uuid' => $sale['uuid']]);
    push(['sales' => [$sale], 'payments' => [$payment]])->assertOk();

    push(['sale_cancellations' => [cancellation(['sale_uuid' => $sale['uuid']])]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    $record = Payment::query()->where('client_uuid', $payment['uuid'])->firstOrFail();
    expect($record->status)->toBe('CONFIRMED')
        ->and($record->needs_review)->toBeFalse()
        ->and(CustomerAccountEntry::query()->where('payment_id', $record->id)->count())->toBe(1)
        ->and(cancellationBalance())->toBe(1000.0 - 800.25);
});

test('push gives a cancelled sale stock back to the route of its visit', function (): void {
    // A route has one driver per day, so the visit's route is another one than the driver's own.
    $otherRoute = Route::factory()->create(['assigned_driver_id' => Driver::factory()->create()->id, 'date' => '2026-10-22']);
    $otherStock = RouteInventory::query()->create([
        'route_id' => $otherRoute->id, 'product_id' => $this->water->id, 'current_quantity' => 10, 'created_by' => $this->user->id,
    ]);
    $stop = RouteStop::factory()->create(['route_id' => $otherRoute->id, 'customer_id' => $this->customer->id]);
    $visit = Visit::factory()->create([
        'route_stop_id' => $stop->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'created_by' => $this->user->id,
    ]);
    $sale = cancellableSale(['visit_id' => $visit->id]);
    push(['sales' => [$sale]])->assertOk();
    expect((float) $otherStock->fresh()->current_quantity)->toBe(7.0);

    push(['sale_cancellations' => [cancellation(['sale_uuid' => $sale['uuid']])]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    expect((float) $otherStock->fresh()->current_quantity)->toBe(10.0)
        ->and(waterStock())->toBe(20.0);
});

test('push accepts a sale created and cancelled in the same request', function (): void {
    $sale = cancellableSale();

    $response = push([
        'sale_cancellations' => [cancellation(['sale_uuid' => $sale['uuid']])],
        'sales' => [$sale],
    ]);

    $record = Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail();
    $response->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.0.server_id', $record->id);

    expect($record->status)->toBe('cancelled')
        ->and(cancellationBalance())->toBe(1000.0)
        ->and(waterStock())->toBe(20.0);
});

test('push cancels a payment of today: status cancelled, flagged, balance back, one adjustment', function (): void {
    $payment = cancellablePayment();
    push(['payments' => [$payment]])->assertOk()->assertJsonPath('data.payments.0.status', 'accepted');
    $record = Payment::query()->where('client_uuid', $payment['uuid'])->firstOrFail();
    expect(cancellationBalance())->toBe(1000.0 - 800.25);

    $payload = cancellation(['payment_uuid' => $payment['uuid'], 'reason' => 'Billete falso']);
    $response = push(['payment_cancellations' => [$payload]]);

    $response->assertOk()
        ->assertJsonPath('data.payment_cancellations.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.payment_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.payment_cancellations.0.server_id', $record->id)
        ->assertJsonPath('data.payment_cancellations.0.review_reasons', ['Anulado por el repartidor.']);

    $record->refresh();
    $adjustment = CustomerAccountEntry::query()->where('entry_type', 'ADJUSTMENT')->sole();

    expect($record->status)->toBe('CANCELLED')
        ->and($record->needs_review)->toBeTrue()
        ->and($record->review_reasons)->toBe(['Cancelled by a driver.'])
        ->and(cancellationBalance())->toBe(1000.0)
        ->and($adjustment->direction)->toBe(1)
        ->and((float) $adjustment->amount)->toBe(800.25)
        ->and($adjustment->payment_id)->toBe($record->id)
        ->and($adjustment->description)->toBe('Cobro anulado por el repartidor: Billete falso')
        ->and(CustomerAccountEntry::query()->count())->toBe(2);
});

test('push cancels a payment by its server id', function (): void {
    $payment = cancellablePayment();
    push(['payments' => [$payment]])->assertOk();
    $record = Payment::query()->where('client_uuid', $payment['uuid'])->firstOrFail();

    push(['payment_cancellations' => [cancellation(['payment_id' => $record->id])]])
        ->assertOk()
        ->assertJsonPath('data.payment_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.payment_cancellations.0.server_id', $record->id);

    expect($record->fresh()->status)->toBe('CANCELLED')->and(cancellationBalance())->toBe(1000.0);
});

test('push answers a replayed :entity cancellation with the same server id and books nothing again', function (string $entity, string $replay): void {
    $target = $entity === 'sale' ? cancellableSale() : cancellablePayment();
    push([$entity === 'sale' ? 'sales' : 'payments' => [$target]])->assertOk();
    $key = "{$entity}_cancellations";
    $first = cancellation(["{$entity}_uuid" => $target['uuid']]);
    $serverId = push([$key => [$first]])->assertOk()->json("data.{$key}.0.server_id");

    if ($replay === 'next day') {
        $this->travelTo('2026-10-23 09:00:00');
    }

    $again = $replay === 'same uuid' ? $first : cancellation(["{$entity}_uuid" => $target['uuid']]);
    $response = push([$key => [$again]]);

    $response->assertOk()
        ->assertJsonPath("data.{$key}.0.uuid", $again['uuid'])
        ->assertJsonPath("data.{$key}.0.status", 'accepted')
        ->assertJsonPath("data.{$key}.0.server_id", $serverId)
        ->assertJsonPath("data.{$key}.0.review_reasons", ['Anulado por el repartidor.']);

    expect(CustomerAccountEntry::query()->count())->toBe(2)
        ->and(cancellationBalance())->toBe(1000.0)
        ->and(waterStock())->toBe(20.0);
})->with(['sale', 'payment'])->with(['same uuid', 'another uuid', 'next day']);

/**
 * Pushes a sale or payment of 2026-10-22 and answers its cancellation key, reference and the figures a rejection must leave alone.
 *
 * @return array{0: string, 1: array<string, mixed>, 2: class-string}
 */
function pushedTarget(string $entity): array
{
    $target = $entity === 'sale' ? cancellableSale() : cancellablePayment();
    push([$entity === 'sale' ? 'sales' : 'payments' => [$target]])->assertOk()->assertJsonPath(($entity === 'sale' ? 'data.sales' : 'data.payments').'.0.status', 'accepted');

    return ["{$entity}_cancellations", $target, $entity === 'sale' ? Sale::class : Payment::class];
}

test('push applies an offline :entity cancellation made on the record day and synced after midnight', function (string $entity): void {
    [$key, $target, $model] = pushedTarget($entity);

    // Cancelled on 2026-10-22 at 23:50 local, synced at 00:00:13 of the next day.
    $this->travelTo('2026-10-23 00:00:13');
    $result = push([$key => [cancellation(["{$entity}_uuid" => $target['uuid'], 'cancelled_at' => '2026-10-23T02:50:00Z'])]])->assertOk()->json("data.{$key}.0");

    $record = $model::query()->where('client_uuid', $target['uuid'])->firstOrFail();
    expect($result['status'])->toBe('accepted')
        ->and($result['server_id'])->toBe($record->id)
        ->and(strtolower($record->status))->toBe('cancelled')
        ->and(cancellationBalance())->toBe(1000.0)
        ->and(CustomerAccountEntry::query()->count())->toBe(2)
        ->and(waterStock())->toBe(20.0);
})->with(['sale', 'payment']);

test('push rejects a :entity cancellation made on a later day than the record and changes nothing', function (string $entity): void {
    [$key, $target, $model] = pushedTarget($entity);
    $before = [cancellationBalance(), CustomerAccountEntry::query()->count(), waterStock()];

    $this->travelTo('2026-10-23 09:00:00');
    $result = push([$key => [cancellation(["{$entity}_uuid" => $target['uuid'], 'cancelled_at' => '2026-10-23T11:00:00Z'])]])->assertOk()->json("data.{$key}.0");

    $record = $model::query()->where('client_uuid', $target['uuid'])->firstOrFail();
    expect($result['status'])->toBe('rejected')
        ->and($result['server_id'])->toBeNull()
        ->and($result['error'])->toBe('La anulación no se hizo el día del registro.')
        ->and(strtolower($record->status))->toBe('confirmed')
        ->and($record->needs_review)->toBeFalse()
        ->and([cancellationBalance(), CustomerAccountEntry::query()->count(), waterStock()])->toBe($before);
})->with(['sale', 'payment']);

test('push rejects a :entity cancellation more than 3 days old and changes nothing', function (string $entity): void {
    [$key, $target, $model] = pushedTarget($entity);
    $before = [cancellationBalance(), CustomerAccountEntry::query()->count(), waterStock()];

    // Cancelled on the record's day at 12:00 local, synced 3 days and 1 second later.
    $this->travelTo('2026-10-25 12:00:01');
    $result = push([$key => [cancellation(["{$entity}_uuid" => $target['uuid'], 'cancelled_at' => '2026-10-22T15:00:00Z'])]])->assertOk()->json("data.{$key}.0");

    $record = $model::query()->where('client_uuid', $target['uuid'])->firstOrFail();
    expect($result['status'])->toBe('rejected')
        ->and($result['server_id'])->toBeNull()
        ->and($result['error'])->toBe('La anulación tiene más de 3 días.')
        ->and(strtolower($record->status))->toBe('confirmed')
        ->and($record->needs_review)->toBeFalse()
        ->and([cancellationBalance(), CustomerAccountEntry::query()->count(), waterStock()])->toBe($before);
})->with(['sale', 'payment']);

test('push applies a :entity cancellation exactly 3 days old', function (string $entity): void {
    [$key, $target, $model] = pushedTarget($entity);

    $this->travelTo('2026-10-25 12:00:00');
    push([$key => [cancellation(["{$entity}_uuid" => $target['uuid'], 'cancelled_at' => '2026-10-22T15:00:00Z'])]])
        ->assertOk()->assertJsonPath("data.{$key}.0.status", 'accepted');

    expect(strtolower($model::query()->where('client_uuid', $target['uuid'])->firstOrFail()->status))->toBe('cancelled')
        ->and(cancellationBalance())->toBe(1000.0);
})->with(['sale', 'payment']);

test('push clamps a :entity cancellation dated in the future to the server clock and applies it', function (string $entity): void {
    [$key, $target, $model] = pushedTarget($entity);

    // The app's clock runs a day ahead: read as now, the record's day.
    push([$key => [cancellation(["{$entity}_uuid" => $target['uuid'], 'cancelled_at' => '2026-10-23T15:00:00Z'])]])
        ->assertOk()->assertJsonPath("data.{$key}.0.status", 'accepted');

    expect(strtolower($model::query()->where('client_uuid', $target['uuid'])->firstOrFail()->status))->toBe('cancelled')
        ->and(cancellationBalance())->toBe(1000.0)
        ->and(waterStock())->toBe(20.0);
})->with(['sale', 'payment']);

test('push rejects cancelling a :entity of yesterday today and changes nothing', function (string $entity): void {
    $target = $entity === 'sale'
        ? cancellableSale(['date' => '2026-10-21T13:30:00Z'])
        : cancellablePayment(['paid_at' => '2026-10-21T14:45:00Z']);
    push([$entity === 'sale' ? 'sales' : 'payments' => [$target]])->assertOk();
    $model = $entity === 'sale' ? Sale::class : Payment::class;
    $before = [cancellationBalance(), CustomerAccountEntry::query()->count(), waterStock()];

    $key = "{$entity}_cancellations";
    $result = push([$key => [cancellation(["{$entity}_uuid" => $target['uuid']])]])->assertOk()->json("data.{$key}.0");

    $record = $model::query()->where('client_uuid', $target['uuid'])->firstOrFail();
    expect($result['status'])->toBe('rejected')
        ->and($result['server_id'])->toBeNull()
        ->and($result['error'])->toBe('La anulación no se hizo el día del registro.')
        ->and(strtolower($record->status))->toBe('confirmed')
        ->and($record->needs_review)->toBeFalse()
        ->and([cancellationBalance(), CustomerAccountEntry::query()->count(), waterStock()])->toBe($before);
})->with(['sale', 'payment']);

test('push judges the :entity cancellation day in local time, not UTC', function (string $entity): void {
    // Recorded at 23:30 -03 and cancelled at 20:00 -03 of 2026-10-22: different UTC days, the same local day.
    $this->travelTo('2026-10-23 00:00:13');
    $target = $entity === 'sale'
        ? cancellableSale(['date' => '2026-10-23T02:30:00Z'])
        : cancellablePayment(['paid_at' => '2026-10-23T02:30:00Z']);
    push([$entity === 'sale' ? 'sales' : 'payments' => [$target]])->assertOk();
    $key = "{$entity}_cancellations";

    push([$key => [cancellation(["{$entity}_uuid" => $target['uuid'], 'cancelled_at' => '2026-10-22T23:00:00Z'])]])
        ->assertOk()->assertJsonPath("data.{$key}.0.status", 'accepted');

    $model = $entity === 'sale' ? Sale::class : Payment::class;
    expect(strtolower($model::query()->where('client_uuid', $target['uuid'])->firstOrFail()->status))->toBe('cancelled')
        ->and(cancellationBalance())->toBe(1000.0);
})->with(['sale', 'payment']);

test('push rejects cancelling a :entity of another driver and changes nothing', function (string $entity, string $ownership): void {
    $otherUser = User::factory()->create();
    $other = Driver::factory()->create(['user_id' => $otherUser->id]);
    $otherVisit = Visit::factory()->create(['customer_id' => $this->customer->id, 'driver_id' => $other->id]);
    $attributes = match ($ownership) {
        // The visit decides, whoever recorded it.
        'on their visit' => ['visit_id' => $otherVisit->id, $entity === 'sale' ? 'created_by' : 'received_by' => $this->user->id],
        'recorded by their user' => ['visit_id' => null, $entity === 'sale' ? 'created_by' : 'received_by' => $otherUser->id],
    };
    $record = $entity === 'sale'
        ? Sale::factory()->create(['customer_id' => $this->customer->id, 'date' => '2026-10-22 10:00:00', ...$attributes])
        : Payment::factory()->create(['customer_id' => $this->customer->id, 'paid_at' => '2026-10-22 10:00:00', ...$attributes]);

    $key = "{$entity}_cancellations";
    $result = push([$key => [cancellation(["{$entity}_id" => $record->id])]])->assertOk()->json("data.{$key}.0");

    expect($result['status'])->toBe('rejected')
        ->and($result['error'])->toBe($entity === 'sale' ? 'La venta pertenece a otro repartidor.' : 'El cobro pertenece a otro repartidor.')
        ->and(strtolower($record->fresh()->status))->toBe('confirmed')
        ->and($record->fresh()->needs_review)->toBeFalse()
        ->and(CustomerAccountEntry::query()->count())->toBe(0)
        ->and(cancellationBalance())->toBe(1000.0)
        ->and(waterStock())->toBe(20.0);
})->with(['sale', 'payment'])->with(['on their visit', 'recorded by their user']);

test('push rejects a :entity cancellation whose reference matches nothing or is missing', function (string $entity): void {
    $key = "{$entity}_cancellations";

    $response = push([$key => [
        cancellation(["{$entity}_uuid" => (string) Str::uuid()]),
        cancellation(["{$entity}_id" => 999999]),
        cancellation(),
    ]])->assertOk();

    expect($response->json("data.{$key}.0.status"))->toBe('rejected')
        ->and($response->json("data.{$key}.0.error"))->toBe($missing = $entity === 'sale' ? 'La venta referida no existe.' : 'El cobro referido no existe.')
        ->and($response->json("data.{$key}.1.error"))->toBe($missing)
        ->and($response->json("data.{$key}.2.status"))->toBe('rejected')
        ->and(CustomerAccountEntry::query()->count())->toBe(0);
})->with(['sale', 'payment']);

test('the day figures and the sales list totals no longer count a cancelled sale', function (): void {
    $kept = cancellableSale(['total' => 700, 'details' => [['product_id' => $this->water->id, 'quantity' => 1, 'unit_price' => 700]]]);
    $cancelled = cancellableSale();
    push(['sales' => [$kept, $cancelled]])->assertOk();

    push(['sale_cancellations' => [cancellation(['sale_uuid' => $cancelled['uuid']])]])->assertOk();

    $day = app(DailyFigures::class)->forDay('2026-10-22', DayScope::driver($this->driver));
    expect($day->totalSales)->toBe(700.0)
        ->and($day->creditSales)->toBe(700.0)
        ->and($day->sales->pluck('client_uuid')->all())->toBe([$kept['uuid']])
        ->and(app(DailyFigures::class)->forDay('2026-10-22', DayScope::all())->totalSales)->toBe(700.0);
});

test('a driver cancellation always reaches the review inbox, even on a sale already flagged or already reviewed', function (string $state): void {
    $sale = cancellableSale(['details' => [['product_id' => test()->water->id, 'quantity' => 3, 'unit_price' => 700]]]);
    push(['sales' => [$sale]])->assertOk();
    $stored = Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail();

    $stored->forceFill(match ($state) {
        'flagged' => ['needs_review' => true, 'review_reasons' => ['Sale price differs from the agreement or list price.']],
        'reviewed' => ['needs_review' => true, 'review_reasons' => ['Sale price differs from the agreement or list price.'], 'reviewed_at' => now(), 'reviewed_by' => test()->user->id],
    })->save();

    // A reason still waiting stays next to the cancellation; one the admin already reviewed is not shown again.
    $reasons = match ($state) {
        'flagged' => ['Sale price differs from the agreement or list price.', 'Cancelled by a driver.'],
        'reviewed' => ['Cancelled by a driver.'],
    };

    push(['sale_cancellations' => [cancellation(['sale_uuid' => $sale['uuid']])]])->assertOk()
        ->assertJsonPath('data.sale_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.0.review_reasons', ReviewReason::labels($reasons));

    $stored->refresh();
    expect($stored->needs_review)->toBeTrue()
        ->and($stored->review_reasons)->toBe($reasons)
        ->and($stored->reviewed_at)->toBeNull()
        ->and($stored->reviewed_by)->toBeNull();
})->with(['flagged', 'reviewed']);

test('a cancelled sale of a product the truck did not carry gives back only what it discounted, even after a later load', function (): void {
    $soda = Product::factory()->create(['enabled' => true, 'price' => 500]);
    $sale = cancellableSale(['total' => 1000, 'details' => [['product_id' => $soda->id, 'quantity' => 2, 'unit_price' => 500]]]);
    push(['sales' => [$sale]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');
    app(StreetOperation::class)->loadRoute($this->route, [$soda->id => 10], $this->user);

    push(['sale_cancellations' => [cancellation(['sale_uuid' => $sale['uuid']])]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    $sodaStock = RouteInventory::query()->where('route_id', $this->route->id)->where('product_id', $soda->id)->value('current_quantity');
    expect((float) $sodaStock)->toBe(10.0)
        ->and(cancellationBalance())->toBe(1000.0)
        ->and(waterStock())->toBe(20.0);
});

test('a cancelled loose sale made before the driver had a route gives nothing back to a route created later that day', function (): void {
    $this->route->update(['date' => '2026-10-21']);
    $sale = cancellableSale();
    push(['sales' => [$sale]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');
    $laterRoute = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-22', 'status' => 'IN_PROGRESS']);
    $laterStock = RouteInventory::query()->create([
        'route_id' => $laterRoute->id, 'product_id' => $this->water->id, 'current_quantity' => 5, 'created_by' => $this->user->id,
    ]);

    push(['sale_cancellations' => [cancellation(['sale_uuid' => $sale['uuid']])]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    expect((float) $laterStock->fresh()->current_quantity)->toBe(5.0)
        ->and(waterStock())->toBe(20.0)
        ->and(cancellationBalance())->toBe(1000.0);
});

test('a replayed payment cancellation after the admin reviewed it keeps the review and books nothing', function (): void {
    $payment = cancellablePayment();
    push(['payments' => [$payment]])->assertOk();
    $first = cancellation(['payment_uuid' => $payment['uuid']]);
    push(['payment_cancellations' => [$first]])->assertOk()->assertJsonPath('data.payment_cancellations.0.status', 'accepted');
    $record = Payment::query()->where('client_uuid', $payment['uuid'])->firstOrFail();
    $record->markReviewed($this->user);
    $reviewedAt = $record->fresh()->reviewed_at;

    push(['payment_cancellations' => [$first]])->assertOk()
        ->assertJsonPath('data.payment_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.payment_cancellations.0.server_id', $record->id)
        ->assertJsonPath('data.payment_cancellations.0.review_reasons', []);

    $record->refresh();
    expect($record->reviewed_at?->toIso8601String())->toBe($reviewedAt?->toIso8601String())
        ->and($record->reviewed_by)->toBe($this->user->id)
        ->and($record->status)->toBe('CANCELLED')
        ->and(CustomerAccountEntry::query()->count())->toBe(2)
        ->and(cancellationBalance())->toBe(1000.0);
});

test('a sale records the route it discounted from: :case', function (string $case): void {
    $sale = cancellableSale();
    $expected = $this->route->id;

    if ($case === 'the route of its visit stop') {
        // A route has one driver per day, so the visit's route is another one than the driver's own.
        $otherRoute = Route::factory()->create(['assigned_driver_id' => Driver::factory()->create()->id, 'date' => '2026-10-22']);
        $stop = RouteStop::factory()->create(['route_id' => $otherRoute->id, 'customer_id' => $this->customer->id]);
        $visit = Visit::factory()->create([
            'route_stop_id' => $stop->id, 'customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'created_by' => $this->user->id,
        ]);
        $sale = cancellableSale(['visit_id' => $visit->id]);
        $expected = $otherRoute->id;
    }

    if ($case === 'none without a route') {
        $this->route->update(['date' => '2026-10-21']);
        $expected = null;
    }

    push(['sales' => [$sale]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');

    expect(Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail()->stock_route_id)->toBe($expected);
})->with(['the route of its visit stop', 'the driver route of the day', 'none without a route']);
