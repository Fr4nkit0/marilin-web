<?php

declare(strict_types=1);

namespace App\Operations;

use App\Accounts\Models\User;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Visit;
use App\Review\ReviewReason;
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
        public ?ReviewReason $reviewReason = null,
    ) {}
}
