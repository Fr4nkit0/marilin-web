<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
});

test('the settings page lists the neighborhoods by name, disabled ones included, paginated', function (): void {
    Neighborhood::factory()->create(['name' => 'Tres Cerritos']);
    $off = Neighborhood::factory()->create(['name' => 'Ciudad del Milagro', 'enabled' => false]);
    Neighborhood::factory()->count(30)->sequence(fn ($sequence) => ['name' => 'Zona '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();

    $this->actingAs($this->admin)
        ->get(route('neighborhoods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Neighborhoods/Index')
            ->where('neighborhoods.total', 32)
            ->where('neighborhoods.current_page', 1)
            ->has('neighborhoods.data', 25)
            ->where('neighborhoods.data.0', ['id' => $off->id, 'name' => 'Ciudad del Milagro', 'enabled' => false])
            ->where('neighborhoods.data.1.name', 'Tres Cerritos')
            ->where('filters', ['search' => '']));
});

test('the search finds neighborhoods by name without minding case or accents', function (): void {
    $match = Neighborhood::factory()->create(['name' => 'Villa Mónica']);
    Neighborhood::factory()->create(['name' => 'Centro']);

    $this->actingAs($this->admin)
        ->get(route('neighborhoods.index', ['search' => ' monica ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('neighborhoods.data', 1)
            ->where('neighborhoods.data.0.id', $match->id)
            ->where('filters.search', 'monica'));
});

test('the admin creates a neighborhood with its name trimmed, enabled', function (): void {
    $this->actingAs($this->admin)
        ->from(route('neighborhoods.index'))
        ->post(route('neighborhoods.store'), ['name' => '  Barrio Nuevo  '])
        ->assertRedirect(route('neighborhoods.index'))
        ->assertSessionHas('success', 'Barrio creado');

    $created = Neighborhood::query()->sole();

    expect($created->name)->toBe('Barrio Nuevo')
        ->and($created->enabled)->toBeTrue();
});

test('the admin renames a neighborhood', function (): void {
    $neighborhood = Neighborhood::factory()->create(['name' => 'Tres Cerito']);

    $this->actingAs($this->admin)
        ->from(route('neighborhoods.index'))
        ->patch(route('neighborhoods.update', $neighborhood), ['name' => ' Tres Cerritos '])
        ->assertRedirect(route('neighborhoods.index'))
        ->assertSessionHas('success', 'Barrio actualizado');

    expect($neighborhood->fresh()->name)->toBe('Tres Cerritos');
});

test('the admin disables a neighborhood and enables it again, never deleting it', function (): void {
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centro']);

    $this->actingAs($this->admin)
        ->from(route('neighborhoods.index'))
        ->patch(route('neighborhoods.status', $neighborhood), ['enabled' => false])
        ->assertRedirect(route('neighborhoods.index'))
        ->assertSessionHas('success', 'Barrio deshabilitado');

    expect($neighborhood->fresh()->enabled)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('neighborhoods.status', $neighborhood), ['enabled' => true])
        ->assertSessionHas('success', 'Barrio habilitado');

    expect($neighborhood->fresh()->enabled)->toBeTrue()
        ->and(Neighborhood::query()->count())->toBe(1);
});

test('renaming a neighborhood renames it in the addresses that carry the old name, and only in those', function (): void {
    $this->travelTo('2026-11-01 10:00:00');
    $neighborhood = Neighborhood::factory()->create(['name' => 'Tres Cerito']);
    $matching = CustomerAddress::factory()->create(['neighborhood' => 'Tres Cerito']);
    $otherCase = CustomerAddress::factory()->create(['neighborhood' => 'tres cerito']);
    $other = CustomerAddress::factory()->create(['neighborhood' => 'Centro']);

    $this->travelTo('2026-11-02 09:30:00');
    $this->actingAs($this->admin)
        ->patch(route('neighborhoods.update', $neighborhood), ['name' => 'Tres Cerritos'])
        ->assertSessionHasNoErrors();

    $matching->refresh();

    expect($matching->neighborhood)->toBe('Tres Cerritos')
        ->and($matching->updated_at->equalTo(Carbon::parse('2026-11-02 09:30:00')))->toBeTrue()
        // The rename is a web edit: an older offline edit of the field from the app no longer wins.
        ->and(Carbon::parse($matching->field_modified_at['neighborhood'])->equalTo(Carbon::parse('2026-11-02 09:30:00')))->toBeTrue()
        ->and($otherCase->fresh()->neighborhood)->toBe('tres cerito')
        ->and($otherCase->fresh()->updated_at->equalTo(Carbon::parse('2026-11-01 10:00:00')))->toBeTrue()
        ->and($other->fresh()->neighborhood)->toBe('Centro')
        ->and($other->fresh()->updated_at->equalTo(Carbon::parse('2026-11-01 10:00:00')))->toBeTrue();
});

test('the next pull sends the renamed addresses with the new name and the neighborhood', function (): void {
    $this->travelTo('2026-11-01 10:00:00');
    $driverUser = User::factory()->create(['enabled' => true]);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'enabled' => true]);
    $customer = Customer::factory()->create(['driver_id' => $driver->id]);
    $renamed = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'neighborhood' => 'Tres Cerito']);
    $untouched = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'neighborhood' => 'Centro']);
    $neighborhood = Neighborhood::factory()->create(['name' => 'Tres Cerito']);

    $this->travelTo('2026-11-02 09:00:00');
    $cursor = now()->toIso8601String();

    $this->travelTo('2026-11-02 09:30:00');
    $this->actingAs($this->admin)->patch(route('neighborhoods.update', $neighborhood), ['name' => 'Tres Cerritos']);

    $this->travelTo('2026-11-02 09:40:00');
    Sanctum::actingAs($driverUser, ['*']);
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();
    $addresses = collect($response->json('data.customer_addresses'))->keyBy('id');

    expect($addresses->keys()->all())->toBe([$renamed->id])
        ->and($addresses[$renamed->id]['neighborhood'])->toBe('Tres Cerritos')
        ->and($addresses->keys())->not->toContain($untouched->id)
        ->and(collect($response->json('data.neighborhoods'))->pluck('name')->all())->toBe(['Tres Cerritos']);
});

