<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Routes\Visits\Models\Visit;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\Payments\Models\Transaction;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
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
    $this->soda = Product::factory()->create(['enabled' => true, 'price' => 400.50]);
    $this->waterStock = RouteInventory::query()->create([
        'route_id' => $this->route->id,
        'product_id' => $this->water->id,
        'current_quantity' => 20,
        'created_by' => $this->user->id,
    ]);
    // The truck carries what the sales here sell, so a sale with stock left goes to review only for what each test is about.
    RouteInventory::query()->create([
        'route_id' => $this->route->id,
        'product_id' => $this->soda->id,
        'current_quantity' => 20,
        'created_by' => $this->user->id,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salePayload(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'date' => '2026-10-22T10:30:00Z',
        'total' => 2500.50,
        'is_credit_sale' => true,
        'notes' => 'Cobra el viernes',
        'details' => [
            ['product_id' => test()->water->id, 'quantity' => 3, 'unit_price' => 700.00],
            ['product_id' => test()->soda->id, 'quantity' => 1, 'unit_price' => 400.50],
        ],
    ], $overrides);
}

test('push accepts a sale with its details, unit prices and the date the app set', function (): void {
    $payload = salePayload();

    $response = push(['sales' => [$payload]]);

    $sale = Sale::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.sales.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.server_id', $sale->id)
        ->assertJsonPath('data.sales.0.review_reasons', []);

    expect($sale->customer_id)->toBe($this->customer->id)
        ->and($sale->date->toIso8601String())->toBe('2026-10-22T07:30:00-03:00')
        ->and((float) $sale->total)->toBe(2500.5)
        ->and($sale->status)->toBe('confirmed')
        ->and($sale->is_credit_sale)->toBeTrue()
        ->and($sale->notes)->toBe('Cobra el viernes')
        ->and($sale->created_by)->toBe($this->user->id)
        ->and($sale->details()->count())->toBe(2)
        ->and((float) $sale->details()->where('product_id', $this->water->id)->firstOrFail()->unit_price)->toBe(700.0);
});

