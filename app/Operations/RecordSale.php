<?php

declare(strict_types=1);

namespace App\Operations;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Models\Customer;
use App\Models\Visit;
use App\Review\ReviewReason;
use Carbon\CarbonInterface;

/**
 * A sale to record. Route stock is discounted from the route of the visit or, without visit, from the route
 * the driver has on the sale's day; with neither a visit nor a driver (a sale at home) no stock is discounted.
 */
final readonly class RecordSale
{
    /**
     * @param  list<SaleLine>  $lines
     */
    public function __construct(
        public Customer $customer,
        public ?Visit $visit,
        public ?Driver $driver,
        public CarbonInterface $date,
        public array $lines,
        public string|int|float $total,
        public bool $isCreditSale,
        public ?string $notes,
        public User $recordedBy,
        public ?string $clientUuid = null,
        public ?ReviewReason $reviewReason = null,
    ) {}
}
