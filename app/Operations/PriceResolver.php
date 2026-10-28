<?php

declare(strict_types=1);

namespace App\Operations;

use App\Catalog\Models\Product;
use App\Models\Customer;
use App\Models\CustomerAgreement;
use Carbon\CarbonInterface;

/**
 * The price a customer pays for a product on a given day: the agreement valid that day or, without one, the list price.
 */
class PriceResolver
{
    public function priceFor(Customer $customer, Product $product, CarbonInterface $date): ResolvedPrice
    {
        // With several valid agreements the most recent valid_from wins, as everywhere else in the app.
        $agreement = CustomerAgreement::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->active($date->toDateString())
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        return new ResolvedPrice((string) ($agreement?->unit_price ?? $product->price), $agreement);
    }
}
