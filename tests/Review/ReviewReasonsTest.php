<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Sync/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 18:00:00');
    $this->user = User::factory()->create(['enabled' => true, 'name' => 'Juan']);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    $this->water = Product::factory()->create(['enabled' => true, 'price' => 800]);
});

test('a sale on another driver\'s customer at a price off the list keeps both reasons, in the push answer and in the inbox', function (): void {
    $foreign = Customer::factory()->create(['driver_id' => Driver::factory()->create()->id, 'name' => 'Kiosco Ana']);
    $sale = [
        'uuid' => (string) Str::uuid(),
        'customer_id' => $foreign->id,
        'date' => '2026-10-22T13:30:00Z',
        'total' => 1400,
        'is_credit_sale' => false,
        'details' => [['product_id' => $this->water->id, 'quantity' => 2, 'unit_price' => 700]],
    ];
    Sanctum::actingAs($this->user, ['*']);

    push(['sales' => [$sale]])
        ->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reasons', [
            'El cliente está asignado a otro repartidor.',
            'Venta con precio distinto al acordado o de lista.',
        ]);

    $record = Sale::query()->where('client_uuid', $sale['uuid'])->sole();
    expect($record->review_reasons)->toBe([ReviewReason::ForeignCustomer->value, ReviewReason::PriceMismatch->value]);

    $this->actingAs(User::factory()->superUser()->create())
        ->get(route('review.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/Index')
            ->where('items.data.0.type', 'sale')
            ->where('items.data.0.reasons', [
                'El cliente está asignado a otro repartidor.',
                'Venta con precio distinto al acordado o de lista.',
            ]));

    // A replay answers the same reasons.
    Sanctum::actingAs($this->user, ['*']);
    push(['sales' => [$sale]])->assertJsonPath('data.sales.0.review_reasons', [
        'El cliente está asignado a otro repartidor.',
        'Venta con precio distinto al acordado o de lista.',
    ]);
});

test('flagging a record waiting for review adds the reason once', function (): void {
    $sale = Sale::factory()->create(['needs_review' => true, 'review_reasons' => [ReviewReason::ForeignCustomer->value]]);

    $sale->flag(ReviewReason::DisabledCustomer);
    $sale->flag(ReviewReason::DisabledCustomer);
    $sale->flag(ReviewReason::ForeignCustomer);

    expect($sale->fresh()->review_reasons)->toBe([ReviewReason::ForeignCustomer->value, ReviewReason::DisabledCustomer->value])
        ->and($sale->fresh()->needs_review)->toBeTrue();
});

test('a new event on a record waiting for review adds its reason; on a reviewed one it starts a new list', function (): void {
    $pending = Sale::factory()->create(['needs_review' => true, 'review_reasons' => [ReviewReason::PriceMismatch->value]]);
    $reviewed = Sale::factory()->create(['needs_review' => true, 'review_reasons' => [ReviewReason::PriceMismatch->value], 'reviewed_at' => now(), 'reviewed_by' => User::factory()->create()->id]);

    $pending->reopenReview(ReviewReason::DriverCancellation);
    $reviewed->reopenReview(ReviewReason::DriverCancellation);

    expect($pending->fresh()->review_reasons)->toBe([ReviewReason::PriceMismatch->value, ReviewReason::DriverCancellation->value])
        ->and($reviewed->fresh()->review_reasons)->toBe([ReviewReason::DriverCancellation->value])
        ->and($reviewed->fresh()->reviewed_at)->toBeNull();
});
