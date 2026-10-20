<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Route as DeliveryRoute;
use App\Models\RouteInventory;
use App\Models\RouteInventoryLoad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterTruckLoadAction
{
    /**
     * Records one load of the truck (the first of the day or a reload) and adds it to the truck stock,
     * which reaches the driver's app on its next pull.
     *
     * @param  array<int, int>  $quantities  quantity keyed by product id
     */
    public function execute(DeliveryRoute $route, array $quantities, User $loadedBy): void
    {
        DB::transaction(function () use ($route, $quantities, $loadedBy): void {
            $loadedAt = now();
            // Same order as the sale push, so a load and a sale touching the same products never deadlock.
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
