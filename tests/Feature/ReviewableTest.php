<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Visit;
use App\Review\Reviewable;
use App\Review\ReviewReason;
use App\Services\Review\ReviewInbox;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-23 12:00:00');
});

test('the pending review scope and predicate match flagged records that nobody reviewed', function (): void {
    $pending = Sale::factory()->create(['needs_review' => true, 'review_reason' => ReviewReason::ForeignCustomer->value]);
    $reviewed = Sale::factory()->create(['needs_review' => true, 'reviewed_at' => now(), 'reviewed_by' => User::factory()->create()->id]);
    $clean = Sale::factory()->create(['needs_review' => false]);

    expect(Sale::query()->pendingReview()->pluck('id')->all())->toBe([$pending->id])
        ->and($pending->isPendingReview())->toBeTrue()
        ->and($reviewed->isPendingReview())->toBeFalse()
        ->and($clean->isPendingReview())->toBeFalse();
});

test('flag marks a clean record with the reason', function (): void {
    $sale = Sale::factory()->create(['needs_review' => false]);

    $sale->flag(ReviewReason::DisabledCustomer);

    expect($sale->fresh()->needs_review)->toBeTrue()
        ->and($sale->fresh()->review_reason)->toBe(ReviewReason::DisabledCustomer->value);
});

test('flag does not change a record that is already flagged', function (): void {
    $sale = Sale::factory()->create(['needs_review' => true, 'review_reason' => ReviewReason::ForeignCustomer->value]);

    $sale->flag(ReviewReason::DisabledCustomer);

    expect($sale->fresh()->review_reason)->toBe(ReviewReason::ForeignCustomer->value);
});

test('flag does not re-flag a record the admin already reviewed', function (): void {
    $sale = Sale::factory()->create(['needs_review' => false, 'reviewed_at' => now()]);

    $sale->flag(ReviewReason::DisabledCustomer);

    expect($sale->fresh()->needs_review)->toBeFalse()
        ->and($sale->fresh()->review_reason)->toBeNull();
});

test('mark reviewed saves who and when and keeps the first reviewer', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $payment = Payment::factory()->create(['needs_review' => true]);

    $payment->markReviewed($first);

    expect($payment->fresh()->reviewed_by)->toBe($first->id)
        ->and($payment->fresh()->reviewed_at->toDateTimeString())->toBe('2026-10-23 12:00:00')
        ->and(fn () => $payment->fresh()->markReviewed($second))->toThrow(ModelNotFoundException::class)
        ->and($payment->fresh()->reviewed_by)->toBe($first->id);
});

test('mark reviewed rejects a record that is not waiting for review', function (): void {
    $payment = Payment::factory()->create(['needs_review' => false]);

    expect(fn () => $payment->markReviewed(User::factory()->create()))->toThrow(ModelNotFoundException::class);
});

test('the review columns are cast on every reviewable model', function (string $model): void {
    $casts = (new $model)->getCasts();

    expect($casts['needs_review'])->toBe('boolean')
        ->and($casts['reviewed_at'])->toBe('datetime');
})->with([Visit::class, Sale::class, Payment::class, RouteStop::class, Customer::class, ContainerMovement::class, CustomerAgreement::class]);

test('every inbox type comes from a model declaration', function (): void {
    $declared = collect([Visit::class, Sale::class, Payment::class, RouteStop::class, Customer::class, ContainerMovement::class, CustomerAgreement::class])
        ->each(fn (string $model) => expect(class_uses($model))->toHaveKey(Reviewable::class))
        ->map(fn (string $model): string => $model::reviewSource()->type)
        ->all();

    expect(ReviewInbox::TYPES)->toBe($declared);
});