test('a disabled neighborhood keeps reaching the app, marked disabled', function (): void {
    $this->travelTo('2026-11-01 10:00:00');
    $driverUser = User::factory()->create(['enabled' => true]);
    Driver::factory()->create(['user_id' => $driverUser->id, 'enabled' => true]);
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centro']);
    $cursor = now()->addMinute()->toIso8601String();

    $this->travelTo('2026-11-01 10:30:00');
    $this->actingAs($this->admin)->patch(route('neighborhoods.status', $neighborhood), ['enabled' => false]);

    Sanctum::actingAs($driverUser, ['*']);
    $rows = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.neighborhoods');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($neighborhood->id)
        ->and($rows[0]['enabled'])->toBeFalse();
});

test('a name taken by another neighborhood in any case is rejected and nothing changes', function (): void {
    Neighborhood::factory()->create(['name' => 'Centro']);
    $renaming = Neighborhood::factory()->create(['name' => 'Limache']);
    $address = CustomerAddress::factory()->create(['neighborhood' => 'Limache']);

    $this->actingAs($this->admin)
        ->post(route('neighborhoods.store'), ['name' => ' CENTRO '])
        ->assertSessionHasErrors(['name' => 'Ya hay un barrio con ese nombre.']);

    $this->actingAs($this->admin)
        ->patch(route('neighborhoods.update', $renaming), ['name' => 'centro'])
        ->assertSessionHasErrors(['name' => 'Ya hay un barrio con ese nombre.']);

    expect(Neighborhood::query()->count())->toBe(2)
        ->and($renaming->fresh()->name)->toBe('Limache')
        ->and($address->fresh()->neighborhood)->toBe('Limache');
});

test('a neighborhood may keep its own name in another case', function (): void {
    $neighborhood = Neighborhood::factory()->create(['name' => 'tres cerritos']);

    $this->actingAs($this->admin)
        ->patch(route('neighborhoods.update', $neighborhood), ['name' => 'Tres Cerritos'])
        ->assertSessionHasNoErrors();

    expect($neighborhood->fresh()->name)->toBe('Tres Cerritos');
});

test('an empty or too long name is rejected and nothing changes', function (): void {
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centro']);

    $this->actingAs($this->admin)
        ->post(route('neighborhoods.store'), ['name' => '   '])
        ->assertSessionHasErrors(['name' => 'Ingresá el nombre del barrio.']);

    $this->actingAs($this->admin)
        ->patch(route('neighborhoods.update', $neighborhood), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'Ingresá el nombre del barrio.']);

    $this->actingAs($this->admin)
        ->post(route('neighborhoods.store'), ['name' => str_repeat('a', 256)])
        ->assertSessionHasErrors(['name' => 'El nombre puede tener hasta 255 caracteres.']);

    expect(Neighborhood::query()->count())->toBe(1)
        ->and($neighborhood->fresh()->name)->toBe('Centro');
});

test('the status needs a yes or no', function (): void {
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centro']);

    $this->actingAs($this->admin)
        ->patch(route('neighborhoods.status', $neighborhood), [])
        ->assertSessionHasErrors('enabled');

    expect($neighborhood->fresh()->enabled)->toBeTrue();
});

test('a user without manage-settings gets 403 on every neighborhood endpoint and changes nothing', function (): void {
    $driver = User::factory()->create(['is_super_user' => false]);
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centro']);

    $this->actingAs($driver)->get(route('neighborhoods.index'))->assertForbidden();
    $this->actingAs($driver)->post(route('neighborhoods.store'), ['name' => 'Nuevo'])->assertForbidden();
    $this->actingAs($driver)->patch(route('neighborhoods.update', $neighborhood), ['name' => 'Otro'])->assertForbidden();
    $this->actingAs($driver)->patch(route('neighborhoods.status', $neighborhood), ['enabled' => false])->assertForbidden();

    $neighborhood->refresh();

    expect(Neighborhood::query()->count())->toBe(1)
        ->and($neighborhood->name)->toBe('Centro')
        ->and($neighborhood->enabled)->toBeTrue();
});
