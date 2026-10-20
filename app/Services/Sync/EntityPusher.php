<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    final public function push(Driver $driver, User $user, array $record): array
    {
        $uuid = is_string($record['uuid'] ?? null) ? $record['uuid'] : null;
        $validator = Validator::make($record, $this->rules());

        if ($validator->fails()) {
            return $this->rejected($uuid, $validator->errors()->first());
        }

        try {
            return DB::transaction(fn (): array => $this->handle($driver, $user, $record));
        } catch (RejectedRecord $rejection) {
            return $this->rejected($uuid, $rejection->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function accepted(string $uuid, int $serverId, ?string $reviewReason = null): array
    {
        return [
            'uuid' => $uuid,
            'status' => 'accepted',
            'server_id' => $serverId,
            'review_reason' => $reviewReason,
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
     * @return array<string, mixed>
     */
    private function rejected(?string $uuid, string $error): array
    {
        return [
            'uuid' => $uuid,
            'status' => 'rejected',
            'server_id' => null,
            'review_reason' => null,
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
