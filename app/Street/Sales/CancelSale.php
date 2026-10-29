<?php

declare(strict_types=1);

namespace App\Street\Sales;

use App\Accounts\Models\User;
use App\Review\ReviewReason;
use App\Street\Sales\Models\Sale;

/**
 * A sale to cancel. Its stock goes back to the route it was discounted from (the sale stores it); a sale that discounted
 * from none gives nothing back.
 */
final readonly class CancelSale
{
    public function __construct(
        public Sale $sale,
        public User $cancelledBy,
        public ?string $reason = null,
        public ?ReviewReason $reviewReason = null,
    ) {}
}
