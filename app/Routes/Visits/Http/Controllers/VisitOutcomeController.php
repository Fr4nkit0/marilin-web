<?php

declare(strict_types=1);

namespace App\Routes\Visits\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Routes\Visits\Http\Requests\StoreVisitOutcomeRequest;
use App\Routes\Visits\Http\Requests\UpdateVisitOutcomeRequest;
use App\Routes\Visits\Http\Requests\VisitOutcomeStatusRequest;
use App\Routes\Visits\Models\VisitOutcome;
use App\Routes\Visits\VisitOutcomeCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The visit outcomes the driver picks from at a stop, managed from the settings section.
 */
class VisitOutcomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Routes/Visits/Outcomes', [
            'outcomes' => VisitOutcome::query()
                ->orderByDesc('enabled')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'triggers_reschedule', 'enabled'])
                ->map(fn (VisitOutcome $outcome): array => [
                    'id' => $outcome->id,
                    'code' => $outcome->code,
                    'name' => $outcome->name,
                    'triggers_reschedule' => $outcome->triggers_reschedule,
                    'enabled' => $outcome->enabled,
                ]),
        ]);
    }

    public function store(StoreVisitOutcomeRequest $request, VisitOutcomeCatalog $catalog): RedirectResponse
    {
        $catalog->add($request->validated('name'), $request->boolean('triggers_reschedule'));

        return back()->with('success', 'Resultado de visita creado');
    }

    public function update(UpdateVisitOutcomeRequest $request, VisitOutcome $visitOutcome, VisitOutcomeCatalog $catalog): RedirectResponse
    {
        $catalog->edit($visitOutcome, $request->validated('name'), $request->boolean('triggers_reschedule'));

        return back()->with('success', 'Resultado de visita actualizado');
    }

    public function status(VisitOutcomeStatusRequest $request, VisitOutcome $visitOutcome, VisitOutcomeCatalog $catalog): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $catalog->setEnabled($visitOutcome, $enabled);

        return back()->with('success', $enabled ? 'Resultado de visita habilitado' : 'Resultado de visita deshabilitado');
    }
}
