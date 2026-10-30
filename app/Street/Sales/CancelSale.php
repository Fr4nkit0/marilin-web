<?php

declare(strict_types=1);

namespace App\Street\Sales;

use App\Accounts\Models\User;
use App\Review\ReviewReason;
use App\Street\Sales\Models\Sale;
use Carbon\CarbonInterface;

/**
 * A sale to cancel. Its stock goes back to the route it was discounted from (the sale stores it); a sale that discounted
 * from none gives nothing back. The containers moved with it are given back dated when it was cancelled (now when not given).
 */
final readonly class CancelSale
{
    public function __construct(
        public Sale $sale,
        public User $cancelledBy,
        public ?string $reason = null,
        public ?ReviewReason $reviewReason = null,
        public ?CarbonInterface $cancelledAt = null,
    ) {}
}
