<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerReassignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-11-11 10:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->juan = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Juan'])->id]);
    $this->carlos = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Carlos'])->id]);
    $this->ana = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Ana'])->id]);
});

function transferCustomer(Driver $driver, string $name, ?string $neighborhood, bool $enabled = true): Customer
{
    $customer = Customer::factory()->create(['driver_id' => $driver->id, 'name' => $name, 'enabled' => $enabled]);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'street' => "{$name} 100", 'neighborhood' => $neighborhood]);

    return $customer;
}

/**
 * @return array<int, int|null> driver id of each customer, by customer id
 */
function transferDrivers(): array
{
    return Customer::query()->orderBy('id')->pluck('driver_id', 'id')->all();
}

test('the transfer page lists the enabled customers of the driver with their neighborhood, and the other enabled drivers', function (): void {
    $centro = transferCustomer($this->juan, 'Kiosco Centro', 'Centro');
    $norte = transferCustomer($this->juan, 'Almacén Norte', 'Norte');
    transferCustomer($this->juan, 'Cliente borrado', 'Centro', enabled: false);
    transferCustomer($this->carlos, 'De Carlos', 'Centro');
    Driver::factory()->create(['enabled' => false]);

    $this->actingAs($this->admin)
        ->get(route('customers.transfer', $this->juan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Customers/Transfer')
            ->where('driver', ['id' => $this->juan->id, 'name' => 'Juan'])
            ->has('customers', 2)
            ->where('customers.0', ['id' => $norte->id, 'name' => 'Almacén Norte', 'street' => 'Almacén Norte 100', 'neighborhood' => 'Norte'])
            ->where('customers.1', ['id' => $centro->id, 'name' => 'Kiosco Centro', 'street' => 'Kiosco Centro 100', 'neighborhood' => 'Centro'])
            ->where('drivers', [['id' => $this->ana->id, 'name' => 'Ana'], ['id' => $this->carlos->id, 'name' => 'Carlos']]));
});

test('transferring part of the portfolio moves only the selected customers and records one reassignment each', function (): void {
    $first = transferCustomer($this->juan, 'Uno', 'Centro');
    $second = transferCustomer($this->juan, 'Dos', 'Centro');
    $kept = transferCustomer($this->juan, 'Tres', 'Norte');

    $this->actingAs($this->admin)
        ->post(route('customers.transfer.store', $this->juan), ['to_driver_id' => $this->carlos->id, 'customer_ids' => [$first->id, $second->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.transfer', $this->juan))
        ->assertSessionHas('success', '2 clientes transferidos a Carlos.');

    expect(transferDrivers())->toBe([$first->id => $this->carlos->id, $second->id => $this->carlos->id, $kept->id => $this->juan->id])
        ->and(CustomerReassignment::query()->orderBy('customer_id')->get(['customer_id', 'from_driver_id', 'to_driver_id', 'reassigned_by'])->toArray())->toBe([
            ['customer_id' => $first->id, 'from_driver_id' => $this->juan->id, 'to_driver_id' => $this->carlos->id, 'reassigned_by' => $this->admin->id],
            ['customer_id' => $second->id, 'from_driver_id' => $this->juan->id, 'to_driver_id' => $this->carlos->id, 'reassigned_by' => $this->admin->id],
        ])
        ->and(CustomerReassignment::query()->pluck('reassigned_at')->map->toDateTimeString()->unique()->all())->toBe(['2026-11-11 10:00:00'])
        ->and($first->fresh()->last_modified_by)->toBe($this->admin->id);
});

test('the admin splits a portfolio among several drivers by repeating the transfer', function (): void {
    $first = transferCustomer($this->juan, 'Uno', 'Centro');
    $second = transferCustomer($this->juan, 'Dos', 'Norte');

    $this->actingAs($this->admin)->post(route('customers.transfer.store', $this->juan), ['to_driver_id' => $this->carlos->id, 'customer_ids' => [$first->id]])
        ->assertSessionHas('success', '1 cliente transferido a Carlos.');
    $this->actingAs($this->admin)->post(route('customers.transfer.store', $this->juan), ['to_driver_id' => $this->ana->id, 'customer_ids' => [$second->id]])
        ->assertSessionHasNoErrors();

    expect(transferDrivers())->toBe([$first->id => $this->carlos->id, $second->id => $this->ana->id])
        ->and(CustomerReassignment::query()->count())->toBe(2);

    $this->actingAs($this->admin)->get(route('customers.transfer', $this->juan))
        ->assertInertia(fn (Assert $page) => $page->has('customers', 0));
});

test('a rejected transfer changes no customer and records no reassignment', function (Closure $payload, string $field, string $message): void {
    $first = transferCustomer($this->juan, 'Uno', 'Centro');
    $second = transferCustomer($this->juan, 'Dos', 'Centro');
    $foreign = transferCustomer($this->carlos, 'Ajeno', 'Centro');
    $before = transferDrivers();

    $this->actingAs($this->admin)
        ->from(route('customers.transfer', $this->juan))
        ->post(route('customers.transfer.store', $this->juan), $payload($this, [$first, $second, $foreign]))
        ->assertRedirect(route('customers.transfer', $this->juan))
        ->assertSessionHasErrors([$field => $message])
        ->assertSessionMissing('success');

    expect(transferDrivers())->toBe($before)
        ->and(CustomerReassignment::query()->count())->toBe(0);
})->with([
    'destination is the source' => [fn ($t, $c) => ['to_driver_id' => $t->juan->id, 'customer_ids' => [$c[0]->id]], 'to_driver_id', 'Elegí un repartidor distinto del actual.'],
    'destination is disabled' => [fn ($t, $c) => ['to_driver_id' => Driver::factory()->create(['enabled' => false])->id, 'customer_ids' => [$c[0]->id]], 'to_driver_id', 'El repartidor de destino debe estar habilitado.'],
    'destination does not exist' => [fn ($t, $c) => ['to_driver_id' => 999999, 'customer_ids' => [$c[0]->id]], 'to_driver_id', 'El repartidor de destino debe estar habilitado.'],
    'no destination' => [fn ($t, $c) => ['customer_ids' => [$c[0]->id]], 'to_driver_id', 'Elegí el repartidor que recibe los clientes.'],
    'no customers' => [fn ($t, $c) => ['to_driver_id' => $t->carlos->id, 'customer_ids' => []], 'customer_ids', 'Elegí al menos un cliente.'],
    'a customer of another driver' => [fn ($t, $c) => ['to_driver_id' => $t->ana->id, 'customer_ids' => [$c[0]->id, $c[2]->id]], 'customer_ids', 'Algunos clientes elegidos ya no son de este repartidor.'],
    'a customer that does not exist' => [fn ($t, $c) => ['to_driver_id' => $t->ana->id, 'customer_ids' => [$c[0]->id, 999999]], 'customer_ids', 'Algunos clientes elegidos ya no son de este repartidor.'],
]);

test('a guest can neither see nor make a transfer', function (): void {
    $customer = transferCustomer($this->juan, 'Uno', 'Centro');

    $this->get(route('customers.transfer', $this->juan))->assertRedirect(route('login'));
    $this->post(route('customers.transfer.store', $this->juan), ['to_driver_id' => $this->carlos->id, 'customer_ids' => [$customer->id]])
        ->assertRedirect(route('login'));

    expect($customer->fresh()->driver_id)->toBe($this->juan->id)
        ->and(CustomerReassignment::query()->count())->toBe(0);
});
