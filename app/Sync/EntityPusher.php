<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Review\Reviewable;
use App\Review\ReviewReason;
use App\Shared\ClientTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * One entity type of the push: every record is validated and processed on its own transaction,
 * so an invalid record is rejected without affecting the rest of the batch.
 */
abstract class EntityPusher
{
    /**
     * @return array<string, array<int, string>>
     */
    abstract protected function rules(): array;

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    abstract protected function handle(Driver $driver, User $user, array $record): array;

    /**
     * The server row this record was found to point at (see findPushed), kept to answer a conflict with it.
     */
    private ?Model $found = null;

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    final public function push(Driver $driver, User $user, array $record): array
    {
        $this->found = null;

        return $this->withCurrent($this->process($driver, $user, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function process(Driver $driver, User $user, array $record): array
    {
        $record = $this->normalize($record);
        $uuid = is_string($record['uuid'] ?? null) ? $record['uuid'] : null;
        $validator = Validator::make($record, $this->rules());

        if ($validator->fails()) {
            return $this->rejected($uuid, $validator->errors()->first());
        }

        // Twice at most: a concurrent push may have created the very same record between our lookup and our insert,
        // and on the second pass the idempotent replay path finds it and answers with its server id.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->found = null;

            try {
                return DB::transaction(fn (): array => $this->handle($driver, $user, $record));
            } catch (RejectedRecord $rejection) {
                return $this->rejected($uuid, $rejection->getMessage());
            } catch (UniqueConstraintViolationException $violation) {
                if ($attempt === 2) {
                    report($violation);
                }
            } catch (QueryException $failure) {
                report($failure);

                return $this->rejected($uuid, 'The record could not be saved.');
            }
        }

        return $this->rejected($uuid, 'The record could not be saved.');
    }

    /**
     * The server row in the pull's shape, answered with an outdated or rejected record so the app can show and
     * keep what the server holds (S8); null when the entity does not answer conflicts with it.
     *
     * @return array<string, mixed>|null
     */
    protected function current(Model $row): ?array
    {
        return null;
    }

    /**
     * Adds `current` to an outdated or rejected result whose record pointed at an existing server row, read after
     * the record's transaction rolled back. A rejected create has no row of its own on the server, so it gets none.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withCurrent(array $result): array
    {
        $row = in_array($result['status'], ['outdated', 'rejected'], true) ? $this->found?->fresh() : null;
        $current = $row === null ? null : $this->current($row);

        return $current === null ? $result : [...$result, 'current' => $current];
    }

    /**
     * Adjusts what older app builds still send before it is validated.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function normalize(array $record): array
    {
        return $record;
    }

    /**
     * The app's modification date of a record; the app's clock can run ahead of the server's and a future date would shadow every later edit.
     *
     * @param  array<string, mixed>  $record
     */
    protected function modifiedAt(array $record): Carbon
    {
        return ClientTime::parse($record['modified_at'])->min(now());
    }

    /**
     * An offline cancellation or edit is judged by when the driver acted, not by when the push arrives: it must be made
     * on the record's local day and be at most `sync.offline_action_max_age_days` old.
     */
    protected function assertActedOnRecordDay(CarbonInterface $recordAt, CarbonInterface $actedAt, string $notOnDay, string $tooOld): void
    {
        if ($recordAt->toDateString() !== $actedAt->toDateString()) {
            throw new RejectedRecord($notOnDay);
        }

        $maxAgeDays = (int) config('sync.offline_action_max_age_days');

        if ($actedAt->lessThan(now()->subDays($maxAgeDays))) {
            throw new RejectedRecord(sprintf($tooOld, $maxAgeDays));
        }
    }

    /**
     * @param  list<string>  $reviewReasons  every reason the record waits for review, empty when it is clean
     * @return array<string, mixed>
     */
    protected function accepted(string $uuid, int $serverId, array $reviewReasons = []): array
    {
        return [
            'uuid' => $uuid,
            'status' => 'accepted',
            'server_id' => $serverId,
            'review_reasons' => $reviewReasons,
        ];
    }

    /**
     * An edit dropped as a whole: every field it carried was older than the server's (APP-26). Answered like an
     * accepted record, so the app learns the server id, but with its own status so it can tell the driver.
     *
     * @param  list<string>  $reviewReasons
     * @return array<string, mixed>
     */
    protected function outdated(string $uuid, int $serverId, array $reviewReasons = []): array
    {
        return [...$this->accepted($uuid, $serverId, $reviewReasons), 'status' => 'outdated'];
    }

    /**
     * Rules that only apply on one path of the record (e.g. a create needs fields an edit may leave out).
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, array<int, string>>  $rules
     */
    protected function assertValid(array $record, array $rules): void
    {
        $validator = Validator::make($record, $rules);

        if ($validator->fails()) {
            throw new RejectedRecord($validator->errors()->first());
        }
    }

    /**
     * Idempotency: a record the server already holds is answered again with the same server id and, if it still waits for review, its reasons.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    protected function alreadyPushed(string $model, array $record): ?array
    {
        $existing = $model::query()->where('client_uuid', $record['uuid'])->first();

        return $existing === null
            ? null
            : $this->accepted($record['uuid'], $existing->getKey(), $this->pendingReasons($existing));
    }

    /**
     * The reasons a record is waiting for review, empty when it is not (or the admin already reviewed it).
     *
     * @return list<string>
     */
    protected function pendingReasons(Model $record): array
    {
        /** @var Reviewable&Model $record */
        return $record->pendingReviewReasons();
    }

    /**
     * The columns that mark a new record for review, or leave it alone when there is no reason.
     *
     * @return array{needs_review: bool, review_reasons: list<string>}
     */
    protected function reviewAttributes(?ReviewReason ...$reasons): array
    {
        return ReviewReason::columns(...$reasons);
    }

    /**
     * Rules of a reference sent as `<name>_uuid` (the app's id) or `<name>_id` (the server's id).
     *
     * @return array<string, array<int, string>>
     */
    protected function referenceRules(string $name, bool $required = false): array
    {
        return [
            "{$name}_id" => ['nullable', 'integer', ...($required ? ["required_without:{$name}_uuid"] : [])],
            "{$name}_uuid" => ['nullable', 'uuid', ...($required ? ["required_without:{$name}_id"] : [])],
        ];
    }

    /**
     * Accept-and-mark (ADR 0001): what happened on the street is kept, but a customer that was reassigned or disabled meanwhile needs the admin's eyes.
     */
    protected function reviewReasonForCustomer(Customer $customer, Driver $driver): ?ReviewReason
    {
        return match (true) {
            $customer->driver_id !== $driver->id => ReviewReason::ForeignCustomer,
            ! $customer->enabled => ReviewReason::DisabledCustomer,
            default => null,
        };
    }

    /**
     * The server row an edit of the app points at: by the app's uuid, then by the server id the app learned in a pull.
     * Returns null for a new record; rejects a server id that points at nothing or at a row another app record owns
     * (a row without an app id, e.g. created in the web, is adopted by its first app edit). The row it finds, even
     * one it rejects, is what an outdated or rejected result answers as `current`.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $record
     * @return TModel|null
     */
    protected function findPushed(string $model, array $record, string $name): ?Model
    {
        $found = $model::query()->where('client_uuid', $record['uuid'])->first();

        if ($found === null && isset($record['server_id'])) {
            $found = $model::query()->find($record['server_id']) ?? throw new RejectedRecord("The server_id does not match any {$name}.");
            $this->found = $found;

            if ($found->getAttribute('client_uuid') !== null) {
                throw new RejectedRecord('The server_id belongs to another app record.');
            }
        }

        return $this->found = $found;
    }

    /**
     * Accept-and-mark (ADR 0001): a record on another driver's customer is applied and left for review, even a stale one.
     */
    protected function flagForReviewIfForeign(Customer $customer, Driver $driver): void
    {
        if ($customer->driver_id === $driver->id || ! $customer->canBeFlagged()) {
            return;
        }

        $customer->timestamps = false;
        $customer->flag(ReviewReason::ForeignCustomer);
    }

    /**
     * @return array<string, mixed>
     */
    private function rejected(?string $uuid, string $error): array
    {
        return [
            'uuid' => $uuid,
            'status' => 'rejected',
            'server_id' => null,
            'review_reasons' => [],
            'error' => $error,
        ];
    }

    /**
     * A payment or sale may only point at records of the same customer; anything else would book money on the wrong account.
     */
    protected function assertBelongsToCustomer(?Model $related, Customer $customer, string $name): void
    {
        if ($related !== null && $related->getAttribute('customer_id') !== $customer->id) {
            throw new RejectedRecord("The {$name} belongs to another customer.");
        }
    }

    /**
     * Resolves a reference sent as `<prefix>_uuid` (the app's id) or `<prefix>_id` (the server's id).
     * Returns null when the record carries no such reference; rejects the record when it points at nothing.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $record
     * @return TModel|null
     */
    protected function reference(string $model, array $record, string $prefix): ?Model
    {
        $found = null;
        $sent = false;

        if (isset($record["{$prefix}_uuid"])) {
            $sent = true;
            $found = $model::query()->where('client_uuid', $record["{$prefix}_uuid"])->first();
        }

        if ($found === null && isset($record["{$prefix}_id"])) {
            $sent = true;
            $found = $model::query()->find($record["{$prefix}_id"]);
        }

        if ($sent && $found === null) {
            throw new RejectedRecord("The {$prefix} reference does not match any record.");
        }

        return $found;
    }
}
