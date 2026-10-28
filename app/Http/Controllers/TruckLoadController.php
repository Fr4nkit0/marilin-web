<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Accounts\Models\User;
use App\Actions\RegisterTruckLoadAction;
use App\Http\Requests\TruckLoadRequest;
use App\Models\Route as DeliveryRoute;
use Illuminate\Http\RedirectResponse;

class TruckLoadController extends Controller
{
    public function store(TruckLoadRequest $request, DeliveryRoute $route, RegisterTruckLoadAction $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $action->execute($route, $request->quantities(), $admin);

        return back()->with('success', 'Carga registrada');
    }
}
