<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
});

test('the settings page lists every container type, enabled first, then by description', function (): void {
    $sifon = ContainerType::factory()->sifon2L()->create();
    $off = ContainerType::factory()->disabled()->create(['code' => 'BOTELLON', 'description' => 'Botellón']);
    $bidon = ContainerType::factory()->bidon20L()->create();

    $this->actingAs($this->admin)
        ->get(route('container-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/ContainerTypes')
            ->has('containerTypes', 3)
            ->where('containerTypes.0', ['id' => $bidon->id, 'code' => 'BIDON_20L', 'description' => 'Bidón 20 Litros Retornable', 'enabled' => true])
            ->where('containerTypes.1.id', $sifon->id)
            ->where('containerTypes.2', ['id' => $off->id, 'code' => 'BOTELLON', 'description' => 'Botellón', 'enabled' => false]));
});

test('the admin creates a container type whose code comes from the description, without accents', function (): void {
    $this->actingAs($this->admin)
        ->from(route('container-types.index'))
        ->post(route('container-types.store'), ['description' => '  Bidón 12L  ', 'code' => 'MINE'])
        ->assertRedirect(route('container-types.index'))
        ->assertSessionHas('success', 'Tipo de envase creado');

    $created = ContainerType::query()->sole();

    expect($created->description)->toBe('Bidón 12L')
        ->and($created->code)->toBe('BIDON_12L')
        ->and($created->enabled)->toBeTrue();
});

test('a generated code already taken gets a numeric suffix', function (): void {
    ContainerType::factory()->create(['code' => 'SIFON_1_5L', 'description' => 'Sifón viejo']);
    ContainerType::factory()->create(['code' => 'SIFON_1_5L_2', 'description' => 'Sifón otro']);

    $this->actingAs($this->admin)
        ->post(route('container-types.store'), ['description' => 'Sifón 1,5L'])
        ->assertSessionHasNoErrors();

    expect(ContainerType::query()->where('description', 'Sifón 1,5L')->sole()->code)->toBe('SIFON_1_5L_3');
});

test('a generated code keeps within 50 characters, its suffix included', function (): void {
    $description = str_repeat('Envase ', 20);
    $base = substr(str_repeat('ENVASE_', 20), 0, 50);
    ContainerType::factory()->create(['code' => rtrim($base, '_'), 'description' => 'Otro']);

    $this->actingAs($this->admin)
        ->post(route('container-types.store'), ['description' => $description])
        ->assertSessionHasNoErrors();

    $code = ContainerType::query()->where('description', trim($description))->sole()->code;

    expect(strlen($code))->toBeLessThanOrEqual(50)
        ->and($code)->toEndWith('_2')
        ->and($code)->toStartWith('ENVASE_ENVASE_');
});

test('the admin edits the description of a container type, never its code', function (): void {
    $type = ContainerType::factory()->bidon20L()->create();

    $this->actingAs($this->admin)
        ->from(route('container-types.index'))
        ->patch(route('container-types.update', $type), ['description' => 'Bidón 20 litros', 'code' => 'OTHER'])
        ->assertRedirect(route('container-types.index'))
        ->assertSessionHas('success', 'Tipo de envase actualizado');

    $type->refresh();

    expect($type->description)->toBe('Bidón 20 litros')
        ->and($type->code)->toBe('BIDON_20L');
});

test('the admin disables a container type and enables it again, never deleting it', function (): void {
    $type = ContainerType::factory()->bidon20L()->create();

    $this->actingAs($this->admin)
        ->from(route('container-types.index'))
        ->patch(route('container-types.status', $type), ['enabled' => false])
        ->assertRedirect(route('container-types.index'))
        ->assertSessionHas('success', 'Tipo de envase deshabilitado');

    expect($type->fresh()->enabled)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('container-types.status', $type), ['enabled' => true])
        ->assertSessionHas('success', 'Tipo de envase habilitado');

    expect($type->fresh()->enabled)->toBeTrue()
        ->and(ContainerType::query()->count())->toBe(1);
});

