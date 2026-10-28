<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\Sale;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->actingAs(User::factory()->superUser()->create());
});

test('the dashboard shows the requested day and each driver row carries the driver id for the cash close link', function (): void {
    $driver = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Juan Pérez'])->id]);
    Route::factory()->create(['assigned_driver_id' => $driver->id, 'date' => '2026-10-19', 'status' => 'COMPLETED']);
    Sale::factory()->create(['date' => '2026-10-19 10:00:00', 'total' => 1200]);
    Sale::factory()->create(['date' => '2026-10-20 10:00:00', 'total' => 900]);

    $this->get(route('dashboard', ['date' => '2026-10-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('metrics.date', '2026-10-19')
            ->where('metrics.kpis.totalSales', 1200)
            ->where('metrics.repartidores.0.id', $driver->id)
            ->where('metrics.repartidores.0.name', 'Juan Pérez'));
});

test('each KPI card carries the records behind it', function (): void {
    $driverUser = User::factory()->create(['name' => 'Juan Pérez']);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $route = Route::factory()->create(['assigned_driver_id' => $driver->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);
    $hotel = Customer::factory()->create(['name' => 'Hotel Altos de la Viña']);
    $tolaba = Customer::factory()->create(['name' => 'Familia Tolaba']);
    $verduleria = Customer::factory()->create(['name' => 'Verdulería Los Andes']);

    $done = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $hotel->id, 'scheduled_time' => '09:00']);
    Visit::factory()->create(['route_stop_id' => $done->id, 'customer_id' => $hotel->id, 'driver_id' => $driver->id, 'status' => 'FINISHED']);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $tolaba->id, 'scheduled_time' => '13:00']);
    $absent = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $verduleria->id, 'scheduled_time' => '08:30']);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $verduleria->id, 'scheduled_time' => '16:00', 'is_rescheduled_from' => $absent->id]);

    Sale::factory()->create(['customer_id' => $hotel->id, 'created_by' => $driverUser->id, 'date' => '2026-10-20 09:10:00', 'total' => 3400, 'is_credit_sale' => false]);
    Payment::factory()->create([
        'customer_id' => $hotel->id,
        'received_by' => $driverUser->id,
        'payment_method_id' => PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo'])->id,
        'paid_at' => '2026-10-20 09:12:00',
        'amount' => 3400,
        'status' => 'CONFIRMED',
    ]);
    ContainerMovement::factory()->create([
        'customer_id' => $hotel->id,
        'created_by' => $driverUser->id,
        'container_type_id' => ContainerType::factory()->create(['description' => 'Bidón 20L'])->id,
        'movement_type' => 'RETURNED',
        'direction' => -1,
        'quantity' => 6,
        'created_at' => '2026-10-20 09:11:00',
    ]);

    $details = null;
    $this->get(route('dashboard', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$details): void {
            $details = $page->toArray()['props']['metrics']['details'];
        });

    expect($details['sales'][0])->toMatchArray(['customer' => 'Hotel Altos de la Viña', 'driver' => 'Juan Pérez', 'at' => '2026-10-20 09:10', 'amount' => 3400, 'credit' => false])
        ->and($details['payments'][0])->toMatchArray(['customer' => 'Hotel Altos de la Viña', 'driver' => 'Juan Pérez', 'method' => 'CASH', 'amount' => 3400])
        ->and($details['containers'][0])->toMatchArray(['customer' => 'Hotel Altos de la Viña', 'driver' => 'Juan Pérez', 'container' => 'Bidón 20L', 'movement' => 'RETURNED', 'quantity' => 6])
        ->and(collect($details['stops'])->map(fn (array $stop): array => [$stop['time'], $stop['customer'], $stop['status']])->all())->toBe([
            ['08:30', 'Verdulería Los Andes', 'RESCHEDULED'],
            ['09:00', 'Hotel Altos de la Viña', 'FINISHED'],
            ['13:00', 'Familia Tolaba', 'PENDING'],
            ['16:00', 'Verdulería Los Andes', 'PENDING'],
        ])
        ->and($details['stops'][0]['driver'])->toBe('Juan Pérez');
});

