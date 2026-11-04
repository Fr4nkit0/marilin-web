<?php

declare(strict_types=1);

namespace App\Customers\Neighborhoods\Http\Controllers;

use App\Customers\Neighborhoods\Http\Requests\NeighborhoodStatusRequest;
use App\Customers\Neighborhoods\Http\Requests\StoreNeighborhoodRequest;
use App\Customers\Neighborhoods\Http\Requests\UpdateNeighborhoodRequest;
use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Customers\Neighborhoods\NeighborhoodCatalog;
use App\Http\Controllers\Controller;
use App\Shared\TextMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The neighborhood catalog the app offers for an address, managed from the settings section.
 */
class NeighborhoodController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $neighborhoods = Neighborhood::query()
            ->tap(fn ($query) => TextMatch::search($query, ['name'], $search))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25, ['id', 'name', 'enabled'])
            ->withQueryString()
            ->through(fn (Neighborhood $neighborhood): array => [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'enabled' => $neighborhood->enabled,
            ]);

        return Inertia::render('Customers/Neighborhoods/Index', [
            'neighborhoods' => $neighborhoods,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreNeighborhoodRequest $request, NeighborhoodCatalog $catalog): RedirectResponse
    {
        $catalog->add($request->validated('name'));

        return back()->with('success', 'Barrio creado');
    }

    public function update(UpdateNeighborhoodRequest $request, Neighborhood $neighborhood, NeighborhoodCatalog $catalog): RedirectResponse
    {
        $catalog->rename($neighborhood, $request->validated('name'), $request->user());

        return back()->with('success', 'Barrio actualizado');
    }

    public function status(NeighborhoodStatusRequest $request, Neighborhood $neighborhood, NeighborhoodCatalog $catalog): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $catalog->setEnabled($neighborhood, $enabled);

        return back()->with('success', $enabled ? 'Barrio habilitado' : 'Barrio deshabilitado');
    }
}
