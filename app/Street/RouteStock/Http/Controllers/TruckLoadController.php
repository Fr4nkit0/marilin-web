<?php

declare(strict_types=1);

namespace App\Street\RouteStock\Http\Controllers;

use App\Accounts\Models\User;
use App\Http\Controllers\Controller;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Street\RouteStock\Http\Requests\TruckLoadRequest;
use App\Street\StreetOperation;
use Illuminate\Http\RedirectResponse;

class TruckLoadController extends Controller
{
    public function store(TruckLoadRequest $request, DeliveryRoute $route, StreetOperation $operation): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $operation->loadRoute($route, $request->quantities(), $admin);

        return back()->with('success', 'Carga registrada');
    }
}
