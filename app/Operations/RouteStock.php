<?php

declare(strict_types=1);

namespace App\Operations;

use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\RouteInventoryLoad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place that does route inventory arithmetic. Every write walks the products in ascending id order,
 * so a sale and a truck load touching the same products never lock rows in opposite orders.
 *
 * @internal Used through StreetOperation.
 */
class RouteStock
{
    /**
     * @param  array<int, int|float>  $quantities  quantity keyed by product id
     */
    public function discount(int $routeId, array $quantities): void
    {
        ksort($quantities);

        foreach ($quantities as $productId => $quantity) {
            RouteInventory::query()
                ->where('route_id', $routeId)
                ->where('product_id', $productId)
                ->decrement('current_quantity', (float) $quantity);
        }
    }

    /**
     * Records one load of the truck (history row) and adds it to the route stock.
     *
     * @param  array<int, int>  $quantities  quantity keyed by product id
     */
    public function load(Route $route, array $quantities, User $loadedBy): void
    {
        DB::transaction(function () use ($route, $quantities, $loadedBy): void {
            $loadedAt = now();
            ksort($quantities);

            foreach ($quantities as $productId => $quantity) {
                RouteInventoryLoad::query()->create([
                    'route_id' => $route->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'loaded_at' => $loadedAt,
                    'created_by' => $loadedBy->id,
                ]);

                $inventory = RouteInventory::query()->firstOrCreate(
                    ['route_id' => $route->id, 'product_id' => $productId],
                    ['current_quantity' => 0, 'created_by' => $loadedBy->id, 'enabled' => true],
                );

                // Atomic, so a sale pushed by the app at the same moment is not lost.
                $inventory->newQuery()->whereKey($inventory->id)->update([
                    'current_quantity' => DB::raw('current_quantity + '.(int) $quantity),
                    'last_modified_by' => $loadedBy->id,
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
