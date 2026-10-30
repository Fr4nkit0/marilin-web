<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\CashDesk\DayScope;
use App\Review\ReviewReason;
use App\Shared\ClientTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The driver cancels a sale or payment of their own, offline on the record's day: it is reversed and left for the admin's review (ADR 0001).
 * The response's server id is the cancelled record's; the cancellation's own uuid is not stored, so a replay is
 * recognized by the record being cancelled already, whatever uuid and day it comes with.
 *
 * @template TModel of Model
 */
abstract class CancellationPusher extends EntityPusher
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * The reference prefix (`sale` sends `sale_uuid` or `sale_id`) and the name in the error messages.
     */
    abstract protected function name(): string;

    /**
     * The user column that owns a record without visit, as the cash close counts it.
     */
    abstract protected function recordedByColumn(): string;

    /**
     * @param  TModel  $record
     */
    abstract protected function isCancelled(Model $record): bool;

    /**
     * @param  TModel  $record
     */
    abstract protected function occurredAt(Model $record): CarbonInterface;

    /**
     * @param  TModel  $record
     * @return TModel
     */
    abstract protected function cancel(Model $record, Driver $driver, User $user, ?string $reason, CarbonInterface $cancelledAt): Model;

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules($this->name(), required: true),
            'cancelled_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        /** @var TModel $target */
        $target = $this->reference($this->model(), $record, $this->name());

        if (! $this->belongsTo($target, $driver)) {
            throw new RejectedRecord("The {$this->name()} belongs to another driver.");
        }

        // Before the date, so a replay arriving the next day is still answered as accepted.
        if ($this->isCancelled($target)) {
            return $this->accepted($record['uuid'], $target->getKey(), $this->pendingReason($target));
        }

        $cancelledAt = ClientTime::parse($record['cancelled_at'])->min(now());

        $this->assertActedOnRecordDay(
            $this->occurredAt($target),
            $cancelledAt,
            "The cancellation was not made on the record's day.",
            'The cancellation is more than %d days old.',
        );

        $cancelled = $this->cancel($target, $driver, $user, $record['reason'] ?? null, $cancelledAt);

        return $this->accepted($record['uuid'], $cancelled->getKey(), $this->pendingReason($cancelled));
    }

    protected function reviewReason(): ReviewReason
    {
        return ReviewReason::DriverCancellation;
    }

    /**
     * The cash close's ownership: made on one of the driver's visits or, without visit, recorded by the driver's user.
     */
    private function belongsTo(Model $target, Driver $driver): bool
    {
        return $target->newQuery()
            ->whereKey($target->getKey())
            ->tap(fn ($query) => DayScope::driver($driver)->apply($query, $this->recordedByColumn()))
            ->exists();
    }
}
