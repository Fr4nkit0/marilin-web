<?php

declare(strict_types=1);

use App\Models\CustomerAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('statusOn and scopeActive apply the same validity rule on every boundary', function (?string $validFrom, ?string $validTo, string $date, string $status): void {
    $agreement = CustomerAgreement::factory()->create(['valid_from' => $validFrom, 'valid_to' => $validTo])->fresh();

    expect($agreement->statusOn($date))->toBe($status)
        ->and($agreement->isActiveOn($date))->toBe($status === 'active')
        ->and(CustomerAgreement::query()->active($date)->whereKey($agreement->id)->exists())->toBe($status === 'active');
})->with([
    'first day' => ['2026-10-10', '2026-10-20', '2026-10-10', 'active'],
    'last day' => ['2026-10-10', '2026-10-20', '2026-10-20', 'active'],
    'day before the first day' => ['2026-10-10', '2026-10-20', '2026-10-09', 'upcoming'],
    'day after the last day' => ['2026-10-10', '2026-10-20', '2026-10-21', 'ended'],
    'one-day agreement' => ['2026-10-10', '2026-10-10', '2026-10-10', 'active'],
    'no end, starts that day' => ['2026-10-10', null, '2026-10-10', 'active'],
    'no end, long after' => ['2026-10-10', null, '2030-01-01', 'active'],
    'no end, before it starts' => ['2026-10-10', null, '2026-10-09', 'upcoming'],
    'future agreement' => ['2026-11-01', null, '2026-10-22', 'upcoming'],
    'ended agreement' => ['2026-09-01', '2026-09-30', '2026-10-22', 'ended'],
]);
