<?php

declare(strict_types=1);

use App\Catalog\Models\Product;
use App\Models\Customer;
use App\Models\CustomerAgreement;
use App\Models\RouteStop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 07:30:00');
    $this->seed(DatabaseSeeder::class);
});

/**
 * Logs into the API like the mobile app does and returns the bearer token.
 */
function appLogin(string $email): string
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])
        ->assertOk()
        ->json('data.token');
}

test('the seeded driver Esteban gets a full day to work from the app', function (): void {
    $response = $this->withToken(appLogin('esteban.benitez@marilin.com'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $stops = collect($response->json('data.stops'));
    $customers = collect($response->json('data.customers'))->keyBy('name');
    $products = collect($response->json('data.products'))->keyBy('name');

    expect($stops)->toHaveCount(7)
        ->and($stops->where('status', 'PENDING'))->toHaveCount(6)
        ->and($stops->where('status', 'RESCHEDULED'))->toHaveCount(1)
        ->and($stops->whereNotNull('is_rescheduled_from')->first()['scheduled_time'])->toStartWith('16:00')
        ->and($stops->whereNull('scheduled_time'))->toBeEmpty()
        ->and($customers->keys())->not->toContain('Kiosco Don Ramón')
        ->and($customers['Familia Quispe']['addresses'][0]['floor'])->toBe('PB')
        ->and($customers['Familia Quispe']['preferences'])->not->toBeEmpty()
        ->and(collect($customers['Supermercado El Tunal']['agreements']))->toHaveCount(2)
        ->and(collect($customers['Escuela N° 432 Alto Comedero']['agreements'])->pluck('unit_price')->all())->toBe(['1800.00'])
        ->and($products->map(fn (array $product): string => $product['price'])->all())->toBe(['Bidón 20L' => '6500.00', 'Sifón 1.5L' => '1500.00'])
        ->and($response->json('data.truck_inventory'))->toHaveCount(2);
});

test('the seeded visit outcomes are absent, refused and reschedule, and only reschedule triggers a reschedule', function (): void {
    $response = $this->withToken(appLogin('esteban.benitez@marilin.com'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $outcomes = collect($response->json('data.visit_outcomes'))
        ->map(fn (array $outcome): array => [$outcome['code'], $outcome['name'], $outcome['triggers_reschedule']])
        ->sortBy(0)
        ->values()
        ->all();

    expect($outcomes)->toBe([
        ['absent', 'Ausente', false],
        ['refused', 'No quiso', false],
        ['reschedule', 'Reprogramar', true],
    ]);
});

test('the first pull of Esteban carries the disabled customer', function (): void {
    $response = $this->withToken(appLogin('esteban.benitez@marilin.com'))->getJson('/api/v1/sync/pull')->assertOk();

    $customer = collect($response->json('data.customers'))->firstWhere('name', 'Kiosco Don Ramón');

    expect($customer['enabled'])->toBeFalse()
        ->and(collect($response->json('data.products'))->pluck('name')->all())->not->toContain('Bidón de Agua 12L');
});

test('credit sales raise the seeded customer balance', function (): void {
    $sanatorio = Customer::query()->where('name', 'Sanatorio Nuestra Señora del Rosario')->firstOrFail();
    $distribuidora = Customer::query()->where('name', 'Distribuidora San Salvador S.R.L.')->firstOrFail();

    expect((float) $sanatorio->balance->current_balance)->toBe(45000.0 + 8 * 1900.0)
        ->and((float) $distribuidora->balance->current_balance)->toBe(62000.0 + 15 * 1800.0 + 6 * 1000.0);
});

test('running the seeder again does not duplicate stops, agreements, products or balances', function (): void {
    $counts = fn (): array => [
        RouteStop::query()->count(),
        CustomerAgreement::query()->count(),
        Product::query()->count(),
        (float) Customer::query()->where('name', 'Sanatorio Nuestra Señora del Rosario')->firstOrFail()->balance->current_balance,
    ];
    $before = $counts();

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($before);
});
