<?php

declare(strict_types=1);

namespace App\Street\Sales;

use App\Customers\Agreements\Models\CustomerAgreement;

final readonly class SaleLine
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public string|int|float $unitPrice,
        public ?CustomerAgreement $agreement = null,
    ) {}
}
