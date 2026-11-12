<?php

declare(strict_types=1);

namespace App\Routes\Routes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Routes\Routes\RouteSplit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Repartir ruta": the admin splits an absent driver's route by neighborhood before dispatch, assigning each
 * neighborhood (or some of its stops) through the stops move and staying on the page until the route is split.
 */
class RouteSplitController extends Controller
{
    public function show(DeliveryRoute $route, RouteSplit $split): Response|RedirectResponse
    {
        $refusal = match (true) {
            $route->dispatched_at !== null => 'La ruta ya fue despachada: ya no se puede repartir.',
            $route->status !== RouteStatus::Planned->value => 'Solo se puede repartir una ruta planificada.',
            default => null,
        };

        if ($refusal !== null) {
            return redirect()
                ->route('routes.index', ['date' => $route->date->toDateString()])
                ->withErrors(['split' => $refusal]);
        }

        return Inertia::render('Routes/Routes/Split', $split->of($route));
    }
}
