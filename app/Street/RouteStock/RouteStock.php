<?php

declare(strict_types=1);

namespace App\Street\RouteStock;

use App\Accounts\Models\User;
use App\Routes\Routes\Models\Route;
use App\Street\RouteStock\Enums\LoadType;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;
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
     * Takes a sale off the route stock. A product the truck does not carry gets its row at zero first,
     * so the discount always lands (stock may go below zero) and a cancellation has something to give back to.
     *
     * @param  array<int, int|float>  $quantities  quantity keyed by product id
     * @return list<int> the products the discount left below zero on the truck
     */
    public function discount(int $routeId, array $quantities, User $recordedBy): array
    {
        ksort($quantities);

        foreach ($quantities as $productId => $quantity) {
            RouteInventory::query()->insertOrIgnore([
                'route_id' => $routeId,
                'product_id' => $productId,
                'current_quantity' => 0,
                'created_by' => $recordedBy->id,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            RouteInventory::query()
                ->where('route_id', $routeId)
                ->where('product_id', $productId)
                ->decrement('current_quantity', (float) $quantity);
        }

        // Read inside the same transaction, on rows this discount already locked.
        return RouteInventory::query()
            ->where('route_id', $routeId)
            ->whereIn('product_id', array_keys($quantities))
            ->where('current_quantity', '<', 0)
            ->orderBy('product_id')
            ->pluck('product_id')
            ->all();
    }

    /**
     * Gives back what a cancelled sale had discounted.
     *
     * @param  array<int, int|float>  $quantities  quantity keyed by product id
     */
    public function restore(int $routeId, array $quantities): void
    {
        ksort($quantities);

        foreach ($quantities as $productId => $quantity) {
            RouteInventory::query()
                ->where('route_id', $routeId)
                ->where('product_id', $productId)
                ->increment('current_quantity', (float) $quantity);
        }
    }

    /**
     * Records one load of the truck (history row) and adds it to the route stock; a correction keeps the reason it was made for.
     *
     * @param  array<int, int>  $quantities  quantity keyed by product id
     */
    public function load(Route $route, array $quantities, User $loadedBy, LoadType $type = LoadType::Load, ?string $reason = null): void
    {
        DB::transaction(function () use ($route, $quantities, $loadedBy, $type, $reason): void {
            $loadedAt = now();
            ksort($quantities);

            foreach ($quantities as $productId => $quantity) {
                RouteInventoryLoad::query()->create([
                    'route_id' => $route->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'type' => $type,
                    'reason' => $reason,
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
