<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Catalog\Models\Product;
use App\Customers\Agreements\PriceResolver;
use App\Customers\Customers\Models\Customer;
use App\Enums\ContainerMovementType;
use App\Http\Requests\StoreHomeSaleRequest;
use App\Operations\MoveContainers;
use App\Operations\RecordSale;
use App\Operations\SaleLine;
use App\Operations\StreetOperation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Venta en casa: recorded by the administrator without visit or driver, so it discounts no route stock
 * and belongs to no cash close. Prices come from the server, never from the request.
 */
class HomeSaleController extends Controller
{
    public function store(StoreHomeSaleRequest $request, Customer $customer, StreetOperation $operation, PriceResolver $prices): RedirectResponse
    {
        $admin = $request->user();
        $now = now();
        $products = Product::query()->whereIn('id', array_column($request->validated('lines'), 'product_id'))->get()->keyBy('id');

        $lines = [];
        $totalCents = 0;

        foreach ($request->validated('lines') as $line) {
            $resolved = $prices->priceFor($customer, $products[(int) $line['product_id']], $now);
            $quantity = (int) $line['quantity'];
            $lines[] = new SaleLine((int) $line['product_id'], $quantity, $resolved->unitPrice, $resolved->agreement);
            $totalCents += (int) round((float) $resolved->unitPrice * 100) * $quantity;
        }

        DB::transaction(function () use ($request, $customer, $operation, $admin, $now, $lines, $totalCents): void {
            $operation->recordSale(new RecordSale(
                customer: $customer,
                visit: null,
                driver: null,
                date: $now,
                lines: $lines,
                total: number_format($totalCents / 100, 2, '.', ''),
                isCreditSale: $request->boolean('is_credit_sale'),
                notes: $request->validated('notes'),
                recordedBy: $admin,
            ));

            foreach ($request->validated('containers') ?? [] as $container) {
                $operation->moveContainers(new MoveContainers(
                    customer: $customer,
                    containerTypeId: (int) $container['container_type_id'],
                    type: ContainerMovementType::from($container['type']),
                    quantity: (int) $container['quantity'],
                    visit: null,
                    recordedBy: $admin,
                ));
            }
        });

        return redirect()->route('customers.show', $customer)->with('success', 'Venta en casa registrada.');
    }
}
