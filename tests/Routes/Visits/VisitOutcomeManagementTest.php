<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Routes\Visits\Models\VisitOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
});

test('the settings page lists every visit outcome, enabled first, then by name', function (): void {
    $absent = VisitOutcome::factory()->triggersReschedule()->create(['code' => 'ABSENT', 'name' => 'Ausente']);
    $off = VisitOutcome::factory()->create(['code' => null, 'name' => 'Cerrado', 'enabled' => false]);
    $delivered = VisitOutcome::factory()->create(['code' => 'DELIVERED', 'name' => 'Entregado']);

    $this->actingAs($this->admin)
        ->get(route('visit-outcomes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Routes/Visits/Outcomes')
            ->has('outcomes', 3)
            ->where('outcomes.0', ['id' => $absent->id, 'code' => 'ABSENT', 'name' => 'Ausente', 'triggers_reschedule' => true, 'enabled' => true])
            ->where('outcomes.1.id', $delivered->id)
            ->where('outcomes.2', ['id' => $off->id, 'code' => null, 'name' => 'Cerrado', 'triggers_reschedule' => false, 'enabled' => false]));
});

test('the admin creates a visit outcome with its name trimmed, enabled and without a code', function (): void {
    $this->actingAs($this->admin)
        ->from(route('visit-outcomes.index'))
        ->post(route('visit-outcomes.store'), ['name' => '  Portón cerrado  ', 'triggers_reschedule' => true, 'code' => 'GATE'])
        ->assertRedirect(route('visit-outcomes.index'))
        ->assertSessionHas('success', 'Resultado de visita creado');

    $created = VisitOutcome::query()->sole();

    expect($created->name)->toBe('Portón cerrado')
        ->and($created->triggers_reschedule)->toBeTrue()
        ->and($created->enabled)->toBeTrue()
        ->and($created->code)->toBeNull();
});

test('the admin edits the name and the reschedule of an outcome, never its code', function (): void {
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente', 'triggers_reschedule' => false]);

    $this->actingAs($this->admin)
        ->from(route('visit-outcomes.index'))
        ->patch(route('visit-outcomes.update', $outcome), ['name' => 'No estaba', 'triggers_reschedule' => true, 'code' => 'OTHER'])
        ->assertRedirect(route('visit-outcomes.index'))
        ->assertSessionHas('success', 'Resultado de visita actualizado');

    $outcome->refresh();

    expect($outcome->name)->toBe('No estaba')
        ->and($outcome->triggers_reschedule)->toBeTrue()
        ->and($outcome->code)->toBe('ABSENT');
});

test('the admin disables a visit outcome and enables it again, never deleting it', function (): void {
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente']);

    $this->actingAs($this->admin)
        ->from(route('visit-outcomes.index'))
        ->patch(route('visit-outcomes.status', $outcome), ['enabled' => false])
        ->assertRedirect(route('visit-outcomes.index'))
        ->assertSessionHas('success', 'Resultado de visita deshabilitado');

    expect($outcome->fresh()->enabled)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('visit-outcomes.status', $outcome), ['enabled' => true])
        ->assertSessionHas('success', 'Resultado de visita habilitado');

    expect($outcome->fresh()->enabled)->toBeTrue()
        ->and(VisitOutcome::query()->count())->toBe(1);
});

test('a disabled visit outcome keeps reaching the app, marked disabled', function (): void {
    $this->travelTo('2026-11-01 10:00:00');
    $driverUser = User::factory()->create(['enabled' => true]);
    Driver::factory()->create(['user_id' => $driverUser->id, 'enabled' => true]);
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente']);
    $cursor = now()->addMinute()->toIso8601String();

    $this->travelTo('2026-11-01 10:30:00');
    $this->actingAs($this->admin)->patch(route('visit-outcomes.status', $outcome), ['enabled' => false]);

    Sanctum::actingAs($driverUser, ['*']);
    $rows = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.visit_outcomes');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($outcome->id)
        ->and($rows[0]['enabled'])->toBeFalse();
});

