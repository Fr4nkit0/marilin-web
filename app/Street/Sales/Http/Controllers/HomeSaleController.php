<?php

declare(strict_types=1);

namespace App\Street\Sales\Http\Controllers;

use App\Catalog\Models\Product;
use App\Customers\Agreements\PriceResolver;
use App\Customers\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\MoveContainers;
use App\Street\Payments\RecordPayment;
use App\Street\Sales\Http\Requests\StoreHomeSaleRequest;
use App\Street\Sales\RecordSale;
use App\Street\Sales\SaleLine;
use App\Street\StreetOperation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Venta en casa: recorded by the administrator without visit or driver, so it discounts no route stock
 * and belongs to no cash close. Prices come from the server, never from the request.
 * Unless it is a credit sale it is paid on the spot: its payment for the full total is recorded with it.
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
            $total = number_format($totalCents / 100, 2, '.', '');
            $isCreditSale = $request->boolean('is_credit_sale');

            $sale = $operation->recordSale(new RecordSale(
                customer: $customer,
                visit: null,
                driver: null,
                date: $now,
                lines: $lines,
                total: $total,
                isCreditSale: $isCreditSale,
                notes: $request->validated('notes'),
                recordedBy: $admin,
            ));

            if (! $isCreditSale) {
                $operation->recordPayment(new RecordPayment(
                    customer: $customer,
                    visit: null,
                    sale: $sale,
                    paymentMethodId: (int) $request->validated('payment_method_id'),
                    amount: $total,
                    paidAt: $now,
                    receivedBy: $admin,
                ));
            }

            foreach ($request->validated('containers') ?? [] as $container) {
                $operation->moveContainers(new MoveContainers(
                    customer: $customer,
                    containerTypeId: (int) $container['container_type_id'],
                    type: ContainerMovementType::from($container['type']),
                    quantity: (int) $container['quantity'],
                    visit: null,
                    recordedBy: $admin,
                    sale: $sale,
                ));
            }
        });

        return redirect()->route('customers.show', $customer)->with('success', 'Venta en casa registrada.');
    }
}
