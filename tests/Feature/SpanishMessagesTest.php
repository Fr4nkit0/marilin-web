<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
});

test('the app speaks Spanish and falls back to English', function (): void {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and(app()->getLocale())->toBe('es');
});

test('a web form error is shown in Spanish with the field name in Spanish', function (): void {
    $this->actingAs(User::factory()->superUser()->create());

    $this->post(route('customers.store'), ['name' => '', 'street' => 'Mitre 50'])
        ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);

    expect(Customer::query()->count())->toBe(0);
});

test('a domain rule broken in a web form is shown in Spanish', function (): void {
    $this->actingAs(User::factory()->superUser()->create());

    $this->post(route('customers.store'), [
        'name' => 'Kiosco Ana',
        'street' => 'Mitre 50',
        'preferences' => [['day_of_week' => 2, 'time_window_start' => '12:00', 'time_window_end' => '08:00']],
    ])->assertSessionHasErrors(['preferences.0.time_window_end' => 'El horario de fin debe ser posterior al de inicio.']);

    expect(Customer::query()->count())->toBe(0);
});

test('a wrong password at the web login is answered in Spanish', function (): void {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);

    $this->assertGuest();
});

describe('sync push', function (): void {
    beforeEach(function (): void {
        $user = User::factory()->create(['enabled' => true]);
        Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
        Sanctum::actingAs($user, ['*']);
    });

    test('a record that breaks a validation rule is rejected with a Spanish message', function (): void {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => $uuid,
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['street' => 'Av Belgrano 100'],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.uuid', $uuid)
            ->assertJsonPath('data.customers.0.status', 'rejected')
            ->assertJsonPath('data.customers.0.error', 'El campo nombre es obligatorio.');

        expect(Customer::query()->count())->toBe(0);
    });

    test('a nested field is named in Spanish', function (): void {
        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'name' => 'Almacen Don Pepe',
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['neighborhood' => 'Centro'],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.error', 'El campo calle es obligatorio.');
    });

    test('a record without its customer reference is told the customer is required', function (): void {
        $this->postJson('/api/v1/sync/push', ['payments' => [[
            'uuid' => (string) Str::uuid(),
            'payment_method_id' => 1,
            'amount' => 100,
            'paid_at' => '2026-10-22T09:00:00-03:00',
        ]]])
            ->assertOk()
            ->assertJsonPath('data.payments.0.status', 'rejected')
            ->assertJsonPath('data.payments.0.error', 'El campo cliente es obligatorio.');
    });

    test('a domain rejection keeps its English message', function (): void {
        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'server_id' => 999999,
            'name' => 'Almacen Don Pepe',
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['street' => 'Av Belgrano 100'],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.status', 'rejected')
            ->assertJsonPath('data.customers.0.error', 'The server_id does not match any customer.');

        expect(Customer::query()->count())->toBe(0);
    });
});
