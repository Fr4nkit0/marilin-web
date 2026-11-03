<?php

declare(strict_types=1);

namespace App\Routes\Routes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Routes\Routes\InvalidRouteDispatch;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Routes\Routes\RouteDispatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The admin releases a loaded route to the driver's app from the routes page.
 */
class RouteDispatchController extends Controller
{
    public function store(Request $request, DeliveryRoute $route, RouteDispatch $dispatch): RedirectResponse
    {
        try {
            $dispatch->dispatch($route, $request->user());
        } catch (InvalidRouteDispatch $invalid) {
            throw ValidationException::withMessages(['route' => $invalid->getMessage()]);
        }

        return back()->with('success', 'Ruta despachada.');
    }
}
