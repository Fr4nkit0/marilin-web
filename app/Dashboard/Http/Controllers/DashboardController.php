<?php

namespace App\Dashboard\Http\Controllers;

use App\Dashboard\GetDailyDashboardMetricsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetDailyDashboardMetricsAction $action): Response
    {
        $date = $request->query('date');
        $metrics = $action->execute($date);

        return Inertia::render('Dashboard/Index', [
            'metrics' => $metrics,
        ]);
    }
}
