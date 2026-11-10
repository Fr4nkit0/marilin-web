<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['enabled' => true]);
});

/**
 * The names on the list's page, in the order the server sent them.
 *
 * @param  array<string, string>  $query
 * @return list<string>
 */
function listedCustomers(object $test, array $query = [], int $page = 1): array
{
    $names = [];

    $test->actingAs($test->user)
        ->get(route('customers.index', [...$query, 'page' => $page]))
        ->assertOk()
        ->assertInertia(function (Assert $inertia) use (&$names): void {
            $names = array_column($inertia->toArray()['props']['customers']['data'], 'name');
        });

    return $names;
}

test('without a sort the list stays in alphabetical order and echoes no sort', function (): void {
    foreach (['Carla', 'Ana', 'Beto'] as $name) {
        Customer::factory()->create(['name' => $name]);
    }

    expect(listedCustomers($this))->toBe(['Ana', 'Beto', 'Carla']);

    $this->actingAs($this->user)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', null)
            ->where('filters.direction', null));
});

test('the list sorts by name both ways', function (): void {
    foreach (['Carla', 'Ana', 'Beto'] as $name) {
        Customer::factory()->create(['name' => $name]);
    }

    expect(listedCustomers($this, ['sort' => 'name', 'direction' => 'desc']))->toBe(['Carla', 'Beto', 'Ana']);

    $this->actingAs($this->user)
        ->get(route('customers.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'desc'));
});

test('the list sorts by the current account balance, a customer without one counting as zero', function (): void {
    $owes = Customer::factory()->create(['name' => 'Debe mucho']);
    CustomerBalance::factory()->create(['customer_id' => $owes->id, 'current_balance' => 9000]);
    $little = Customer::factory()->create(['name' => 'Debe poco']);
    CustomerBalance::factory()->create(['customer_id' => $little->id, 'current_balance' => 100]);
    $credit = Customer::factory()->create(['name' => 'A favor']);
    CustomerBalance::factory()->create(['customer_id' => $credit->id, 'current_balance' => -500]);
    Customer::factory()->create(['name' => 'Sin cuenta']);

    expect(listedCustomers($this, ['sort' => 'balance', 'direction' => 'desc']))
        ->toBe(['Debe mucho', 'Debe poco', 'Sin cuenta', 'A favor']);
    expect(listedCustomers($this, ['sort' => 'balance', 'direction' => 'asc']))
        ->toBe(['A favor', 'Sin cuenta', 'Debe poco', 'Debe mucho']);
});

test('the list sorts by the containers a customer holds, adding every container type', function (): void {
    $many = Customer::factory()->create(['name' => 'Muchos']);
    CustomerContainerBalance::factory()->create(['customer_id' => $many->id, 'balance' => 4]);
    CustomerContainerBalance::factory()->create(['customer_id' => $many->id, 'balance' => 5]);
    $few = Customer::factory()->create(['name' => 'Pocos']);
    CustomerContainerBalance::factory()->create(['customer_id' => $few->id, 'balance' => 6]);
    Customer::factory()->create(['name' => 'Ninguno']);

    expect(listedCustomers($this, ['sort' => 'containers', 'direction' => 'desc']))->toBe(['Muchos', 'Pocos', 'Ninguno']);
});

test('the list sorts by the driver name and by the street of the primary address', function (): void {
    $zoe = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Zoe'])->id]);
    $abel = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Abel'])->id]);
    $first = Customer::factory()->create(['name' => 'De Zoe', 'driver_id' => $zoe->id]);
    $second = Customer::factory()->create(['name' => 'De Abel', 'driver_id' => $abel->id]);
    CustomerAddress::factory()->create(['customer_id' => $first->id, 'is_primary' => true, 'street' => 'Alvarado 10']);
    CustomerAddress::factory()->create(['customer_id' => $second->id, 'is_primary' => true, 'street' => 'Mitre 50']);

    expect(listedCustomers($this, ['sort' => 'driver', 'direction' => 'asc']))->toBe(['De Abel', 'De Zoe']);
    expect(listedCustomers($this, ['sort' => 'address', 'direction' => 'asc']))->toBe(['De Zoe', 'De Abel']);
});

test('the sort covers the whole list, not just the page, and every page keeps it', function (): void {
    foreach (range(1, 16) as $number) {
        Customer::factory()->create(['name' => sprintf('Cliente %02d', $number)]);
    }

    expect(listedCustomers($this, ['sort' => 'name', 'direction' => 'desc'])[0])->toBe('Cliente 16');
    expect(listedCustomers($this, ['sort' => 'name', 'direction' => 'desc'], page: 2))->toBe(['Cliente 01']);
});

test('an unknown column or direction falls back to the alphabetical order', function (array $query): void {
    foreach (['Carla', 'Ana', 'Beto'] as $name) {
        Customer::factory()->create(['name' => $name]);
    }

    expect(listedCustomers($this, $query))->toBe(['Ana', 'Beto', 'Carla']);

    $this->actingAs($this->user)
        ->get(route('customers.index', $query))
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', null)->where('filters.direction', null));
})->with([
    'unknown column' => [['sort' => 'password', 'direction' => 'asc']],
    'sql in the column' => [['sort' => 'name; drop table customers', 'direction' => 'asc']],
    'unknown direction' => [['sort' => 'name', 'direction' => 'sideways']],
]);
