<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Street\Sales\Models\Sale;
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

    test('a domain rejection is answered in Spanish', function (): void {
        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'server_id' => 999999,
            'name' => 'Almacen Don Pepe',
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['street' => 'Av Belgrano 100'],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.status', 'rejected')
            ->assertJsonPath('data.customers.0.error', 'El identificador del servidor no corresponde a ningún cliente.');

        expect(Customer::query()->count())->toBe(0);
    });

    test('a sale whose total does not match its lines is rejected in Spanish', function (): void {
        $customer = Customer::factory()->create(['driver_id' => Driver::query()->firstOrFail()->id]);
        $product = Product::factory()->create(['enabled' => true, 'price' => 700]);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sync/push', ['sales' => [[
            'uuid' => $uuid,
            'customer_id' => $customer->id,
            'date' => '2026-10-22T10:30:00-03:00',
            'total' => 999,
            'is_credit_sale' => true,
            'details' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 700]],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.sales.0.status', 'rejected')
            ->assertJsonPath('data.sales.0.error', 'El total no coincide con la suma de las líneas de la venta.');

        expect(Sale::query()->count())->toBe(0);
    });

    test('the review reasons of an accepted record are the Spanish ones the review inbox shows', function (): void {
        $other = Driver::factory()->create(['enabled' => true]);
        $customer = Customer::factory()->create(['driver_id' => $other->id]);
        $product = Product::factory()->create(['enabled' => true, 'price' => 700]);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sync/push', ['sales' => [[
            'uuid' => $uuid,
            'customer_id' => $customer->id,
            'date' => '2026-10-22T10:30:00-03:00',
            'total' => 1000,
            'is_credit_sale' => true,
            'details' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 500]],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.sales.0.status', 'accepted')
            ->assertJsonPath('data.sales.0.review_reasons', [
                'El cliente está asignado a otro repartidor.',
                'Venta con precio distinto al acordado o de lista.',
            ]);

        // What is stored stays the reason's stable value; only the answer is translated.
        expect(Sale::query()->where('client_uuid', $uuid)->firstOrFail()->review_reasons)
            ->toBe([ReviewReason::ForeignCustomer->value, ReviewReason::PriceMismatch->value]);
    });

    test('a street longer than its column is rejected naming the field', function (): void {
        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'name' => 'Almacen Don Pepe',
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['street' => str_repeat('a', 201)],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.status', 'rejected')
            ->assertJsonPath('data.customers.0.error', 'El campo calle no puede tener más de 200 caracteres.');

        expect(Customer::query()->count())->toBe(0);
    });

    test('a customer address field longer than its column is rejected naming the field', function (string $field, int $max, string $name): void {
        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'name' => 'Almacen Don Pepe',
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['street' => 'Av Belgrano 100', $field => str_repeat('a', $max + 1)],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.status', 'rejected')
            ->assertJsonPath('data.customers.0.error', "El campo {$name} no puede tener más de {$max} caracteres.");

        expect(Customer::query()->count())->toBe(0);
    })->with([
        'apartment' => ['apartment', 20, 'departamento'],
        'neighborhood' => ['neighborhood', 255, 'barrio'],
    ]);

    test('a street of exactly its column limit is accepted', function (): void {
        $this->postJson('/api/v1/sync/push', ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'name' => 'Almacen Don Pepe',
            'modified_at' => '2026-10-20T09:00:00-03:00',
            'address' => ['street' => str_repeat('a', 200)],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.customers.0.status', 'accepted');

        expect(Customer::query()->count())->toBe(1);
    });
});

test('a user who is not an active driver is told so in Spanish by the sync', function (): void {
    Sanctum::actingAs(User::factory()->create(['enabled' => true]), ['*']);

    $this->getJson('/api/v1/sync/bootstrap')
        ->assertForbidden()
        ->assertJsonPath('message', 'El usuario no es un repartidor activo.');
});

test('every rejection the push can answer has its Spanish translation', function (string $message): void {
    expect(trans()->hasForLocale($message, 'es'))->toBeTrue();
})->with(fn (): array => rejectionMessages());

/**
 * Every message a push rejection is built from: the literal ones in the code and each concrete form of the
 * ones built from an entity name.
 *
 * @return list<string>
 */
function rejectionMessages(): array
{
    $literal = [];

    $app = dirname(__DIR__, 2).'/app';

    foreach ([...glob("{$app}/Sync/*.php"), ...glob("{$app}/Customers/Customers/*.php")] as $file) {
        preg_match_all('/new (?:RejectedRecord|InvalidCustomerChange)\(\s*\'([^\']+)\'/', file_get_contents($file), $found);
        preg_match_all('/^\s*"((?:The|A) [^"]*\.)",$/m', file_get_contents($file), $doubleQuoted);
        preg_match_all("/^\\s*'((?:The|A) [^']*\\.)',$/m", file_get_contents($file), $singleQuoted);
        $literal = [...$literal, ...$found[1], ...$doubleQuoted[1], ...$singleQuoted[1]];
    }

    $named = [
        ...array_map(fn (string $name): string => "The server_id does not match any {$name}.", ['customer', 'customer address', 'customer preference']),
        ...array_map(fn (string $name): string => "The {$name} belongs to another customer.", ['record', 'visit', 'sale', 'customer agreement']),
        ...array_map(fn (string $name): string => "The {$name} belongs to another driver.", ['sale', 'payment']),
        ...array_map(
            fn (string $prefix): string => "The {$prefix} reference does not match any record.",
            ['customer', 'visit', 'sale', 'payment', 'route_stop', 'rescheduled_from', 'customer_agreement'],
        ),
    ];

    return array_values(array_unique([...$literal, ...$named, 'The record could not be saved.']));
}
