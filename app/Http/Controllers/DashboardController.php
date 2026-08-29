<?php

namespace App\Http\Controllers;

use App\Actions\GetDailyDashboardMetricsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetDailyDashboardMetricsAction $action): Response
    {
        $date = $request->query('date');
        $metrics = $action->execute($date);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
        ]);
    }
}
