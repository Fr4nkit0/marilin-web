<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Nothing is ever deleted: drivers and customers are disabled, so no foreign key may delete its rows in cascade
 * and the database refuses to delete a row that others still reference. Each refused delete runs in its own
 * savepoint so the test transaction survives the error.
 */

test('no foreign key deletes its rows in cascade', function (): void {
    $cascades = collect(DB::select(<<<'SQL'
        SELECT conrelid::regclass::text AS "table", conname AS "constraint"
        FROM pg_constraint
        WHERE contype = 'f' AND confdeltype = 'c' AND connamespace = 'public'::regnamespace
        SQL))->map(fn (object $row): string => "{$row->table}.{$row->constraint}");

    expect($cascades->all())->toBe([]);
});

test('deleting the user of a driver with customers is refused and keeps them', function (): void {
    $customer = Customer::factory()->create();
    $driver = $customer->driver;

    expect(fn () => DB::transaction(fn () => $driver->user->delete()))->toThrow(QueryException::class);

    expect(Driver::query()->find($driver->id))->not->toBeNull()
        ->and(Customer::query()->find($customer->id)?->driver_id)->toBe($driver->id);
});

test('deleting a driver with customers is refused and keeps them', function (): void {
    $customer = Customer::factory()->create();
    $driver = $customer->driver;

    expect(fn () => DB::transaction(fn () => $driver->delete()))->toThrow(QueryException::class);

    expect(Driver::query()->find($driver->id))->not->toBeNull()
        ->and(Customer::query()->find($customer->id)?->driver_id)->toBe($driver->id);
});