test('the system card shows each driver last app sync and flags a route in progress that went quiet', function (): void {
    $juan = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Juan Pérez'])->id, 'enabled' => true]);
    $esteban = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Esteban Benítez'])->id, 'enabled' => true]);
    $carlos = Driver::factory()->create(['user_id' => User::factory()->create(['name' => 'Carlos Gómez'])->id, 'enabled' => true]);
    Route::factory()->create(['assigned_driver_id' => $juan->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);
    Route::factory()->create(['assigned_driver_id' => $esteban->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);

    $juan->user->createToken('android')->accessToken->forceFill(['last_used_at' => now()->subMinutes(4)])->save();
    $esteban->user->createToken('android')->accessToken->forceFill(['last_used_at' => now()->subMinutes(45)])->save();

    $sync = null;
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$sync): void {
            $sync = collect($page->toArray()['props']['metrics']['sync'])->keyBy('name');
        });

    expect($sync['Juan Pérez'])->toMatchArray(['lastSyncAt' => '2026-10-20T17:56:00-03:00', 'onRoute' => true, 'stale' => false])
        ->and($sync['Esteban Benítez'])->toMatchArray(['lastSyncAt' => '2026-10-20T17:15:00-03:00', 'onRoute' => true, 'stale' => true])
        ->and($sync['Carlos Gómez'])->toMatchArray(['lastSyncAt' => null, 'onRoute' => false, 'stale' => false]);
});

test('the dashboard shows what each driver and the house took in, and the day totals add them all up', function (): void {
    $driverUser = User::factory()->create(['name' => 'Juan Pérez']);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $route = Route::factory()->create(['assigned_driver_id' => $driver->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);
    $stop = RouteStop::factory()->create(['route_id' => $route->id]);
    $visit = Visit::factory()->create(['route_stop_id' => $stop->id, 'driver_id' => $driver->id, 'status' => 'FINISHED']);
    $admin = User::factory()->superUser()->create();
    $cash = PaymentMethod::factory()->create(['code' => 'CASH', 'name' => 'Efectivo']);

    // On the driver's visit, even if an administrator typed it in.
    Sale::factory()->create(['visit_id' => $visit->id, 'created_by' => $admin->id, 'date' => '2026-10-20 10:00:00', 'total' => 1000]);
    // Loose, recorded by the driver.
    Sale::factory()->create(['visit_id' => null, 'created_by' => $driverUser->id, 'date' => '2026-10-20 11:00:00', 'total' => 200]);
    // House: loose, recorded by an administrator.
    Sale::factory()->create(['visit_id' => null, 'created_by' => $admin->id, 'date' => '2026-10-20 12:00:00', 'total' => 80]);
    Payment::factory()->create(['visit_id' => null, 'received_by' => $admin->id, 'payment_method_id' => $cash->id, 'paid_at' => '2026-10-20 12:05:00', 'amount' => 50, 'status' => 'CONFIRMED']);
    Payment::factory()->create(['visit_id' => null, 'received_by' => $admin->id, 'payment_method_id' => $cash->id, 'paid_at' => '2026-10-20 12:06:00', 'amount' => 900, 'status' => 'CANCELLED']);

    $this->get(route('dashboard', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.kpis.totalSales', 1280)
            ->where('metrics.kpis.totalCollected', 50)
            ->where('metrics.repartidores.0.totalSales', 1200)
            ->where('metrics.repartidores.0.totalCollected', 0)
            ->where('metrics.casa.name', 'Casa')
            ->where('metrics.casa.totalSales', 80)
            ->where('metrics.casa.totalCollected', 50));
});

test('the containers card lists deliveries and returns, not the initial balances of new customers', function (): void {
    $bidon = ContainerType::factory()->create(['code' => 'BIDON_20L', 'description' => 'Bidón 20L']);
    ContainerMovement::factory()->create(['container_type_id' => $bidon->id, 'movement_type' => 'ADJUSTMENT', 'direction' => 1, 'quantity' => 3, 'visit_id' => null, 'created_at' => '2026-10-20 09:00:00']);
    $returned = ContainerMovement::factory()->create(['container_type_id' => $bidon->id, 'movement_type' => 'RETURNED', 'direction' => -1, 'quantity' => 2, 'visit_id' => null, 'created_at' => '2026-10-20 10:00:00']);

    $this->get('/dashboard?date=2026-10-20')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('metrics.details.containers', 1)
            ->where('metrics.details.containers.0.id', $returned->id));
});
