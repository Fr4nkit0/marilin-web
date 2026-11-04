<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
});

test('the settings page lists every payment method, enabled first, then by name', function (): void {
    $transfer = PaymentMethod::factory()->transferencia()->create(['enabled' => false]);
    $cash = PaymentMethod::factory()->efectivo()->create();

    $this->actingAs($this->admin)
        ->get(route('payment-methods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/PaymentMethods')
            ->has('paymentMethods', 2)
            ->where('paymentMethods.0', ['id' => $cash->id, 'code' => 'CASH', 'name' => 'Efectivo', 'enabled' => true])
            ->where('paymentMethods.1', ['id' => $transfer->id, 'code' => 'TRANSFER', 'name' => 'Transferencia Bancaria', 'enabled' => false]));
});

test('payment methods cannot be created', function (): void {
    expect(Route::has('payment-methods.store'))->toBeFalse();

    $this->actingAs($this->admin)
        ->post('/settings/payment-methods', ['name' => 'Cheque'])
        ->assertStatus(405);

    expect(PaymentMethod::query()->count())->toBe(0);
});

test('the admin renames a payment method, never its code', function (): void {
    $method = PaymentMethod::factory()->transferencia()->create();

    $this->actingAs($this->admin)
        ->from(route('payment-methods.index'))
        ->patch(route('payment-methods.update', $method), ['name' => '  Transferencia  ', 'code' => 'CASH'])
        ->assertRedirect(route('payment-methods.index'))
        ->assertSessionHas('success', 'Medio de pago actualizado');

    $method->refresh();

    expect($method->name)->toBe('Transferencia')
        ->and($method->code)->toBe('TRANSFER');
});

test('the admin disables a payment method and enables it again, never deleting it', function (): void {
    $method = PaymentMethod::factory()->transferencia()->create();

    $this->actingAs($this->admin)
        ->from(route('payment-methods.index'))
        ->patch(route('payment-methods.status', $method), ['enabled' => false])
        ->assertRedirect(route('payment-methods.index'))
        ->assertSessionHas('success', 'Medio de pago deshabilitado');

    expect($method->fresh()->enabled)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('payment-methods.status', $method), ['enabled' => true])
        ->assertSessionHas('success', 'Medio de pago habilitado');

    expect($method->fresh()->enabled)->toBeTrue()
        ->and(PaymentMethod::query()->count())->toBe(1);
});

test('a disabled payment method keeps reaching the app, marked disabled', function (): void {
    $this->travelTo('2026-11-01 10:00:00');
    $driverUser = User::factory()->create(['enabled' => true]);
    Driver::factory()->create(['user_id' => $driverUser->id, 'enabled' => true]);
    $method = PaymentMethod::factory()->transferencia()->create();
    $cursor = now()->addMinute()->toIso8601String();

    $this->travelTo('2026-11-01 10:30:00');
    $this->actingAs($this->admin)->patch(route('payment-methods.status', $method), ['enabled' => false]);

    Sanctum::actingAs($driverUser, ['*']);
    $rows = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.payment_methods');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($method->id)
        ->and($rows[0]['enabled'])->toBeFalse();
});

test('a name taken by another payment method in another case or accent is rejected and nothing changes', function (): void {
    PaymentMethod::factory()->efectivo()->create();
    $renaming = PaymentMethod::factory()->transferencia()->create();

    $this->actingAs($this->admin)
        ->patch(route('payment-methods.update', $renaming), ['name' => ' EFÉCTIVO '])
        ->assertSessionHasErrors(['name' => 'Ya hay un medio de pago con ese nombre.']);

    expect($renaming->fresh()->name)->toBe('Transferencia Bancaria');
});

test('a payment method may keep its own name in another case', function (): void {
    $method = PaymentMethod::factory()->efectivo()->create(['name' => 'efectivo']);

    $this->actingAs($this->admin)
        ->patch(route('payment-methods.update', $method), ['name' => 'Efectivo'])
        ->assertSessionHasNoErrors();

    expect($method->fresh()->name)->toBe('Efectivo');
});

test('an empty or too long name is rejected and nothing changes', function (): void {
    $method = PaymentMethod::factory()->efectivo()->create();

    $this->actingAs($this->admin)
        ->patch(route('payment-methods.update', $method), ['name' => '   '])
        ->assertSessionHasErrors(['name' => 'Ingresá el nombre del medio de pago.']);

    $this->actingAs($this->admin)
        ->patch(route('payment-methods.update', $method), ['name' => str_repeat('a', 81)])
        ->assertSessionHasErrors(['name' => 'El nombre puede tener hasta 80 caracteres.']);

    expect($method->fresh()->name)->toBe('Efectivo');
});

test('the status needs a yes or no', function (): void {
    $method = PaymentMethod::factory()->efectivo()->create();

    $this->actingAs($this->admin)
        ->patch(route('payment-methods.status', $method), [])
        ->assertSessionHasErrors(['enabled' => 'Indicá si el medio de pago queda habilitado.']);

    expect($method->fresh()->enabled)->toBeTrue();
});

test('a user without manage-settings gets 403 on every payment method endpoint and changes nothing', function (): void {
    $driver = User::factory()->create(['is_super_user' => false]);
    $method = PaymentMethod::factory()->efectivo()->create();

    $this->actingAs($driver)->get(route('payment-methods.index'))->assertForbidden();
    $this->actingAs($driver)->patch(route('payment-methods.update', $method), ['name' => 'Otro'])->assertForbidden();
    $this->actingAs($driver)->patch(route('payment-methods.status', $method), ['enabled' => false])->assertForbidden();

    $method->refresh();

    expect($method->name)->toBe('Efectivo')
        ->and($method->enabled)->toBeTrue();
});
