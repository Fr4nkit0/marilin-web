<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->actingAs($this->admin);

    $this->martinez = Customer::factory()->create(['name' => 'Ana Martínez']);
    $this->perez = Customer::factory()->create(['name' => 'Kiosco Pérez']);
    $this->other = Customer::factory()->create(['name' => 'Hotel Sol']);
});

/**
 * The ids a list page sends under `$key`, sorted; `$paginated` lists read `$key.data`.
 *
 * @param  array<string, mixed>  $query
 * @return list<int>
 */
function searchedIds(string $routeName, array $query, string $key, bool $paginated = true): array
{
    $ids = [];
    test()->get(route($routeName, $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($key, $paginated, &$ids): void {
            $rows = $page->toArray()['props'][$key];
            $ids = array_column($paginated ? $rows['data'] : $rows, 'id');
        });
    sort($ids);

    return $ids;
}

test('the customers search ignores accents and case', function (string $term, string $expected): void {
    expect(searchedIds('customers.index', ['search' => $term], 'customers'))->toBe([$this->{$expected}->id]);
})->with([
    'without the accent' => ['martinez', 'martinez'],
    'in capitals' => ['PEREZ', 'perez'],
    'with the accent in capitals' => ['PÉREZ', 'perez'],
]);

test('the customers search still matches the phone and the street of the primary address', function (): void {
    $this->other->update(['phone_number' => '3874112233']);
    CustomerAddress::factory()->create(['customer_id' => $this->martinez->id, 'is_primary' => true, 'street' => 'Avenida Belgrano 340']);

    expect(searchedIds('customers.index', ['search' => '4112'], 'customers'))->toBe([$this->other->id])
        ->and(searchedIds('customers.index', ['search' => 'BELGRANO'], 'customers'))->toBe([$this->martinez->id]);
});

test('the sales search finds the customer ignoring accents and case', function (string $term, string $expected): void {
    $sales = collect(['martinez', 'perez', 'other'])->mapWithKeys(fn (string $who): array => [
        $who => Sale::factory()->create(['customer_id' => $this->{$who}->id, 'date' => '2026-10-20 10:00:00']),
    ]);

    expect(searchedIds('sales.index', ['search' => $term], 'sales'))->toBe([$sales[$expected]->id]);
})->with([
    'without the accent' => ['martinez', 'martinez'],
    'in capitals' => ['PEREZ', 'perez'],
]);

test('the payments search finds the customer ignoring accents and case', function (string $term, string $expected): void {
    $cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo']);
    $payments = collect(['martinez', 'perez', 'other'])->mapWithKeys(fn (string $who): array => [
        $who => Payment::factory()->create([
            'customer_id' => $this->{$who}->id,
            'payment_method_id' => $cash->id,
            'status' => 'CONFIRMED',
            'sale_id' => null,
            'paid_at' => '2026-10-20 10:00:00',
        ]),
    ]);

    expect(searchedIds('payments.index', ['search' => $term], 'payments'))->toBe([$payments[$expected]->id]);
})->with([
    'without the accent' => ['martinez', 'martinez'],
    'in capitals' => ['PEREZ', 'perez'],
]);

test('the drivers search finds the driver by name ignoring accents and case', function (string $term): void {
    $ramirez = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Raúl Ramírez'])->id, 'enabled' => true]);
    Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Marcos Morales'])->id, 'enabled' => true]);

    expect(searchedIds('drivers.index', ['search' => $term], 'drivers', paginated: false))->toBe([$ramirez->id]);
})->with(['without the accent' => 'raul ramirez', 'in capitals' => 'RAMIREZ']);

test('the drivers list without a search lists every enabled driver', function (): void {
    Driver::factory()->count(2)->create(['enabled' => true]);
    Driver::factory()->create(['enabled' => false]);

    expect(searchedIds('drivers.index', [], 'drivers', paginated: false))
        ->toBe(Driver::query()->where('enabled', true)->orderBy('id')->pluck('id')->all());
});

test('the users search ignores accents and case', function (string $term): void {
    $gomez = User::factory()->create(['name' => 'Lucía Gómez', 'is_super_user' => false]);
    Driver::factory()->create(['user_id' => $gomez->id]);
    $other = User::factory()->create(['name' => 'Carlos Ruiz', 'is_super_user' => false]);
    Driver::factory()->create(['user_id' => $other->id]);

    expect(searchedIds('users.index', ['search' => $term], 'users', paginated: false))->toBe([$gomez->id]);
})->with(['without the accent' => 'lucia gomez', 'in capitals' => 'GOMEZ']);

test('the search treats % and _ as plain characters', function (): void {
    expect(searchedIds('customers.index', ['search' => '%'], 'customers'))->toBe([]);
});
