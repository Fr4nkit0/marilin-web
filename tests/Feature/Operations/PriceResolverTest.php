<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAgreement;
use App\Models\Product;
use App\Operations\PriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->customer = Customer::factory()->create();
    $this->product = Product::factory()->create(['price' => 6500]);
});

function agreementOn(array $attributes): CustomerAgreement
{
    return CustomerAgreement::factory()->create([
        'customer_id' => test()->customer->id,
        'product_id' => test()->product->id,
        'valid_to' => null,
        ...$attributes,
    ]);
}

test('the list price is used when the customer has no agreement', function (): void {
    $resolved = app(PriceResolver::class)->priceFor($this->customer, $this->product, Carbon::parse('2026-10-22'));

    expect((float) $resolved->unitPrice)->toBe(6500.0)
        ->and($resolved->agreement)->toBeNull();
});

test('an agreement valid on the date beats the list price', function (): void {
    $agreement = agreementOn(['unit_price' => 5000, 'valid_from' => '2026-10-01']);

    $resolved = app(PriceResolver::class)->priceFor($this->customer, $this->product, Carbon::parse('2026-10-22'));

    expect((float) $resolved->unitPrice)->toBe(5000.0)
        ->and($resolved->agreement?->id)->toBe($agreement->id);
});

test('an expired or upcoming agreement falls back to the list price', function (): void {
    agreementOn(['unit_price' => 4000, 'valid_from' => '2026-09-01', 'valid_to' => '2026-09-30']);
    agreementOn(['unit_price' => 4500, 'valid_from' => '2026-11-01']);

    $resolved = app(PriceResolver::class)->priceFor($this->customer, $this->product, Carbon::parse('2026-10-22'));

    expect((float) $resolved->unitPrice)->toBe(6500.0)
        ->and($resolved->agreement)->toBeNull();
});

test('the validity of an agreement includes its first and last day', function (): void {
    agreementOn(['unit_price' => 5000, 'valid_from' => '2026-10-10', 'valid_to' => '2026-10-20']);

    $resolver = app(PriceResolver::class);

    expect((float) $resolver->priceFor($this->customer, $this->product, Carbon::parse('2026-10-10'))->unitPrice)->toBe(5000.0)
        ->and((float) $resolver->priceFor($this->customer, $this->product, Carbon::parse('2026-10-20'))->unitPrice)->toBe(5000.0)
        ->and((float) $resolver->priceFor($this->customer, $this->product, Carbon::parse('2026-10-21'))->unitPrice)->toBe(6500.0);
});

test('with several valid agreements the most recent valid_from wins', function (): void {
    agreementOn(['unit_price' => 5000, 'valid_from' => '2026-10-01']);
    $newest = agreementOn(['unit_price' => 4800, 'valid_from' => '2026-10-15']);

    $resolved = app(PriceResolver::class)->priceFor($this->customer, $this->product, Carbon::parse('2026-10-22'));

    expect((float) $resolved->unitPrice)->toBe(4800.0)
        ->and($resolved->agreement?->id)->toBe($newest->id);
});

test('agreements of another customer or product are ignored', function (): void {
    CustomerAgreement::factory()->create(['customer_id' => Customer::factory()->create()->id, 'product_id' => $this->product->id, 'unit_price' => 100, 'valid_from' => '2026-10-01', 'valid_to' => null]);
    CustomerAgreement::factory()->create(['customer_id' => $this->customer->id, 'product_id' => Product::factory()->create()->id, 'unit_price' => 100, 'valid_from' => '2026-10-01', 'valid_to' => null]);

    expect((float) app(PriceResolver::class)->priceFor($this->customer, $this->product, Carbon::parse('2026-10-22'))->unitPrice)->toBe(6500.0);
});
