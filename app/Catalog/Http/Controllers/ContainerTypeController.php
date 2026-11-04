<?php

declare(strict_types=1);

namespace App\Catalog\Http\Controllers;

use App\Catalog\ContainerTypeCatalog;
use App\Catalog\Http\Requests\ContainerTypeStatusRequest;
use App\Catalog\Http\Requests\StoreContainerTypeRequest;
use App\Catalog\Http\Requests\UpdateContainerTypeRequest;
use App\Catalog\Models\ContainerType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The container types customers hold a balance of, managed from the settings section.
 */
class ContainerTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Catalog/ContainerTypes', [
            'containerTypes' => ContainerType::query()
                ->orderByDesc('enabled')
                ->orderBy('description')
                ->orderBy('id')
                ->get(['id', 'code', 'description', 'enabled'])
                ->map(fn (ContainerType $type): array => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'description' => $type->description,
                    'enabled' => $type->enabled,
                ]),
        ]);
    }

    public function store(StoreContainerTypeRequest $request, ContainerTypeCatalog $catalog): RedirectResponse
    {
        $catalog->add($request->validated('description'));

        return back()->with('success', 'Tipo de envase creado');
    }

    public function update(UpdateContainerTypeRequest $request, ContainerType $containerType, ContainerTypeCatalog $catalog): RedirectResponse
    {
        $catalog->describe($containerType, $request->validated('description'));

        return back()->with('success', 'Tipo de envase actualizado');
    }

    public function status(ContainerTypeStatusRequest $request, ContainerType $containerType, ContainerTypeCatalog $catalog): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $catalog->setEnabled($containerType, $enabled);

        return back()->with('success', $enabled ? 'Tipo de envase habilitado' : 'Tipo de envase deshabilitado');
    }
}