test('push keeps the unit price the app sent even when it differs from the list price', function (): void {
    $this->water->update(['price' => 950]);

    push(['sales' => [salePayload()]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');

    $detail = Sale::query()->latest('id')->firstOrFail()->details()->where('product_id', $this->water->id)->firstOrFail();
    expect((float) $detail->unit_price)->toBe(700.0);
});

test('push books an account entry for an accepted sale, raises the customer balance and discounts the route inventory by product', function (): void {
    CustomerBalance::factory()->create(['customer_id' => $this->customer->id, 'current_balance' => 1000]);
    $payload = salePayload();

    push(['sales' => [$payload]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');

    $sale = Sale::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    $entry = CustomerAccountEntry::query()->where('sale_id', $sale->id)->firstOrFail();
    $balance = CustomerBalance::query()->where('customer_id', $this->customer->id)->firstOrFail();

    expect($entry->entry_type)->toBe('SALE')
        ->and($entry->direction)->toBe(1)
        ->and((float) $entry->amount)->toBe(2500.5)
        ->and($entry->customer_id)->toBe($this->customer->id)
        ->and((float) $balance->current_balance)->toBe(3500.5)
        ->and($balance->last_entry_id)->toBe($entry->id)
        ->and((float) $this->waterStock->fresh()->current_quantity)->toBe(17.0);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentPayload(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'payment_method_id' => PaymentMethod::factory()->create()->id,
        'amount' => 1200.25,
        'paid_at' => '2026-10-22T11:45:00Z',
    ], $overrides);
}

test('push books a cash transaction and an opposite account entry for an accepted payment and lowers the balance', function (): void {
    CustomerBalance::factory()->create(['customer_id' => $this->customer->id, 'current_balance' => 3000]);
    $sale = salePayload(['total' => 100, 'details' => [['product_id' => $this->soda->id, 'quantity' => 1, 'unit_price' => 100]]]);
    $payload = paymentPayload(['sale_uuid' => $sale['uuid']]);

    $response = push(['payments' => [$payload], 'sales' => [$sale]]);

    $response->assertOk()
        ->assertJsonPath('data.payments.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.payments.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.status', 'accepted');

    $payment = Payment::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    $entry = CustomerAccountEntry::query()->where('payment_id', $payment->id)->firstOrFail();

    expect($response->json('data.payments.0.server_id'))->toBe($payment->id)
        ->and($payment->status)->toBe('CONFIRMED')
        ->and($payment->received_by)->toBe($this->user->id)
        ->and($payment->paid_at->toIso8601String())->toBe('2026-10-22T08:45:00-03:00')
        ->and($payment->sale_id)->toBe(Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail()->id)
        ->and($payment->transaction->type)->toBe('IN')
        ->and((float) $payment->transaction->amount)->toBe(1200.25)
        ->and($entry->entry_type)->toBe('PAYMENT')
        ->and($entry->direction)->toBe(-1)
        ->and((float) $entry->amount)->toBe(1200.25)
        ->and((float) CustomerBalance::query()->where('customer_id', $this->customer->id)->firstOrFail()->current_balance)->toBe(1899.75);
});

test('push accepts a :entity on a customer of another driver or a disabled one, flags it for review and still books it', function (string $entity, string $case): void {
    $customer = $case === 'disabled'
        ? Customer::factory()->disabled()->create(['driver_id' => $this->driver->id])
        : Customer::factory()->create(['driver_id' => Driver::factory()->create(['enabled' => true])->id]);
    $payload = $entity === 'sales'
        ? salePayload(['customer_id' => $customer->id])
        : paymentPayload(['customer_id' => $customer->id]);
    // The customer owes more than the payment, so the customer reason is the only one (S4).
    CustomerBalance::factory()->create(['customer_id' => $customer->id, 'current_balance' => 100000]);

    $response = push([$entity => [$payload]]);

    $response->assertOk()->assertJsonPath("data.{$entity}.0.status", 'accepted');

    $record = ($entity === 'sales' ? Sale::class : Payment::class)::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    $reason = $response->json("data.{$entity}.0.review_reasons.0");

    expect($reason)->toBeString()->not->toBeEmpty()
        ->and($record->needs_review)->toBeTrue()
        ->and(ReviewReason::labels($record->review_reasons))->toBe([$reason])
        ->and(CustomerAccountEntry::query()->where('customer_id', $customer->id)->count())->toBe(1);
})->with([
    'sale of another driver' => ['sales', 'other driver'],
    'sale of a disabled customer' => ['sales', 'disabled'],
    'payment of another driver' => ['payments', 'other driver'],
    'payment of a disabled customer' => ['payments', 'disabled'],
]);

test('push books nothing twice when a sale and a payment are sent again', function (): void {
    CustomerBalance::factory()->create(['customer_id' => $this->customer->id, 'current_balance' => 0]);
    $sale = salePayload();
    $payment = paymentPayload();
    $records = ['sales' => [$sale], 'payments' => [$payment]];

    $first = push($records);
    $second = push($records);

    $second->assertOk()
        ->assertJsonPath('data.sales.0.server_id', $first->json('data.sales.0.server_id'))
        ->assertJsonPath('data.payments.0.server_id', $first->json('data.payments.0.server_id'));

    expect(Sale::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(CustomerAccountEntry::query()->count())->toBe(2)
        ->and((float) CustomerBalance::query()->firstOrFail()->current_balance)->toBe(1300.25)
        ->and((float) $this->waterStock->fresh()->current_quantity)->toBe(17.0);
});

test('push rejects a sale whose total does not match its lines and leaves no trace of it', function (): void {
    $broken = salePayload(['total' => 999]);
    $valid = salePayload();

    $response = push(['sales' => [$broken, $valid]]);

    $response->assertOk()
        ->assertJsonPath('data.sales.0.status', 'rejected')
        ->assertJsonPath('data.sales.0.server_id', null)
        ->assertJsonPath('data.sales.1.status', 'accepted');

    expect($response->json('data.sales.0.error'))->toContain('total')
        ->and(Sale::query()->where('client_uuid', $broken['uuid'])->exists())->toBeFalse()
        ->and(CustomerAccountEntry::query()->count())->toBe(1)
        ->and((float) $this->waterStock->fresh()->current_quantity)->toBe(17.0);
});

test('push rejects a sale with a fractional quantity and leaves no trace of it', function (): void {
    $fractional = salePayload(['total' => 1050, 'details' => [['product_id' => $this->water->id, 'quantity' => 1.5, 'unit_price' => 700]]]);

    $response = push(['sales' => [$fractional]]);

    $response->assertOk()
        ->assertJsonPath('data.sales.0.status', 'rejected')
        ->assertJsonPath('data.sales.0.server_id', null);

    expect($response->json('data.sales.0.error'))->toBe('El campo cantidad debe ser un número entero.')
        ->and(Sale::query()->where('client_uuid', $fractional['uuid'])->exists())->toBeFalse()
        ->and((float) $this->waterStock->fresh()->current_quantity)->toBe(20.0);
});

test('push rejects a payment that points at a sale of another customer and a sale that points at a visit of another customer', function (): void {
    $other = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $otherSale = salePayload(['customer_id' => $other->id]);
    push(['sales' => [$otherSale]])->assertOk();
    $otherVisit = Visit::factory()->create(['customer_id' => $other->id, 'driver_id' => $this->driver->id, 'created_by' => $this->user->id]);

    $payment = paymentPayload(['sale_uuid' => $otherSale['uuid']]);
    $sale = salePayload(['visit_id' => $otherVisit->id]);

    $response = push(['sales' => [$sale], 'payments' => [$payment]]);

    $response->assertOk()
        ->assertJsonPath('data.sales.0.status', 'rejected')
        ->assertJsonPath('data.payments.0.status', 'rejected');

    expect($response->json('data.payments.0.error'))->toBe('La venta pertenece a otro cliente.')
        ->and($response->json('data.sales.0.error'))->toBe('La visita pertenece a otro cliente.')
        ->and(Payment::query()->count())->toBe(0)
        ->and(Sale::query()->where('client_uuid', $sale['uuid'])->exists())->toBeFalse()
        ->and(CustomerAccountEntry::query()->count())->toBe(1);
});

test('push resolves a sale line agreement sent as customer_agreement_uuid', function (): void {
    $agreement = CustomerAgreement::factory()->create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->water->id,
        'client_uuid' => (string) Str::uuid(),
    ]);
    $payload = salePayload([
        'total' => 2100.00,
        'details' => [['product_id' => $this->water->id, 'customer_agreement_uuid' => $agreement->client_uuid, 'quantity' => 3, 'unit_price' => 700.00]],
    ]);

    push(['sales' => [$payload]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');

    expect(Sale::query()->sole()->details->sole()->customer_agreement_id)->toBe($agreement->id);
});

test('push resolves an agreement pushed in the same request before the sale', function (): void {
    $agreementUuid = (string) Str::uuid();
    $payload = salePayload([
        'total' => 2100.00,
        'details' => [['product_id' => $this->water->id, 'customer_agreement_uuid' => $agreementUuid, 'quantity' => 3, 'unit_price' => 700.00]],
    ]);

    $response = push([
        'customer_agreements' => [[
            'uuid' => $agreementUuid, 'customer_id' => $this->customer->id, 'product_id' => $this->water->id,
            'unit_price' => 700, 'valid_from' => '2026-10-22',
        ]],
        'sales' => [$payload],
    ])->assertOk();

    $agreement = CustomerAgreement::query()->sole();
    expect($response->json('data.sales.0.status'))->toBe('accepted')
        ->and(Sale::query()->sole()->details->sole()->customer_agreement_id)->toBe($agreement->id);
});

test('push still accepts a sale line that only sends customer_agreement_id', function (): void {
    $agreement = CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->water->id]);
    $payload = salePayload([
        'total' => 2100.00,
        'details' => [['product_id' => $this->water->id, 'customer_agreement_id' => $agreement->id, 'quantity' => 3, 'unit_price' => 700.00]],
    ]);

    push(['sales' => [$payload]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');

    expect(Sale::query()->sole()->details->sole()->customer_agreement_id)->toBe($agreement->id);
});

test('push rejects a sale whose line agreement belongs to another customer and leaves no trace of it', function (string $reference): void {
    $other = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $agreement = CustomerAgreement::factory()->create([
        'customer_id' => $other->id, 'product_id' => $this->water->id, 'client_uuid' => (string) Str::uuid(),
    ]);
    $line = ['product_id' => $this->water->id, 'quantity' => 3, 'unit_price' => 700.00];
    $line += $reference === 'uuid' ? ['customer_agreement_uuid' => $agreement->client_uuid] : ['customer_agreement_id' => $agreement->id];
    $payload = salePayload(['total' => 2100.00, 'details' => [$line]]);

    $result = push(['sales' => [$payload]])->assertOk()->json('data.sales.0');

    expect($result['status'])->toBe('rejected')
        ->and($result['error'])->toBe('El acuerdo de precio pertenece a otro cliente.')
        ->and(Sale::query()->count())->toBe(0)
        ->and(CustomerAgreement::query()->count())->toBe(1);
})->with(['uuid', 'id']);

test('push rejects a sale line agreement uuid that matches nothing', function (): void {
    $payload = salePayload([
        'total' => 2100.00,
        'details' => [['product_id' => $this->water->id, 'customer_agreement_uuid' => (string) Str::uuid(), 'quantity' => 3, 'unit_price' => 700.00]],
    ]);

    $result = push(['sales' => [$payload]])->assertOk()->json('data.sales.0');

    expect($result['status'])->toBe('rejected')->and(Sale::query()->count())->toBe(0);
});

test('push still accepts numeric-string ids and quantities on sales and payments, as before the street operation', function (): void {
    $sale = salePayload([
        'total' => 1400,
        'details' => ['first' => ['product_id' => (string) $this->water->id, 'quantity' => '2', 'unit_price' => 700]],
    ]);
    $payment = paymentPayload(['payment_method_id' => (string) PaymentMethod::factory()->create()->id]);

    $response = push(['sales' => [$sale], 'payments' => [$payment]]);

    $response->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.payments.0.status', 'accepted');

    expect((float) $this->waterStock->fresh()->current_quantity)->toBe(18.0)
        ->and(Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail()->details()->firstOrFail()->quantity)->toBe(2);
});
