<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use App\Accounts\Models\User;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route;
use App\Street\RouteStock\Enums\LoadType;

/**
 * Releases a route to the app once its truck is loaded. Until then bootstrap and pull hide the route,
 * its stops and its inventory, so the driver never starts a day without the stock on the phone.
 */
final class RouteDispatch
{
    /**
     * @throws InvalidRouteDispatch
     */
    public function dispatch(Route $route, User $by): void
    {
        if (in_array($route->status, [RouteStatus::Completed->value, RouteStatus::Cancelled->value], true)) {
            throw new InvalidRouteDispatch('La ruta está cerrada.');
        }

        // A correction fixes a load written down short; it is not the truck being loaded.
        if ($route->inventoryLoads()->where('type', LoadType::Load->value)->doesntExist()) {
            throw new InvalidRouteDispatch('Registrá la carga inicial antes de despachar la ruta.');
        }

        // Guarded on the column itself, so two admins dispatching at once leave the first one recorded.
        $dispatched = Route::query()
            ->whereKey($route->id)
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now(), 'dispatched_by' => $by->id, 'updated_at' => now()]);

        if ($dispatched === 0) {
            throw new InvalidRouteDispatch('La ruta ya fue despachada.');
        }
    }
}
