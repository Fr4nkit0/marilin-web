<?php

declare(strict_types=1);

namespace App\Operations;

use App\Models\CustomerAgreement;

final readonly class ResolvedPrice
{
    public function __construct(
        public string $unitPrice,
        public ?CustomerAgreement $agreement,
    ) {}
}
