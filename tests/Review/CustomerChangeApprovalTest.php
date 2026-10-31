<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Sync/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 12:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->proposer = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Marcos'])->id, 'enabled' => true]);
    $this->owner = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Esteban'])->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create([
        'driver_id' => $this->owner->id,
        'name' => 'Kiosco Ana',
        'created_at' => '2026-10-20 10:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
    $this->address = CustomerAddress::factory()->create([
        'customer_id' => $this->customer->id,
        'street' => 'Calle del servidor',
        'is_primary' => true,
        'created_at' => '2026-10-20 10:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
});

/**
 * Pushes one record as the proposing driver and answers the id of the inbox row it left.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $records
 */
function proposeAs(Driver $driver, array $records): int
{
    Sanctum::actingAs($driver->user, ['*']);
    push($records)->assertOk();

    return changeRows()[0]['id'];
}

/**
 * @return list<array<string, mixed>>
 */
function changeRows(string $status = 'pending'): array
{
    $page = null;
    test()->actingAs(test()->admin, 'web')->get("/review?type=customer_change&status={$status}")->assertOk()
        ->assertInertia(function (Assert $inertia) use (&$page): void {
            $page = $inertia->toArray()['props'];
        });

    return $page['items']['data'];
}

/**
 * @return array<string, mixed>
 */
function nameEdit(Customer $customer, string $name, string $modifiedAt = '2026-10-21T09:00:00-03:00'): array
{
    return ['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $customer->id,
        'name' => $name,
        'address' => ['street' => 'Nueva calle 123'],
        'modified_at' => $modifiedAt,
    ]]];
}

test('approving a pending customer edit applies it, closes it and the owner driver pulls it', function (): void {
    $id = proposeAs($this->proposer, nameEdit($this->customer, 'Kiosco Ana renombrado'));
    $this->travelTo('2026-10-22 12:30:00');

    $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$id}/approve")->assertRedirect();

    $this->customer->refresh();
    expect($this->customer->name)->toBe('Kiosco Ana renombrado')
        ->and($this->customer->primaryAddress->street)->toBe('Nueva calle 123')
        ->and($this->customer->driver_id)->toBe($this->owner->id)
        ->and(changeRows())->toBe([]);

    $closed = changeRows('reviewed');
    expect($closed)->toHaveCount(1)
        ->and($closed[0]['reviewed_by'])->toBe('Admin')
        ->and($closed[0]['change']['status'])->toBe('APPROVED');

    Sanctum::actingAs($this->owner->user, ['*']);
    $pull = $this->getJson('/api/v1/sync/pull?cursor='.urlencode('2026-10-22T12:00:00-03:00'))->assertOk();
    expect(collect($pull->json('data.customers'))->firstWhere('id', $this->customer->id)['name'])->toBe('Kiosco Ana renombrado')
        ->and(collect($pull->json('data.customer_addresses'))->firstWhere('id', $this->address->id)['street'])->toBe('Nueva calle 123');
});

test('an approved change is dated by the approval, so a newer web edit made before it does not swallow it and an older offline edit loses to it', function (): void {
    $id = proposeAs($this->proposer, nameEdit($this->customer, 'Nombre propuesto'));

    // The admin renames the customer after the driver's edit but before approving it.
    $this->travelTo('2026-10-22 12:10:00');
    $this->actingAs($this->admin, 'web')
        ->put(route('customers.update', $this->customer), ['name' => 'Nombre web', 'street' => 'Calle del servidor', 'repartidor_id' => $this->owner->id])
        ->assertSessionHasNoErrors();

    $this->travelTo('2026-10-22 12:30:00');
    $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$id}/approve")->assertRedirect();

    expect($this->customer->fresh()->name)->toBe('Nombre propuesto');

    // The owner's offline rename from 12:20 reaches the server after the approval and loses.
    Sanctum::actingAs($this->owner->user, ['*']);
    push(nameEdit($this->customer, 'Renombre del dueño', '2026-10-22T12:20:00-03:00'))
        ->assertOk()
        ->assertJsonPath('data.customers.0.status', 'outdated');

    expect($this->customer->fresh()->name)->toBe('Nombre propuesto');
});

test('approving pending address and preference edits applies them', function (): void {
    $preference = CustomerPreference::factory()->create([
        'customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '09:00:00', 'time_window_end' => '12:00:00',
    ]);
    $newAddressUuid = (string) Str::uuid();

    Sanctum::actingAs($this->proposer->user, ['*']);
    push([
        'customer_addresses' => [
            ['uuid' => (string) Str::uuid(), 'server_id' => $this->address->id, 'customer_id' => $this->customer->id, 'visual_reference' => 'Porton rojo', 'modified_at' => '2026-10-21T09:00:00-03:00'],
            ['uuid' => $newAddressUuid, 'customer_id' => $this->customer->id, 'street' => 'Caseros 1200', 'is_primary' => false, 'enabled' => true, 'modified_at' => '2026-10-21T09:00:00-03:00'],
        ],
        'customer_preferences' => [
            ['uuid' => (string) Str::uuid(), 'server_id' => $preference->id, 'customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '14:00', 'time_window_end' => '16:00', 'enabled' => true, 'modified_at' => '2026-10-21T09:00:00-03:00'],
        ],
    ])->assertOk();

    foreach (changeRows() as $row) {
        $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$row['id']}/approve")->assertRedirect()->assertSessionHasNoErrors();
    }

    expect($this->address->fresh()->visual_reference)->toBe('Porton rojo')
        ->and(CustomerAddress::query()->where('client_uuid', $newAddressUuid)->value('street'))->toBe('Caseros 1200')
        ->and($preference->fresh()->only(['time_window_start', 'time_window_end']))->toBe(['time_window_start' => '14:00:00', 'time_window_end' => '16:00:00'])
        ->and(changeRows())->toBe([]);
});

