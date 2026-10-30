<?php

declare(strict_types=1);

use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use App\Street\Sales\Models\Sale;
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
        ->and(collect($customers['Escuela N° 4032 Limache']['agreements'])->pluck('unit_price')->all())->toBe(['1800.00'])
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
    $distribuidora = Customer::query()->where('name', 'Distribuidora del Norte S.R.L.')->firstOrFail();

    expect((float) $sanatorio->balance->current_balance)->toBe(45000.0 + 8 * 1900.0)
        ->and((float) $distribuidora->balance->current_balance)->toBe(62000.0 + 15 * 1800.0 + 6 * 1000.0);
});

test('running the seeder again does not duplicate stops, agreements, products or balances', function (): void {
    $counts = fn (): array => [
        RouteStop::query()->count(),
        CustomerAgreement::query()->count(),
        Product::query()->count(),
        Sale::query()->count(),
        Payment::query()->count(),
        (float) Customer::query()->where('name', 'Sanatorio Nuestra Señora del Rosario')->firstOrFail()->balance->current_balance,
    ];
    $before = $counts();

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($before);
});

test('the seeded customers live in Salta capital, in neighborhoods of the catalog', function (): void {
    $catalog = Neighborhood::query()->pluck('name');
    $addresses = CustomerAddress::query()->with('customer')->get();

    expect($catalog)->toHaveCount(398)
        ->and($addresses)->not->toBeEmpty()
        ->and($addresses->whereNotNull('neighborhood')->pluck('neighborhood')->diff($catalog)->values()->all())->toBe([])
        ->and($addresses->filter(fn (CustomerAddress $address): bool => collect([$address->street, $address->neighborhood, $address->visual_reference, $address->customer->name])
            ->contains(fn (?string $text): bool => $text !== null && stripos($text, 'Jujuy') !== false))->pluck('street')->all())->toBe([])
        ->and($addresses->every(fn (CustomerAddress $address): bool => abs((float) $address->latitude + 24.78) < 0.1 && abs((float) $address->longitude + 65.41) < 0.1))->toBeTrue()
        ->and(Customer::query()->where('phone_number', 'not like', '+54 387 %')->pluck('name')->all())->toBe([]);
});

test('the seeded trucks are loaded in the morning, before their route starts', function (): void {
    $loads = RouteInventoryLoad::query()->with('route')->get();

    expect($loads)->not->toBeEmpty()
        ->and($loads->reject(fn (RouteInventoryLoad $load): bool => $load->loaded_at->isSameDay($load->route->started_at)
            && $load->loaded_at->lessThan($load->route->started_at))->pluck('id')->all())->toBe([]);
});

test('yesterday every driver closed a route with visited stops, sales and payments, comparable to today', function (): void {
    $routes = Route::query()->whereDate('date', '2026-10-19')->with(['stops.visit', 'inventoryLoads'])->get();
    $stops = $routes->flatMap->stops;
    $yesterday = app(DailyFigures::class)->forDay('2026-10-19', DayScope::all());
    $today = app(DailyFigures::class)->forDay('2026-10-20', DayScope::all());

    expect($routes)->toHaveCount(4)
        ->and($routes->pluck('status')->unique()->values()->all())->toBe(['COMPLETED'])
        ->and($routes->pluck('assigned_driver_id')->unique())->toHaveCount(4)
        ->and($routes->every(fn (Route $route): bool => $route->completed_at !== null && $route->inventoryLoads->isNotEmpty()))->toBeTrue()
        ->and($stops)->not->toBeEmpty()
        ->and($stops->filter(fn (RouteStop $stop): bool => $stop->visit === null || ! $stop->isDone())->pluck('id')->all())->toBe([])
        ->and($yesterday->payments)->not->toBeEmpty()
        ->and($yesterday->cashPayments)->not->toBeEmpty()
        ->and($yesterday->transferPayments)->not->toBeEmpty()
        ->and($yesterday->creditSales)->toBeGreaterThan(0.0)
        ->and($yesterday->sales->whereNull('visit_id'))->toBeEmpty()
        ->and($today->totalSales)->toBeGreaterThan(0.0)
        ->and($yesterday->totalSales)->toBeGreaterThan($today->totalSales * 0.5)
        ->and($yesterday->totalSales)->toBeLessThan($today->totalSales * 2);
});
