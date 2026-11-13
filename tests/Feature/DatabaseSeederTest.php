<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Agreements\PriceResolver;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\Visits\Models\Visit;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use App\Street\Sales\Models\Sale;
use App\Street\Sales\Models\SaleDetail;
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
function appLogin(string $username): string
{
    return test()->postJson('/api/v1/auth/login', ['username' => $username, 'password' => 'password'])
        ->assertOk()
        ->json('data.token');
}

test('the seeded driver Juan works a 50-stop day from the app, enough to page through the stops', function (): void {
    $response = $this->withToken(appLogin('jperez'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $stops = collect($response->json('data.stops'));
    $customers = collect($response->json('data.customers'))->keyBy('name');

    expect($response->json('data.route.status'))->toBe('IN_PROGRESS')
        ->and($stops)->toHaveCount(50)
        ->and($stops->where('status', 'FINISHED'))->toHaveCount(5)
        ->and($stops->where('status', 'PENDING'))->toHaveCount(45)
        ->and($stops->where('status', 'PENDING')->whereNull('scheduled_time'))->toBeEmpty()
        ->and($stops->pluck('customer_id')->unique())->toHaveCount(50)
        ->and($customers->every(fn (array $customer): bool => $customer['preferences'] !== []))->toBeTrue()
        ->and($response->json('data.truck_inventory'))->toHaveCount(2);
});

test('Juan\'s truck carries enough for every stop still to be made', function (): void {
    $route = todayRouteOf('jperez');
    $pending = $route->stops()->notDone()->count();

    expect((float) $route->inventories()->whereHas('product', fn ($product) => $product->where('name', 'Bidón 20L'))->value('current_quantity'))
        ->toBeGreaterThanOrEqual(2.0 * $pending);
});

test('the seeded driver Esteban has his route planned but not dispatched, so the app gets only his customers', function (): void {
    $response = $this->withToken(appLogin('ebenitez'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $customers = collect($response->json('data.customers'))->keyBy('name');
    $products = collect($response->json('data.products'))->keyBy('name');

    expect($response->json('data.route'))->toBeNull()
        ->and($response->json('data.stops'))->toBe([])
        ->and($response->json('data.truck_inventory'))->toBe([])
        ->and($response->json('data.visits'))->toBe([])
        ->and($response->json('data.sales'))->toBe([])
        ->and($response->json('data.payments'))->toBe([])
        ->and($response->json('data.container_movements'))->toBe([])
        ->and($customers)->toHaveCount(29)
        ->and($customers->keys())->not->toContain('Kiosco Don Ramón')
        ->and($customers['Elena Quispe']['addresses'][0]['floor'])->toBe('PB')
        ->and($customers['Elena Quispe']['preferences'])->not->toBeEmpty()
        ->and(collect($customers['Supermercado El Tunal']['agreements']))->toHaveCount(2)
        ->and(collect($customers['Escuela N° 4032 Limache']['agreements'])->pluck('unit_price')->all())->toBe(['5200.00'])
        ->and($products->map(fn (array $product): string => $product['price'])->all())->toBe(['Bidón 20L' => '6500.00', 'Sifón 1.5L' => '1500.00']);
});

test('the seeded driver Marcos has a customer rescheduled to the afternoon in his route in progress', function (): void {
    $response = $this->withToken(appLogin('mmorales'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $stops = collect($response->json('data.stops'));

    expect($response->json('data.route.status'))->toBe('IN_PROGRESS')
        ->and($stops->where('status', 'RESCHEDULED'))->toHaveCount(1)
        ->and($stops->whereNotNull('is_rescheduled_from'))->toHaveCount(1)
        ->and($stops->whereNotNull('is_rescheduled_from')->first()['scheduled_time'])->toStartWith('16:00')
        ->and($stops->whereNotNull('is_rescheduled_from')->first()['status'])->toBe('PENDING');
});

test('the seeded driver Lucía has her route planned but not dispatched, so the app waits for the truck load', function (): void {
    $route = Route::query()->whereDate('date', '2026-10-20')
        ->whereHas('driver.user', fn ($user) => $user->where('username', 'lfernandez'))
        ->withCount(['stops', 'inventoryLoads'])
        ->sole();

    expect($route->status)->toBe('PLANNED')
        ->and($route->dispatched_at)->toBeNull()
        ->and($route->stops_count)->toBe(20)
        ->and($route->inventory_loads_count)->toBe(0);

    $this->withToken(appLogin('lfernandez'))->getJson('/api/v1/sync/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.route', null)
        ->assertJsonPath('data.stops', [])
        ->assertJsonCount(20, 'data.customers');
});

test('the seeded visit outcomes are absent, refused and reschedule, and only reschedule triggers a reschedule', function (): void {
    $response = $this->withToken(appLogin('ebenitez'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $outcomes = collect($response->json('data.visit_outcomes'))
        ->map(fn (array $outcome): array => [$outcome['code'], $outcome['name'], $outcome['triggers_reschedule']])
        ->sortBy(0)
        ->values()
        ->all();

    expect($outcomes)->toBe([
        ['absent', 'Ausente', false],
        ['refused', 'Sin pedido', false],
        ['reschedule', 'Reprogramar', true],
    ]);
});

test('every seeded customer and user phone reads 387 XXX-XXXX', function (): void {
    $phones = Customer::query()->pluck('phone_number')->merge(User::query()->pluck('phone_number'))->filter();

    expect($phones)->not->toBeEmpty()
        ->and($phones->reject(fn (string $phone): bool => preg_match('/^\d{3} \d{3}-\d{4}$/', $phone) === 1)->all())->toBe([]);
});

test('the first pull of Esteban carries the disabled customer', function (): void {
    $response = $this->withToken(appLogin('ebenitez'))->getJson('/api/v1/sync/pull')->assertOk();

    $customer = collect($response->json('data.customers'))->firstWhere('name', 'Kiosco Don Ramón');

    expect($customer['enabled'])->toBeFalse();
});

test('credit sales raise the seeded customer balance', function (): void {
    $sanatorio = Customer::query()->where('name', 'Sanatorio Nuestra Señora del Rosario')->firstOrFail();
    $distribuidora = Customer::query()->where('name', 'Distribuidora del Norte S.R.L.')->firstOrFail();

    expect((float) $sanatorio->balance->current_balance)->toBe(45000.0 + 8 * 5200.0)
        ->and((float) $distribuidora->balance->current_balance)->toBe(62000.0 + 15 * 5000.0 + 6 * 1500.0);
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
        ->and(Customer::query()->where('phone_number', 'not like', '387 %')->pluck('name')->all())->toBe([]);
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

test('the seeded driver Juan gets one requested stop today, synced to the app with its note', function (): void {
    $response = $this->withToken(appLogin('jperez'))->getJson('/api/v1/sync/bootstrap')->assertOk();

    $requested = collect($response->json('data.stops'))->where('is_requested', true);

    expect($requested)->toHaveCount(1)
        ->and(RouteStop::with('customer')->find($requested->first()['id'])->customer->name)->toBe('Marta Tolaba')
        ->and(ScheduledVisit::sole()->note)->toBe('Llamó, quiere 2 bidones');
});

test('running the seeder again does not duplicate the scheduled visit', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(ScheduledVisit::count())->toBe(1);
});

test('seeded agreements price a 20L bottle between 5000 and 6000, with a single soda agreement as an example', function (): void {
    $agreements = CustomerAgreement::with('product')->get();
    $agua = $agreements->where('product.name', 'Bidón 20L');
    $soda = $agreements->where('product.name', 'Sifón 1.5L');

    expect($agua)->not->toBeEmpty()
        ->and($agua->every(fn (CustomerAgreement $agreement): bool => (float) $agreement->unit_price >= 5000 && (float) $agreement->unit_price <= 6000))->toBeTrue()
        ->and($soda->pluck('unit_price')->map(fn ($price): float => (float) $price)->all())->toBe([1300.0]);
});

test('every seeded sale charges the price the customer had that day', function (): void {
    $prices = app(PriceResolver::class);

    $mismatches = SaleDetail::with(['sale.customer', 'product'])->get()
        ->reject(fn (SaleDetail $detail): bool => (float) $detail->unit_price === (float) $prices->priceFor($detail->sale->customer, $detail->product, $detail->sale->date)->unitPrice);

    expect($mismatches->map(fn (SaleDetail $detail): string => "{$detail->sale->customer->name}: {$detail->product->name} {$detail->unit_price}")->values()->all())->toBe([]);
});

test('no seeded visit is left in progress, since the app only pushes finished visits', function (): void {
    expect(Visit::query()->where('status', 'IN_PROGRESS')->count())->toBe(0);
});

test('the seeded drivers have a username and no email, and the admin keeps the email', function (): void {
    $drivers = User::query()->whereHas('driver')->orderBy('username')->get(['username', 'email']);

    expect($drivers->pluck('username')->all())->toBe(['cgomez', 'ebenitez', 'jperez', 'lfernandez', 'mmorales'])
        ->and($drivers->whereNotNull('email'))->toBeEmpty()
        ->and(User::query()->where('is_super_user', true)->value('email'))->toBe('admin@marilin.com');
});

/**
 * Today's route of a seeded driver, with its stops and their addresses.
 */
function todayRouteOf(string $username): Route
{
    return Route::query()->whereDate('date', '2026-10-20')
        ->whereHas('driver.user', fn ($user) => $user->where('username', $username))
        ->with('stops.address')
        ->sole();
}

test('today Juan\'s dispatched route has 50 stops, Esteban\'s 29 planned ones and Lucía\'s 20', function (): void {
    $juan = todayRouteOf('jperez');
    $esteban = todayRouteOf('ebenitez');
    $lucia = todayRouteOf('lfernandez');

    expect($juan->status)->toBe('IN_PROGRESS')
        ->and($juan->dispatched_at)->not->toBeNull()
        ->and($juan->stops)->toHaveCount(50)
        ->and($juan->stops->pluck('customer_id')->unique())->toHaveCount(50)
        ->and($esteban->status)->toBe('PLANNED')
        ->and($esteban->dispatched_at)->toBeNull()
        ->and($esteban->started_at)->toBeNull()
        ->and($esteban->stops)->toHaveCount(29)
        ->and($esteban->stops->whereNotNull('is_rescheduled_from'))->toBeEmpty()
        ->and($esteban->stops->pluck('customer_id')->unique())->toHaveCount(29)
        ->and($esteban->inventoryLoads()->exists())->toBeFalse()
        ->and(Visit::query()->whereIn('route_stop_id', $esteban->stops->pluck('id'))->exists())->toBeFalse()
        ->and(Visit::query()->where('driver_id', $esteban->assigned_driver_id)->whereDate('arrived_at', '2026-10-20')->exists())->toBeFalse()
        ->and($lucia->stops)->toHaveCount(20)
        ->and($lucia->stops->pluck('customer_id')->unique())->toHaveCount(20);
});

test('Lucía\'s stops spread over several neighborhoods, sharing some with Esteban and some with Juan', function (): void {
    $neighborhoods = fn (string $username) => todayRouteOf($username)->stops->pluck('address.neighborhood');
    $lucia = $neighborhoods('lfernandez');

    expect($lucia->filter())->toHaveCount(20)
        ->and($lucia->unique()->count())->toBeGreaterThanOrEqual(4)
        ->and($lucia->unique()->intersect($neighborhoods('ebenitez'))->count())->toBeGreaterThanOrEqual(2)
        ->and($lucia->unique()->intersect($neighborhoods('jperez'))->count())->toBeGreaterThanOrEqual(2);
});

test('splitting Lucía\'s route suggests Esteban in his neighborhoods, and Juan too though his route is already dispatched', function (): void {
    $admin = User::query()->where('email', 'admin@marilin.com')->sole();

    $groups = collect($this->actingAs($admin)->get(route('routes.split', todayRouteOf('lfernandez')))
        ->assertOk()
        ->viewData('page')['props']['groups']);
    $suggested = $groups->flatMap(fn (array $group): array => array_column($group['suggestions'], 'driverName'));

    expect($groups->filter(fn (array $group): bool => in_array('Esteban Benítez', array_column($group['suggestions'], 'driverName'), true))->count())->toBeGreaterThanOrEqual(2)
        // Juan is already on the street, but his route still takes stops: a route is reorganized on the go.
        ->and($suggested)->toContain('Juan Pérez');
});

test('seeded customers read like people or businesses, never "Familia X"', function (): void {
    expect(Customer::query()->where('name', 'like', 'Familia %')->pluck('name')->all())->toBe([])
        ->and(Customer::query()->pluck('name'))->toContain('Franco Calisaya', 'Guada Guaymás', 'Comedor San Cayetano', 'Kiosco El Pato', 'Despensa Los Hermanos');
});

test('some seeded primary addresses carry a visual reference and others do not', function (): void {
    $addresses = CustomerAddress::query()->where('is_primary', true)->get();

    expect($addresses->whereNotNull('visual_reference'))->not->toBeEmpty()
        ->and($addresses->whereNull('visual_reference'))->not->toBeEmpty();
});
