<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Street\Payments\CancelPayment;
use App\Street\Payments\Enums\PaymentStatus;
use App\Street\Payments\Models\Payment;
use App\Street\StreetOperation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends CancellationPusher<Payment>
 */
class PaymentCancellationPusher extends CancellationPusher
{
    public function __construct(private readonly StreetOperation $operation) {}

    protected function model(): string
    {
        return Payment::class;
    }

    protected function name(): string
    {
        return 'payment';
    }

    protected function recordedByColumn(): string
    {
        return 'received_by';
    }

    protected function isCancelled(Model $record): bool
    {
        return $record->status === PaymentStatus::Cancelled->value;
    }

    protected function occurredAt(Model $record): CarbonInterface
    {
        return $record->paid_at;
    }

    protected function cancel(Model $record, Driver $driver, User $user, ?string $reason, CarbonInterface $cancelledAt): Model
    {
        return $this->operation->cancelPayment(new CancelPayment(
            payment: $record,
            cancelledBy: $user,
            reason: $reason,
            reviewReason: $this->reviewReason(),
            cancelledAt: $cancelledAt,
        ));
    }
}