test('a disabled container type keeps reaching the app, marked disabled', function (): void {
    $this->travelTo('2026-11-01 10:00:00');
    $driverUser = User::factory()->create(['enabled' => true]);
    Driver::factory()->create(['user_id' => $driverUser->id, 'enabled' => true]);
    $type = ContainerType::factory()->bidon20L()->create();
    $cursor = now()->addMinute()->toIso8601String();

    $this->travelTo('2026-11-01 10:30:00');
    $this->actingAs($this->admin)->patch(route('container-types.status', $type), ['enabled' => false]);

    Sanctum::actingAs($driverUser, ['*']);
    $rows = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.container_types');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($type->id)
        ->and($rows[0]['enabled'])->toBeFalse();
});

test('a description taken by another container type in another case or accent is rejected and nothing changes', function (): void {
    ContainerType::factory()->create(['code' => 'BIDON_12L', 'description' => 'Bidón 12L']);
    $editing = ContainerType::factory()->sifon2L()->create();

    $this->actingAs($this->admin)
        ->post(route('container-types.store'), ['description' => ' BIDON 12l '])
        ->assertSessionHasErrors(['description' => 'Ya hay un tipo de envase con esa descripción.']);

    $this->actingAs($this->admin)
        ->patch(route('container-types.update', $editing), ['description' => 'bidon 12L'])
        ->assertSessionHasErrors(['description' => 'Ya hay un tipo de envase con esa descripción.']);

    expect(ContainerType::query()->count())->toBe(2)
        ->and($editing->fresh()->description)->toBe('Sifón Soda 2 Litros Retornable');
});

test('a container type may keep its own description in another case', function (): void {
    $type = ContainerType::factory()->create(['code' => 'BIDON_12L', 'description' => 'bidón 12l']);

    $this->actingAs($this->admin)
        ->patch(route('container-types.update', $type), ['description' => 'Bidón 12L'])
        ->assertSessionHasNoErrors();

    expect($type->fresh()->description)->toBe('Bidón 12L');
});

test('an empty or too long description is rejected and nothing changes', function (): void {
    $type = ContainerType::factory()->bidon20L()->create();

    $this->actingAs($this->admin)
        ->post(route('container-types.store'), ['description' => '   '])
        ->assertSessionHasErrors(['description' => 'Ingresá la descripción del envase.']);

    $this->actingAs($this->admin)
        ->patch(route('container-types.update', $type), ['description' => ''])
        ->assertSessionHasErrors(['description' => 'Ingresá la descripción del envase.']);

    $this->actingAs($this->admin)
        ->post(route('container-types.store'), ['description' => str_repeat('a', 161)])
        ->assertSessionHasErrors(['description' => 'La descripción puede tener hasta 160 caracteres.']);

    expect(ContainerType::query()->count())->toBe(1)
        ->and($type->fresh()->description)->toBe('Bidón 20 Litros Retornable');
});

test('the status needs a yes or no', function (): void {
    $type = ContainerType::factory()->bidon20L()->create();

    $this->actingAs($this->admin)
        ->patch(route('container-types.status', $type), [])
        ->assertSessionHasErrors(['enabled' => 'Indicá si el tipo de envase queda habilitado.']);

    expect($type->fresh()->enabled)->toBeTrue();
});

test('a user without manage-settings gets 403 on every container type endpoint and changes nothing', function (): void {
    $driver = User::factory()->create(['is_super_user' => false]);
    $type = ContainerType::factory()->bidon20L()->create();

    $this->actingAs($driver)->get(route('container-types.index'))->assertForbidden();
    $this->actingAs($driver)->post(route('container-types.store'), ['description' => 'Nuevo'])->assertForbidden();
    $this->actingAs($driver)->patch(route('container-types.update', $type), ['description' => 'Otro'])->assertForbidden();
    $this->actingAs($driver)->patch(route('container-types.status', $type), ['enabled' => false])->assertForbidden();

    $type->refresh();

    expect(ContainerType::query()->count())->toBe(1)
        ->and($type->description)->toBe('Bidón 20 Litros Retornable')
        ->and($type->enabled)->toBeTrue();
});
