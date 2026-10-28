<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 10:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin Marilin']);
    $this->customer = Customer::factory()->create(['name' => 'Hotel Altos de la Viña']);
    $this->bidon = Product::factory()->create(['name' => 'Bidón 20L', 'price' => 6500, 'enabled' => true]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function agreementFor(array $attributes = []): CustomerAgreement
{
    return CustomerAgreement::factory()->create([
        'customer_id' => test()->customer->id,
        'product_id' => test()->bidon->id,
        'unit_price' => 5000,
        'valid_from' => '2026-10-01',
        'valid_to' => null,
        'reason' => 'Cliente mayorista',
        'created_by' => test()->admin->id,
        ...$attributes,
    ]);
}

test('the customer page lists each agreement with its price, validity, author and status', function (): void {
    agreementFor();
    agreementFor(['unit_price' => 4800, 'valid_from' => '2026-09-01', 'valid_to' => '2026-09-30', 'reason' => null]);
    $upcoming = agreementFor(['unit_price' => 5200, 'valid_from' => '2026-11-01']);

    $this->actingAs($this->admin)
        ->get(route('customers.show', $this->customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Show')
            ->where('canManageAgreements', true)
            ->has('agreementProducts', 1)
            ->where('agreementProducts.0', ['id' => $this->bidon->id, 'name' => 'Bidón 20L', 'price' => 6500])
            ->has('agreements', 3)
            ->where('agreements.0', [
                'id' => $upcoming->id, 'product' => 'Bidón 20L', 'unitPrice' => 5200, 'validFrom' => '2026-11-01', 'validTo' => null,
                'reason' => 'Cliente mayorista', 'createdBy' => 'Admin Marilin', 'status' => 'upcoming',
            ])
            ->where('agreements.1.status', 'active')
            ->where('agreements.1.unitPrice', 5000)
            ->where('agreements.2.status', 'ended')
            ->where('agreements.2.validTo', '2026-09-30')
            ->missing('customer.agreements'));
});

test('a regular user sees the agreements but cannot manage them', function (): void {
    agreementFor();

    $this->actingAs(User::factory()->create())
        ->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page->where('canManageAgreements', false)->has('agreements', 1));
});

test('the admin creates an agreement for the customer', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.agreements.store', $this->customer), [
            'product_id' => $this->bidon->id,
            'unit_price' => '5500.50',
            'valid_from' => '2026-10-22',
            'valid_to' => '2026-12-31',
            'reason' => 'Promo fin de año',
        ])
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHasNoErrors();

    $agreement = CustomerAgreement::query()->sole();
    expect($agreement->customer_id)->toBe($this->customer->id)
        ->and($agreement->product_id)->toBe($this->bidon->id)
        ->and($agreement->unit_price)->toBe('5500.50')
        ->and($agreement->valid_from->toDateString())->toBe('2026-10-22')
        ->and($agreement->valid_to->toDateString())->toBe('2026-12-31')
        ->and($agreement->reason)->toBe('Promo fin de año')
        ->and($agreement->created_by)->toBe($this->admin->id);
});

test('an agreement without end date or reason is open-ended', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.agreements.store', $this->customer), [
            'product_id' => $this->bidon->id,
            'unit_price' => 5000,
            'valid_from' => '2026-10-22',
        ])
        ->assertSessionHasNoErrors();

    expect(CustomerAgreement::query()->sole())
        ->valid_to->toBeNull()
        ->reason->toBeNull();
});

test('an invalid agreement is rejected and nothing is stored', function (array $input, string $field): void {
    $this->actingAs($this->admin)
        ->post(route('customers.agreements.store', $this->customer), [
            'product_id' => $this->bidon->id,
            'unit_price' => 5000,
            'valid_from' => '2026-10-22',
            ...$input,
        ])
        ->assertSessionHasErrors($field);

    expect(CustomerAgreement::query()->count())->toBe(0);
})->with([
    'zero price' => [['unit_price' => 0], 'unit_price'],
    'negative price' => [['unit_price' => -10], 'unit_price'],
    'missing price' => [['unit_price' => null], 'unit_price'],
    'unknown product' => [['product_id' => 999_999], 'product_id'],
    'missing start' => [['valid_from' => null], 'valid_from'],
    'end before start' => [['valid_to' => '2026-10-21'], 'valid_to'],
    'long reason' => [['reason' => str_repeat('a', 501)], 'reason'],
]);

test('a disabled product cannot get a new agreement', function (): void {
    $this->bidon->update(['enabled' => false]);

    $this->actingAs($this->admin)
        ->post(route('customers.agreements.store', $this->customer), ['product_id' => $this->bidon->id, 'unit_price' => 5000, 'valid_from' => '2026-10-22'])
        ->assertSessionHasErrors('product_id');

    expect(CustomerAgreement::query()->count())->toBe(0);
});

test('ending an agreement makes yesterday its last day so it no longer applies today', function (): void {
    $agreement = agreementFor();

    $this->actingAs($this->admin)
        ->patch(route('customers.agreements.end', [$this->customer, $agreement]))
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHasNoErrors();

    $agreement->refresh();
    expect($agreement->valid_to->toDateString())->toBe('2026-10-21')
        ->and($agreement->unit_price)->toBe('5000.00')
        ->and(CustomerAgreement::query()->active()->count())->toBe(0);
});

test('ending an agreement that has not started yet keeps it from ever applying', function (): void {
    $agreement = agreementFor(['valid_from' => '2026-11-01']);

    $this->actingAs($this->admin)->patch(route('customers.agreements.end', [$this->customer, $agreement]))->assertSessionHasNoErrors();

    expect($agreement->fresh()->valid_to->toDateString())->toBe('2026-10-21')
        ->and(CustomerAgreement::query()->active('2026-11-15')->count())->toBe(0);
});

test('an agreement that already ended cannot be ended again', function (): void {
    $agreement = agreementFor(['valid_to' => '2026-10-10']);

    $this->actingAs($this->admin)
        ->patch(route('customers.agreements.end', [$this->customer, $agreement]))
        ->assertSessionHasErrors('agreement');

    expect($agreement->fresh()->valid_to->toDateString())->toBe('2026-10-10');
});

test('an agreement of another customer cannot be ended through this customer', function (): void {
    $other = agreementFor(['customer_id' => Customer::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->patch(route('customers.agreements.end', [$this->customer, $other]))
        ->assertNotFound();

    expect($other->fresh()->valid_to)->toBeNull();
});

test('a regular user can neither create nor end agreements', function (): void {
    $agreement = agreementFor();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('customers.agreements.store', $this->customer), ['product_id' => $this->bidon->id, 'unit_price' => 1, 'valid_from' => '2026-10-22'])
        ->assertForbidden();
    $this->actingAs($user)
        ->patch(route('customers.agreements.end', [$this->customer, $agreement]))
        ->assertForbidden();

    expect(CustomerAgreement::query()->count())->toBe(1)
        ->and($agreement->fresh()->valid_to)->toBeNull();
});
