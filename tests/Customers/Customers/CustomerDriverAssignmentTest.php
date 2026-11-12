<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\DriverAssignment;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerReassignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-11-11 10:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->juan = Driver::factory()->create();
    $this->carlos = Driver::factory()->create();
    $this->off = Driver::factory()->create(['enabled' => false]);
});

test('a transfer to a driver disabled meanwhile moves no customer and records nothing', function (): void {
    $customers = Customer::factory()->count(2)->create(['driver_id' => $this->juan->id]);

    expect(fn () => app(DriverAssignment::class)->transfer($customers->modelKeys(), $this->juan->id, $this->off->id, $this->admin))
        ->toThrow(InvalidCustomerChange::class, 'The destination driver is not enabled.');

    expect(Customer::query()->whereKey($customers->modelKeys())->pluck('driver_id')->unique()->all())->toBe([$this->juan->id])
        ->and(CustomerReassignment::query()->count())->toBe(0);
});

test('editing a customer to another driver still records exactly one reassignment', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->juan->id, 'name' => 'Kiosco Ana']);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true]);

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), ['name' => 'Kiosco Ana Bis', 'street' => 'Mitre 50', 'repartidor_id' => $this->carlos->id])
        ->assertSessionHasNoErrors();

    $customer->refresh();
    expect($customer->driver_id)->toBe($this->carlos->id)
        ->and($customer->name)->toBe('Kiosco Ana Bis')
        ->and($customer->field_modified_at)->toHaveKey('name')
        ->and(CustomerReassignment::query()->get(['customer_id', 'from_driver_id', 'to_driver_id', 'reassigned_by'])->toArray())->toBe([
            ['customer_id' => $customer->id, 'from_driver_id' => $this->juan->id, 'to_driver_id' => $this->carlos->id, 'reassigned_by' => $this->admin->id],
        ]);
});

test('editing a customer without changing its driver records no reassignment', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->juan->id]);

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), ['name' => 'Otro', 'street' => 'Mitre 50', 'repartidor_id' => $this->juan->id])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->driver_id)->toBe($this->juan->id)
        ->and(CustomerReassignment::query()->count())->toBe(0);
});

test('a customer is created or edited only with an enabled driver', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->juan->id, 'name' => 'Antes']);

    $this->actingAs($this->admin)
        ->post(route('customers.store'), ['name' => 'Kiosco Nuevo', 'street' => 'Mitre 50', 'repartidor_id' => $this->off->id])
        ->assertSessionHasErrors(['repartidor_id' => 'El repartidor debe estar habilitado.']);
    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), ['name' => 'Después', 'street' => 'Mitre 50', 'repartidor_id' => $this->off->id])
        ->assertSessionHasErrors(['repartidor_id' => 'El repartidor debe estar habilitado.']);

    expect(Customer::query()->where('name', 'Kiosco Nuevo')->exists())->toBeFalse()
        ->and($customer->fresh()->only('driver_id', 'name'))->toBe(['driver_id' => $this->juan->id, 'name' => 'Antes'])
        ->and(CustomerReassignment::query()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->post(route('customers.store'), ['name' => 'Kiosco Nuevo', 'street' => 'Mitre 50', 'repartidor_id' => $this->carlos->id])
        ->assertSessionHasNoErrors();
    expect(Customer::query()->where('name', 'Kiosco Nuevo')->sole()->driver_id)->toBe($this->carlos->id);
});

test('the customers list filters the customers without a driver', function (): void {
    $alone = Customer::factory()->create(['driver_id' => null, 'name' => 'Sin nadie']);
    Customer::factory()->create(['driver_id' => $this->juan->id, 'name' => 'De Juan']);

    $this->actingAs($this->admin)
        ->get(route('customers.index', ['repartidor_id' => 'none']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.repartidor_id', 'none')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $alone->id));
});

test('the customers list tells which drivers are enabled, so the forms offer only those', function (): void {
    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('repartidores', 3)
            ->where('repartidores', fn ($drivers) => collect($drivers)->sortBy('id')->pluck('enabled', 'id')->all() === [
                $this->juan->id => true, $this->carlos->id => true, $this->off->id => false,
            ]));
});
