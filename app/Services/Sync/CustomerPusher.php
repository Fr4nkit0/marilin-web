<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Carbon;

class CustomerPusher extends EntityPusher
{
    protected function handle(Driver $driver, User $user, array $record): array
    {
        $customer = $this->find($record);

        if ($customer === null && isset($record['server_id'])) {
            throw new RejectedRecord('The server_id does not match any customer.');
        }

        if ($customer === null) {
            $customer = $this->create($driver, $user, $record);
        } else {
            $this->flagForReviewIfForeign($customer, $driver);
            $this->edit($customer, $user, $record);
        }

        return $this->accepted($record['uuid'], $customer->id, $customer->needs_review ? $customer->review_reason : null);
    }

    /**
     * Accept-and-mark (ADR 0001): a record on another driver's customer is applied and left for review, even a stale one.
     */
    private function flagForReviewIfForeign(Customer $customer, Driver $driver): void
    {
        if ($customer->driver_id === $driver->id || $customer->needs_review || $customer->reviewed_at !== null) {
            return;
        }

        $customer->timestamps = false;
        $customer->forceFill([
            'needs_review' => true,
            'review_reason' => ReviewReason::ForeignCustomer->value,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function find(array $record): ?Customer
    {
        $customer = Customer::query()->where('client_uuid', $record['uuid'])->first();

        if ($customer === null && isset($record['server_id'])) {
            $customer = Customer::query()->find($record['server_id']);
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function create(Driver $driver, User $user, array $record): Customer
    {
        $modifiedAt = $this->modifiedAt($record);

        $customer = (new Customer)->forceFill([
            'driver_id' => $driver->id,
            'client_uuid' => $record['uuid'],
            'name' => $record['name'],
            'phone_number' => $record['phone_number'] ?? null,
            'created_by' => $user->id,
            'enabled' => true,
            'received_at' => now(),
            'created_at' => $modifiedAt,
            'updated_at' => $modifiedAt,
        ]);
        $customer->save();

        (new CustomerAddress)->forceFill([
            ...$this->addressAttributes((array) $record['address']),
            'customer_id' => $customer->id,
            'is_primary' => true,
            'created_by' => $user->id,
            'enabled' => true,
            'created_at' => $modifiedAt,
            'updated_at' => $modifiedAt,
        ])->save();

        return $customer;
    }

    /**
     * Last write wins: an edit only applies when it is newer than what the server holds.
     *
     * @param  array<string, mixed>  $record
     */
    private function edit(Customer $customer, User $user, array $record): void
    {
        $modifiedAt = $this->modifiedAt($record);

        if (! $modifiedAt->greaterThan($customer->updated_at)) {
            return;
        }

        $customer->timestamps = false;
        $customer->forceFill([
            'client_uuid' => $customer->client_uuid ?? $record['uuid'],
            'name' => $record['name'],
            'phone_number' => $record['phone_number'] ?? null,
            'last_modified_by' => $user->id,
            'updated_at' => $modifiedAt,
            'received_at' => now(),
        ])->save();

        $address = $customer->primaryAddress ?? new CustomerAddress([
            'customer_id' => $customer->id,
            'is_primary' => true,
            'created_by' => $user->id,
            'enabled' => true,
        ]);

        $address->timestamps = false;
        $address->forceFill([
            ...$this->addressAttributes((array) $record['address']),
            'last_modified_by' => $user->id,
            'updated_at' => $modifiedAt,
        ])->save();
    }

    /**
     * The app's clock can run ahead of the server's; a future date would shadow every later server-side edit.
     *
     * @param  array<string, mixed>  $record
     */
    private function modifiedAt(array $record): Carbon
    {
        return Carbon::parse($record['modified_at'])->min(now());
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function addressAttributes(array $address): array
    {
        return [
            'street' => $address['street'],
            'apartment' => $address['apartment'] ?? null,
            'floor' => $address['floor'] ?? null,
            'visual_reference' => $address['visual_reference'] ?? null,
            'neighborhood' => $address['neighborhood'] ?? null,
            'latitude' => $address['latitude'] ?? null,
            'longitude' => $address['longitude'] ?? null,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'server_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'modified_at' => ['required', 'date'],
            'address' => ['required', 'array'],
            'address.street' => ['required', 'string'],
            'address.apartment' => ['nullable', 'string'],
            'address.floor' => ['nullable', 'integer'],
            'address.visual_reference' => ['nullable', 'string'],
            'address.neighborhood' => ['nullable', 'string'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