test('a name taken by another outcome in another case or accent is rejected and nothing changes', function (): void {
    VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente']);
    $editing = VisitOutcome::factory()->create(['code' => null, 'name' => 'Cerrado']);

    $this->actingAs($this->admin)
        ->post(route('visit-outcomes.store'), ['name' => ' AUSENTE ', 'triggers_reschedule' => false])
        ->assertSessionHasErrors(['name' => 'Ya hay un resultado de visita con ese nombre.']);

    $this->actingAs($this->admin)
        ->patch(route('visit-outcomes.update', $editing), ['name' => 'áusente', 'triggers_reschedule' => true])
        ->assertSessionHasErrors(['name' => 'Ya hay un resultado de visita con ese nombre.']);

    $editing->refresh();

    expect(VisitOutcome::query()->count())->toBe(2)
        ->and($editing->name)->toBe('Cerrado')
        ->and($editing->triggers_reschedule)->toBeFalse();
});

test('an outcome may keep its own name in another case', function (): void {
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'ausente']);

    $this->actingAs($this->admin)
        ->patch(route('visit-outcomes.update', $outcome), ['name' => 'Ausente', 'triggers_reschedule' => false])
        ->assertSessionHasNoErrors();

    expect($outcome->fresh()->name)->toBe('Ausente');
});

test('an empty or too long name or a missing reschedule is rejected and nothing changes', function (): void {
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente']);

    $this->actingAs($this->admin)
        ->post(route('visit-outcomes.store'), ['name' => '   ', 'triggers_reschedule' => false])
        ->assertSessionHasErrors(['name' => 'Ingresá el nombre del resultado.']);

    $this->actingAs($this->admin)
        ->patch(route('visit-outcomes.update', $outcome), ['name' => '', 'triggers_reschedule' => false])
        ->assertSessionHasErrors(['name' => 'Ingresá el nombre del resultado.']);

    $this->actingAs($this->admin)
        ->post(route('visit-outcomes.store'), ['name' => str_repeat('a', 101), 'triggers_reschedule' => false])
        ->assertSessionHasErrors(['name' => 'El nombre puede tener hasta 100 caracteres.']);

    $this->actingAs($this->admin)
        ->post(route('visit-outcomes.store'), ['name' => 'Cerrado'])
        ->assertSessionHasErrors(['triggers_reschedule' => 'Indicá si el resultado reprograma la visita.']);

    expect(VisitOutcome::query()->count())->toBe(1)
        ->and($outcome->fresh()->name)->toBe('Ausente');
});

test('the status needs a yes or no', function (): void {
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente']);

    $this->actingAs($this->admin)
        ->patch(route('visit-outcomes.status', $outcome), [])
        ->assertSessionHasErrors(['enabled' => 'Indicá si el resultado queda habilitado.']);

    expect($outcome->fresh()->enabled)->toBeTrue();
});

test('a user without manage-settings gets 403 on every visit outcome endpoint and changes nothing', function (): void {
    $driver = User::factory()->create(['is_super_user' => false]);
    $outcome = VisitOutcome::factory()->create(['code' => 'ABSENT', 'name' => 'Ausente']);

    $this->actingAs($driver)->get(route('visit-outcomes.index'))->assertForbidden();
    $this->actingAs($driver)->post(route('visit-outcomes.store'), ['name' => 'Nuevo', 'triggers_reschedule' => false])->assertForbidden();
    $this->actingAs($driver)->patch(route('visit-outcomes.update', $outcome), ['name' => 'Otro', 'triggers_reschedule' => true])->assertForbidden();
    $this->actingAs($driver)->patch(route('visit-outcomes.status', $outcome), ['enabled' => false])->assertForbidden();

    $outcome->refresh();

    expect(VisitOutcome::query()->count())->toBe(1)
        ->and($outcome->name)->toBe('Ausente')
        ->and($outcome->triggers_reschedule)->toBeFalse()
        ->and($outcome->enabled)->toBeTrue();
});
