<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // 15:00 in Argentina is 18:00 UTC.
    $this->travelTo('2026-10-30 15:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $this->water = Product::factory()->create(['enabled' => true, 'price' => 500]);
    $this->method = PaymentMethod::factory()->create();
    CustomerBalance::factory()->create(['customer_id' => $this->customer->id, 'current_balance' => 1000]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function checkedPayment(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'payment_method_id' => test()->method->id,
        'amount' => 400,
        'paid_at' => '2026-10-30T17:30:00Z',
    ], $overrides);
}

function checkedBalance(): float
{
    return (float) CustomerBalance::query()->where('customer_id', test()->customer->id)->value('current_balance');
}

test('a payment dated more than five minutes after the server time is rejected and books nothing', function (): void {
    $payment = checkedPayment(['paid_at' => '2026-10-30T18:05:01Z']);

    push(['payments' => [$payment]])
        ->assertOk()
        ->assertJsonPath('data.payments.0.uuid', $payment['uuid'])
        ->assertJsonPath('data.payments.0.status', 'rejected')
        ->assertJsonPath('data.payments.0.server_id', null)
        ->assertJsonPath('data.payments.0.review_reasons', [])
        ->assertJsonPath('data.payments.0.error', 'The payment date is in the future.');

    expect(Payment::query()->count())->toBe(0)
        ->and(checkedBalance())->toBe(1000.0);
});

test('a payment up to five minutes ahead of the server time is accepted (clock drift)', function (): void {
    push(['payments' => [checkedPayment(['paid_at' => '2026-10-30T18:05:00Z'])]])
        ->assertOk()
        ->assertJsonPath('data.payments.0.status', 'accepted')
        ->assertJsonPath('data.payments.0.review_reasons', []);

    expect(checkedBalance())->toBe(600.0);
});

test('a payment larger than the debt plus the day\'s sales is accepted and left for review', function (): void {
    $payment = checkedPayment(['amount' => 1000.01]);

    $response = push(['payments' => [$payment]])
        ->assertOk()
        ->assertJsonPath('data.payments.0.status', 'accepted')
        ->assertJsonPath('data.payments.0.review_reasons', ["Payment exceeds the customer's debt."]);

    $record = Payment::query()->where('client_uuid', $payment['uuid'])->sole();

    expect($response->json('data.payments.0.server_id'))->toBe($record->id)
        ->and($record->needs_review)->toBeTrue()
        ->and($record->review_reasons)->toBe(["Payment exceeds the customer's debt."])
        ->and(checkedBalance())->toBe(-0.01);
});

test('a payment of exactly the debt is not flagged', function (): void {
    push(['payments' => [checkedPayment(['amount' => 1000])]])
        ->assertOk()
        ->assertJsonPath('data.payments.0.status', 'accepted')
        ->assertJsonPath('data.payments.0.review_reasons', []);

    expect(Payment::query()->sole()->needs_review)->toBeFalse();
});

test('the day\'s sales count once, through the balance they already raised', function (): void {
    // Booked before the payment: the debt is 1000 plus the day's sale of 500, so up to 1500 passes.
    $sale = [
        'uuid' => (string) Str::uuid(),
        'customer_id' => $this->customer->id,
        'date' => '2026-10-30T16:00:00Z',
        'total' => 500,
        'is_credit_sale' => true,
        'details' => [['product_id' => $this->water->id, 'quantity' => 1, 'unit_price' => 500]],
    ];
    // A sale of another day does not count.
    Sale::factory()->create(['customer_id' => $this->customer->id, 'date' => '2026-10-29 10:00:00', 'total' => 9000]);

    push(['sales' => [$sale], 'payments' => [checkedPayment(['amount' => 1500]), checkedPayment(['amount' => 0.01])]])
        ->assertOk()
        ->assertJsonPath('data.payments.0.review_reasons', [])
        // The first payment settled the whole debt, the day's sale included: anything more is over the debt.
        ->assertJsonPath('data.payments.1.review_reasons', ["Payment exceeds the customer's debt."]);
});

test('an over-debt payment on another driver\'s customer carries both reasons', function (): void {
    $this->customer->update(['driver_id' => Driver::factory()->create()->id]);

    push(['payments' => [checkedPayment(['amount' => 5000])]])
        ->assertOk()
        ->assertJsonPath('data.payments.0.review_reasons', ['Customer is assigned to another driver.', "Payment exceeds the customer's debt."]);
});
