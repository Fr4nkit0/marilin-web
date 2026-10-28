<?php

declare(strict_types=1);

namespace App\Customers\Agreements;

use App\Customers\Agreements\Models\CustomerAgreement;

final readonly class ResolvedPrice
{
    public function __construct(
        public string $unitPrice,
        public ?CustomerAgreement $agreement,
    ) {}
}
