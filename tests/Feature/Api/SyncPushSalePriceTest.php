<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 18:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $this->water = Product::factory()->create(['enabled' => true, 'price' => 800]);
});

/**
 * @return array<string, mixed>
 */
function pricedSale(string|int|float $unitPrice, array $line = [], array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'date' => '2026-10-22T10:30:00Z',
        'total' => 2 * (float) $unitPrice,
        'is_credit_sale' => false,
        'details' => [array_replace(['product_id' => test()->water->id, 'quantity' => 2, 'unit_price' => $unitPrice], $line)],
    ], $overrides);
}

function pricedAgreement(array $attributes = []): CustomerAgreement
{
    return CustomerAgreement::factory()->create([
        'customer_id' => test()->customer->id,
        'product_id' => test()->water->id,
        'unit_price' => 700,
        'valid_from' => '2026-10-01',
        'valid_to' => null,
        ...$attributes,
    ]);
}

test('a sale at the agreement price is accepted and clean', function (): void {
    $agreement = pricedAgreement();
    $sale = pricedSale(700.00, ['customer_agreement_id' => $agreement->id]);

    push(['sales' => [$sale]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reason', null);

    expect(Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail()->needs_review)->toBeFalse();
});

test('a sale at the list price is clean when there is no agreement', function (): void {
    push(['sales' => [pricedSale(800)]])->assertOk()->assertJsonPath('data.sales.0.review_reason', null);
});

test('a numeric-string price equal to the resolved price is not flagged', function (): void {
    pricedAgreement();

    push(['sales' => [pricedSale('700')]])->assertOk()->assertJsonPath('data.sales.0.review_reason', null);
    push(['sales' => [pricedSale('7e2')]])->assertOk()->assertJsonPath('data.sales.0.review_reason', null);
});

test('a sale at the list price while an agreement was valid is accepted, flagged and keeps the prices the app sent', function (): void {
    pricedAgreement();
    $sale = pricedSale(800);

    $response = push(['sales' => [$sale]]);

    $response->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reason', ReviewReason::PriceMismatch->value);

    $stored = Sale::query()->with('details')->where('client_uuid', $sale['uuid'])->firstOrFail();
    expect($stored->needs_review)->toBeTrue()
        ->and($stored->review_reason)->toBe(ReviewReason::PriceMismatch->value)
        ->and((float) $stored->total)->toBe(1600.0)
        ->and((float) $stored->details->sole()->unit_price)->toBe(800.0)
        ->and((float) CustomerAccountEntry::query()->where('sale_id', $stored->id)->sole()->amount)->toBe(1600.0);
});

test('a sale at the price of an agreement that was over on the sale date is flagged', function (): void {
    $expired = pricedAgreement(['unit_price' => 500, 'valid_from' => '2026-09-01', 'valid_to' => '2026-10-10']);

    push(['sales' => [pricedSale(500, ['customer_agreement_id' => $expired->id])]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reason', ReviewReason::PriceMismatch->value);
});

test('a line that references an agreement but does not charge what it says is flagged', function (): void {
    $agreement = pricedAgreement();

    push(['sales' => [pricedSale(650, ['customer_agreement_id' => $agreement->id])]])->assertOk()
        ->assertJsonPath('data.sales.0.review_reason', ReviewReason::PriceMismatch->value);
});

test('a referenced agreement that was not valid but whose price equals the resolved price is not flagged', function (): void {
    $expired = pricedAgreement(['unit_price' => 800, 'valid_from' => '2026-09-01', 'valid_to' => '2026-10-10']);

    push(['sales' => [pricedSale(800, ['customer_agreement_id' => $expired->id])]])->assertOk()
        ->assertJsonPath('data.sales.0.review_reason', null);
});

test('one wrong line among several flags the sale; cents are compared as two-decimal amounts', function (): void {
    $soda = Product::factory()->create(['enabled' => true, 'price' => 400.50]);
    $sale = pricedSale(800, [], [
        'total' => 1600 + 400.5,
        'details' => [
            ['product_id' => $this->water->id, 'quantity' => 2, 'unit_price' => 800],
            ['product_id' => $soda->id, 'quantity' => 1, 'unit_price' => 400.5],
        ],
    ]);
    push(['sales' => [$sale]])->assertOk()->assertJsonPath('data.sales.0.review_reason', null);

    $wrong = pricedSale(800, [], [
        'total' => 1600 + 400.51,
        'details' => [
            ['product_id' => $this->water->id, 'quantity' => 2, 'unit_price' => 800],
            ['product_id' => $soda->id, 'quantity' => 1, 'unit_price' => 400.51],
        ],
    ]);
    push(['sales' => [$wrong]])->assertOk()->assertJsonPath('data.sales.0.review_reason', ReviewReason::PriceMismatch->value);
});

test('the customer reason wins over the price reason', function (): void {
    pricedAgreement();
    $foreign = Customer::factory()->create(['driver_id' => Driver::factory()->create(['enabled' => true])->id]);
    CustomerAgreement::factory()->create(['customer_id' => $foreign->id, 'product_id' => $this->water->id, 'unit_price' => 700, 'valid_from' => '2026-10-01', 'valid_to' => null]);

    push(['sales' => [pricedSale(800, [], ['customer_id' => $foreign->id])]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reason', ReviewReason::ForeignCustomer->value);
});

test('a flagged sale sent again returns the same reason and books nothing twice', function (): void {
    pricedAgreement();
    $sale = pricedSale(800);

    $first = push(['sales' => [$sale]]);
    $second = push(['sales' => [$sale]]);

    $second->assertOk()
        ->assertJsonPath('data.sales.0.server_id', $first->json('data.sales.0.server_id'))
        ->assertJsonPath('data.sales.0.review_reason', ReviewReason::PriceMismatch->value);
    expect(Sale::query()->count())->toBe(1)->and(CustomerAccountEntry::query()->count())->toBe(1);
});

test('the review inbox shows the price reason in the language of the panel', function (): void {
    pricedAgreement();
    push(['sales' => [pricedSale(800)]])->assertOk();

    $rows = null;
    $this->actingAs(User::factory()->superUser()->create())->get('/review')->assertOk()
        ->assertInertia(function (Assert $page) use (&$rows): void {
            $rows = $page->toArray()['props']['items']['data'];
        });

    expect(collect($rows)->pluck('reason', 'type')->all())->toBe(['sale' => 'Venta con precio distinto al acordado o de lista.']);
});