test('approving a pending disable disables the customer', function (): void {
    $id = proposeAs($this->proposer, ['customers' => [[
        'uuid' => (string) Str::uuid(), 'server_id' => $this->customer->id, 'enabled' => false, 'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]]);

    $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$id}/approve")->assertRedirect();

    expect($this->customer->fresh()->enabled)->toBeFalse();
});

test('a change that is no longer valid stays pending and the admin sees why', function (): void {
    $id = proposeAs($this->proposer, ['customer_preferences' => [[
        'uuid' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'day_of_week' => 4,
        'time_window_start' => '14:00', 'time_window_end' => '16:00', 'enabled' => true, 'modified_at' => '2026-10-21T09:00:00-03:00',
    ]]]);
    CustomerPreference::factory()->create([
        'customer_id' => $this->customer->id, 'day_of_week' => 4, 'time_window_start' => '15:00:00', 'time_window_end' => '17:00:00',
    ]);

    $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$id}/approve")
        ->assertRedirect()
        ->assertSessionHasErrors(['change' => 'Se superpone con 15:00–17:00.']);

    expect(changeRows())->toHaveCount(1)
        ->and($this->customer->preferences()->count())->toBe(1);
});

test('rejecting a pending change discards it without touching the customer', function (): void {
    $id = proposeAs($this->proposer, nameEdit($this->customer, 'Kiosco Ana renombrado'));
    $before = $this->customer->fresh()->only(['name', 'updated_at']);

    $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$id}/reject")->assertRedirect();

    expect($this->customer->fresh()->only(['name', 'updated_at']))->toEqual($before)
        ->and($this->customer->primaryAddress->street)->toBe('Calle del servidor')
        ->and(changeRows())->toBe([])
        ->and(changeRows('reviewed')[0]['change']['status'])->toBe('REJECTED');

    // A decided change cannot be decided again.
    $this->post("/review/customer-changes/{$id}/approve")->assertNotFound();
    expect($this->customer->fresh()->name)->toBe('Kiosco Ana');
});

test('only a super user can approve or reject a pending change, and it cannot be just marked as reviewed', function (): void {
    $id = proposeAs($this->proposer, nameEdit($this->customer, 'Kiosco Ana renombrado'));
    $this->actingAs(User::factory()->create(['is_super_user' => false]), 'web');

    $this->post("/review/customer-changes/{$id}/approve")->assertForbidden();
    $this->post("/review/customer-changes/{$id}/reject")->assertForbidden();

    $this->actingAs($this->admin, 'web');
    $this->patch("/review/customer_change/{$id}")->assertNotFound();

    expect($this->customer->fresh()->name)->toBe('Kiosco Ana')
        ->and(changeRows())->toHaveCount(1);
});

test('a held new address edited again before the decision stays one change with the latest fields and approves cleanly', function (): void {
    $uuid = (string) Str::uuid();
    $add = fn (array $fields, string $at): array => ['customer_addresses' => [[
        'uuid' => $uuid, 'customer_id' => $this->customer->id, 'is_primary' => false, 'enabled' => true, 'modified_at' => $at, ...$fields,
    ]]];
    Sanctum::actingAs($this->proposer->user, ['*']);
    push($add(['street' => 'Twice v1', 'floor' => '2'], '2026-10-21T09:00:00-03:00'))->assertOk();
    push($add(['street' => 'Twice v2'], '2026-10-21T09:05:00-03:00'))->assertOk();
    $this->travel(1)->hours();

    $rows = changeRows();
    expect($rows)->toHaveCount(1);

    $this->actingAs($this->admin, 'web')->post("/review/customer-changes/{$rows[0]['id']}/approve")->assertRedirect();

    $address = CustomerAddress::query()->where('client_uuid', $uuid)->sole();
    expect($address->street)->toBe('Twice v2')
        ->and($address->floor)->toBe('2');
});

test('an older resend of a held change does not override the newer one', function (): void {
    $uuid = (string) Str::uuid();
    $add = fn (string $street, string $at): array => ['customer_addresses' => [[
        'uuid' => $uuid, 'customer_id' => $this->customer->id, 'street' => $street, 'is_primary' => false, 'enabled' => true, 'modified_at' => $at,
    ]]];
    Sanctum::actingAs($this->proposer->user, ['*']);
    push($add('Newer', '2026-10-21T09:05:00-03:00'))->assertOk();
    push($add('Older', '2026-10-21T09:00:00-03:00'))->assertOk();

    expect(changeRows())->toHaveCount(1);
    $this->travel(1)->hours();
    $this->actingAs($this->admin, 'web')->post('/review/customer-changes/'.changeRows()[0]['id'].'/approve')->assertRedirect();

    expect(CustomerAddress::query()->where('client_uuid', $uuid)->sole()->street)->toBe('Newer');
});
