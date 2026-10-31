<?php

declare(strict_types=1);

namespace App\Street\Payments;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Visits\Models\Visit;
use App\Street\Sales\Models\Sale;
use Carbon\CarbonInterface;

final readonly class RecordPayment
{
    public function __construct(
        public Customer $customer,
        public ?Visit $visit,
        public ?Sale $sale,
        public int $paymentMethodId,
        public string|int|float $amount,
        public CarbonInterface $paidAt,
        public User $receivedBy,
        public ?string $clientUuid = null,
        /** @var list<ReviewReason|null> why the record is left for the admin's review; none (or only nulls) leaves it clean */
        public array $reviewReasons = [],
    ) {}
}
