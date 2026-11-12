<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerReassignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-11-11 10:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->juan = User::factory()->create(['is_super_user' => false, 'enabled' => true, 'name' => 'Juan']);
    $this->juanDriver = Driver::factory()->create(['user_id' => $this->juan->id]);
    $this->carlos = User::factory()->create(['is_super_user' => false, 'enabled' => true, 'name' => 'Carlos']);
    $this->carlosDriver = Driver::factory()->create(['user_id' => $this->carlos->id]);
});

test('the users page tells how many customers each driver has', function (): void {
    Customer::factory()->count(3)->create(['driver_id' => $this->juanDriver->id]);
    Customer::factory()->create(['driver_id' => $this->juanDriver->id, 'enabled' => false]);

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.0.id', $this->carlos->id)
            ->where('users.0.customers_count', 0)
            ->where('users.1.id', $this->juan->id)
            ->where('users.1.customers_count', 4));
});

test('disabling a driver leaves every customer of theirs without a driver and records the reassignment to none', function (): void {
    $customers = Customer::factory()->count(2)->create(['driver_id' => $this->juanDriver->id]);
    $removed = Customer::factory()->create(['driver_id' => $this->juanDriver->id, 'enabled' => false]);
    $other = Customer::factory()->create(['driver_id' => $this->carlosDriver->id]);

    $this->actingAs($this->admin)
        ->patch(route('users.status', $this->juan), ['enabled' => false])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Cuenta deshabilitada');

    expect($this->juanDriver->fresh()->enabled)->toBeFalse()
        ->and(Customer::query()->whereIn('id', [...$customers->modelKeys(), $removed->id])->pluck('driver_id')->unique()->all())->toBe([null])
        ->and($other->fresh()->driver_id)->toBe($this->carlosDriver->id)
        ->and(CustomerReassignment::query()->orderBy('customer_id')->get(['customer_id', 'from_driver_id', 'to_driver_id', 'reassigned_by'])->toArray())->toBe([
            ['customer_id' => $customers[0]->id, 'from_driver_id' => $this->juanDriver->id, 'to_driver_id' => null, 'reassigned_by' => $this->admin->id],
            ['customer_id' => $customers[1]->id, 'from_driver_id' => $this->juanDriver->id, 'to_driver_id' => null, 'reassigned_by' => $this->admin->id],
            ['customer_id' => $removed->id, 'from_driver_id' => $this->juanDriver->id, 'to_driver_id' => null, 'reassigned_by' => $this->admin->id],
        ]);
});

test('enabling a driver again does not give them back any customer', function (): void {
    $this->juan->update(['enabled' => false]);
    $this->juanDriver->update(['enabled' => false]);
    $customer = Customer::factory()->create(['driver_id' => null]);

    $this->actingAs($this->admin)->patch(route('users.status', $this->juan), ['enabled' => true])
        ->assertSessionHas('success', 'Cuenta habilitada');

    expect($this->juanDriver->fresh()->enabled)->toBeTrue()
        ->and($customer->fresh()->driver_id)->toBeNull()
        ->and(CustomerReassignment::query()->count())->toBe(0);
});

test('a rejected status change unassigns nobody', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->juanDriver->id]);

    $this->actingAs($this->admin)->patch(route('users.status', $this->juan), ['enabled' => 'quizás'])
        ->assertSessionHasErrors('enabled');

    expect($this->juanDriver->fresh()->enabled)->toBeTrue()
        ->and($customer->fresh()->driver_id)->toBe($this->juanDriver->id)
        ->and(CustomerReassignment::query()->count())->toBe(0);
});
