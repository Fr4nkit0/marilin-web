<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Enums\ContainerMovementType;
use App\Models\ContainerMovement;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAddress;
use App\Models\CustomerBalance;
use App\Models\CustomerContainerBalance;
use App\Operations\CustomerAddressDetails;
use App\Operations\CustomerIntake;
use App\Operations\CustomerRegistration;
use App\Operations\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['enabled' => true]);
    $this->bidon = ContainerType::factory()->create(['code' => 'BIDON_20L']);
    $this->sifon = ContainerType::factory()->create(['code' => 'SIFON_2L']);
});

function webCustomer(array $overrides = []): array
{
    return ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', ...$overrides];
}

test('the initial debt is an adjustment entry through the ledger', function (): void {
    $this->actingAs($this->user)
        ->post(route('customers.store'), webCustomer(['initial_balance' => '1500.50']))
        ->assertRedirect(route('customers.index'));

    $customer = Customer::query()->where('name', 'Kiosco Ana')->firstOrFail();
    $entry = CustomerAccountEntry::query()->where('customer_id', $customer->id)->sole();
    $balance = CustomerBalance::query()->where('customer_id', $customer->id)->sole();

    expect($entry->entry_type)->toBe('ADJUSTMENT')
        ->and($entry->direction)->toBe(1)
        ->and((float) $entry->amount)->toBe(1500.5)
        ->and($entry->description)->toBe('Saldo deudor inicial registrado al dar de alta')
        ->and((float) $balance->current_balance)->toBe(1500.5)
        ->and($balance->last_entry_id)->toBe($entry->id);
});

test('a customer without initial debt still gets a balance at zero and no entry', function (): void {
    $this->actingAs($this->user)->post(route('customers.store'), webCustomer());

    $customer = Customer::query()->where('name', 'Kiosco Ana')->firstOrFail();

    expect((float) CustomerBalance::query()->where('customer_id', $customer->id)->sole()->current_balance)->toBe(0.0)
        ->and(CustomerAccountEntry::query()->count())->toBe(0)
        ->and(ContainerMovement::query()->count())->toBe(0)
        ->and(CustomerContainerBalance::query()->count())->toBe(0);
});

test('the initial containers are adjustment movements and fill the balance', function (): void {
    $this->actingAs($this->user)->post(route('customers.store'), webCustomer(['bidon_20l_balance' => '3', 'sifon_2l_balance' => 2]));

    $customer = Customer::query()->where('name', 'Kiosco Ana')->firstOrFail();
    $movements = ContainerMovement::query()->where('customer_id', $customer->id)->get()->keyBy('container_type_id');

    expect($movements)->toHaveCount(2)
        ->and($movements[$this->bidon->id]->movement_type)->toBe(ContainerMovementType::Adjustment->value)
        ->and($movements[$this->bidon->id]->direction)->toBe(1)
        ->and($movements[$this->bidon->id]->quantity)->toBe(3)
        ->and($movements[$this->sifon->id]->quantity)->toBe(2);

    $balances = CustomerContainerBalance::query()->where('customer_id', $customer->id)->get()->keyBy('container_type_id');
    expect($balances[$this->bidon->id]->balance)->toBe(3)
        ->and($balances[$this->bidon->id]->last_movement_id)->toBe($movements[$this->bidon->id]->id)
        ->and($balances[$this->sifon->id]->balance)->toBe(2);
});

test('zero initial containers write nothing', function (): void {
    $this->actingAs($this->user)->post(route('customers.store'), webCustomer(['bidon_20l_balance' => 0, 'sifon_2l_balance' => 0]));

    expect(ContainerMovement::query()->count())->toBe(0)
        ->and(CustomerContainerBalance::query()->count())->toBe(0);
});

test('invalid store input leaves every table unchanged', function (): void {
    $this->actingAs($this->user)
        ->post(route('customers.store'), webCustomer(['initial_balance' => '-5', 'bidon_20l_balance' => 2]))
        ->assertSessionHasErrors('initial_balance');

    expect(Customer::query()->count())->toBe(0)
        ->and(CustomerAddress::query()->count())->toBe(0)
        ->and(CustomerAccountEntry::query()->count())->toBe(0)
        ->and(CustomerBalance::query()->count())->toBe(0)
        ->and(ContainerMovement::query()->count())->toBe(0)
        ->and(CustomerContainerBalance::query()->count())->toBe(0);
});

test('the module registers a customer without http', function (): void {
    $customer = app(CustomerIntake::class)->register(new CustomerRegistration(
        name: 'Almacén Sur',
        phoneNumber: '1122334455',
        driverId: null,
        address: new CustomerAddressDetails(street: 'Sarmiento 10'),
        createdBy: $this->user,
        initialDebt: '200.00',
        initialContainers: ['BIDON_20L' => 4],
    ));

    expect($customer->primaryAddress->street)->toBe('Sarmiento 10')
        ->and($customer->enabled)->toBeTrue()
        ->and((float) $customer->balance->current_balance)->toBe(200.0)
        ->and(CustomerContainerBalance::query()->where('customer_id', $customer->id)->sole()->balance)->toBe(4);
});

test('editing from the web keeps the address location the app sent', function (): void {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'neighborhood' => 'Centro', 'latitude' => -34.5, 'longitude' => -58.4]);

    $this->actingAs($this->user)
        ->put(route('customers.update', $customer), webCustomer(['name' => 'Nuevo']))
        ->assertSessionHasNoErrors();

    $address = $customer->fresh()->primaryAddress;
    expect($customer->fresh()->name)->toBe('Nuevo')
        ->and($address->street)->toBe('Mitre 50')
        ->and($address->neighborhood)->toBe('Centro')
        ->and((float) $address->latitude)->toBe(-34.5)
        ->and($address->last_modified_by)->toBe($this->user->id);
});

test('editing a customer without address from the web creates the primary address', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($this->user)->put(route('customers.update', $customer), webCustomer());

    expect($customer->fresh()->primaryAddress->street)->toBe('Mitre 50');
});

test('an initial debt in scientific notation is booked like any other numeric amount', function (): void {
    $this->actingAs($this->user)
        ->post(route('customers.store'), webCustomer(['initial_balance' => '1e2']))
        ->assertRedirect(route('customers.index'));

    expect((float) CustomerBalance::query()->sole()->current_balance)->toBe(100.0)
        ->and((float) CustomerAccountEntry::query()->sole()->amount)->toBe(100.0);
});

test('a failing initial container movement rolls back the customer, its address, entry and balances', function (): void {
    $this->mock(StreetOperation::class, fn ($mock) => $mock->shouldReceive('moveContainers')->andThrow(new RuntimeException('boom')));
    $this->actingAs($this->user)->withoutExceptionHandling();

    expect(fn () => $this->post(route('customers.store'), webCustomer(['initial_balance' => '100', 'bidon_20l_balance' => 2])))
        ->toThrow(RuntimeException::class, 'boom');

    expect([Customer::count(), CustomerAddress::count(), CustomerAccountEntry::count(), CustomerBalance::count(), ContainerMovement::count(), CustomerContainerBalance::count()])
        ->toBe([0, 0, 0, 0, 0, 0]);
});
