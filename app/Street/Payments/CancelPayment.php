<?php

declare(strict_types=1);

namespace App\Street\Payments;

use App\Accounts\Models\User;
use App\Review\ReviewReason;
use App\Street\Payments\Models\Payment;

final readonly class CancelPayment
{
    public function __construct(
        public Payment $payment,
        public User $cancelledBy,
        public ?string $reason = null,
        public ?ReviewReason $reviewReason = null,
    ) {}
}
