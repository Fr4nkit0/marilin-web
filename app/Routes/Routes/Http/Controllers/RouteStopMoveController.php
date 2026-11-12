<?php

declare(strict_types=1);

namespace App\Routes\Routes\Http\Controllers;

use App\Accounts\Models\Driver;
use App\Http\Controllers\Controller;
use App\Routes\Routes\Http\Requests\MoveRouteStopsRequest;
use App\Routes\Routes\InvalidRouteStopMove;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Routes\Routes\MoveRouteStops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * "Cobertura": the admin moves part of an absent driver's route to another driver from the routes page.
 */
class RouteStopMoveController extends Controller
{
    public function store(MoveRouteStopsRequest $request, DeliveryRoute $route, MoveRouteStops $mover): RedirectResponse
    {
        $driver = Driver::query()->with('user')->findOrFail($request->integer('driver_id'));

        try {
            $mover->move($route, array_map('intval', $request->input('stop_ids')), $driver);
        } catch (InvalidRouteStopMove $invalid) {
            throw ValidationException::withMessages([$invalid->field => $invalid->getMessage()]);
        }

        return back()->with('success', 'Paradas movidas a la ruta de '.($driver->user?->name ?? 'el repartidor').'.');
    }
}
