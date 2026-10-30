<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Street\Sales\CancelSale;
use App\Street\Sales\Enums\SaleStatus;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends CancellationPusher<Sale>
 */
class SaleCancellationPusher extends CancellationPusher
{
    public function __construct(private readonly StreetOperation $operation) {}

    protected function model(): string
    {
        return Sale::class;
    }

    protected function name(): string
    {
        return 'sale';
    }

    protected function recordedByColumn(): string
    {
        return 'created_by';
    }

    protected function isCancelled(Model $record): bool
    {
        return $record->status === SaleStatus::Cancelled->value;
    }

    protected function occurredAt(Model $record): CarbonInterface
    {
        return $record->date;
    }

    protected function cancel(Model $record, Driver $driver, User $user, ?string $reason, CarbonInterface $cancelledAt): Model
    {
        return $this->operation->cancelSale(new CancelSale(
            sale: $record,
            cancelledBy: $user,
            reason: $reason,
            reviewReason: $this->reviewReason(),
            cancelledAt: $cancelledAt,
        ));
    }
}
