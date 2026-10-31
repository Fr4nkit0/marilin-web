<?php

declare(strict_types=1);

namespace App\Street\Payments;

use App\Accounts\Models\User;
use App\Review\ReviewReason;
use App\Street\Payments\Models\Payment;
use Carbon\CarbonInterface;

/**
 * A payment to cancel. Who, when (now when not given) and why are stored on the payment; the driver cancels from the app, the admin from the web.
 */
final readonly class CancelPayment
{
    public function __construct(
        public Payment $payment,
        public User $cancelledBy,
        public ?string $reason = null,
        public ?ReviewReason $reviewReason = null,
        public ?CarbonInterface $cancelledAt = null,
        public bool $byAdmin = false,
    ) {}
}
