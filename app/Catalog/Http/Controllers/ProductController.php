<?php

declare(strict_types=1);

namespace App\Catalog\Http\Controllers;

use App\Catalog\Http\Requests\ProductPriceRequest;
use App\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Products/Index', [
            'products' => Product::query()
                ->orderByDesc('enabled')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'price', 'enabled']),
        ]);
    }

    public function updatePrice(ProductPriceRequest $request, Product $product): RedirectResponse
    {
        $product->update(['price' => $request->validated('price')]);

        return back()->with('success', 'Precio actualizado');
    }
}
