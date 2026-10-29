<?php

declare(strict_types=1);

use App\Customers\Neighborhoods\Models\Neighborhood;
use Database\Seeders\NeighborhoodSeeder;

function saltaNeighborhoodNames(): array
{
    return json_decode(file_get_contents(database_path('data/salta_neighborhoods.json')), true, flags: JSON_THROW_ON_ERROR);
}

test('the seeder loads every Salta neighborhood of the mobile list, enabled', function (): void {
    $this->seed(NeighborhoodSeeder::class);

    $names = Neighborhood::query()->orderBy('id')->pluck('name')->all();

    expect($names)->toHaveCount(398)
        ->and(collect($names)->sort()->values()->all())->toBe(collect(saltaNeighborhoodNames())->sort()->values()->all())
        ->and(Neighborhood::query()->where('enabled', false)->count())->toBe(0);
});

test('running the seeder twice keeps 398 neighborhoods and leaves a disabled one untouched', function (): void {
    $this->seed(NeighborhoodSeeder::class);
    $this->travel(1)->hour();
    $disabled = Neighborhood::query()->where('name', 'Centro')->firstOrFail();
    $disabled->update(['enabled' => false]);
    $updatedAt = $disabled->fresh()->updated_at;

    $this->travel(1)->hour();
    $this->seed(NeighborhoodSeeder::class);

    expect(Neighborhood::query()->count())->toBe(398)
        ->and($disabled->fresh()->enabled)->toBeFalse()
        ->and($disabled->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});
