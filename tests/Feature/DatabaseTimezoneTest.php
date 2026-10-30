<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Routes\Routes\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// The columns are tz-less, so the database default only reads right when the session speaks the app's zone.
it('stamps a load left without a time with the current time of the app zone', function (): void {
    $id = DB::table('route_inventory_loads')->insertGetId([
        'route_id' => Route::factory()->create()->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 10,
        'created_by' => User::factory()->create()->id,
    ]);

    $loadedAt = Carbon::parse(DB::table('route_inventory_loads')->where('id', $id)->value('loaded_at'));

    expect(abs($loadedAt->diffInSeconds(Carbon::now())))->toBeLessThan(60);
});
