<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\AddressLocation;
use App\Customers\Customers\CustomerAddressDetails;
use App\Customers\Customers\CustomerEdit;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\CustomerRegistration;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Shared\ClientTime;
use Illuminate\Support\Carbon;

class CustomerPusher extends EntityPusher
{
    public function __construct(private readonly CustomerIntake $intake) {}

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

        return $this->accepted($record['uuid'], $customer->id, $this->pendingReason($customer));
    }

    /**
     * Accept-and-mark (ADR 0001): a record on another driver's customer is applied and left for review, even a stale one.
     */
    private function flagForReviewIfForeign(Customer $customer, Driver $driver): void
    {
        if ($customer->driver_id === $driver->id || ! $customer->canBeFlagged()) {
            return;
        }

        $customer->timestamps = false;
        $customer->flag(ReviewReason::ForeignCustomer);
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
        return $this->intake->register(new CustomerRegistration(
            name: $record['name'],
            phoneNumber: $record['phone_number'] ?? null,
            driverId: $driver->id,
            address: $this->addressDetails((array) $record['address']),
            createdBy: $user,
            clientUuid: $record['uuid'],
            opensAccount: false,
            appModifiedAt: $this->modifiedAt($record),
        ));
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

        $this->intake->update($customer, new CustomerEdit(
            name: $record['name'],
            phoneNumber: $record['phone_number'] ?? null,
            address: $this->addressDetails((array) $record['address']),
            modifiedBy: $user,
            reassignDriver: false,
            clientUuid: $record['uuid'],
            appModifiedAt: $modifiedAt,
        ));
    }

    /**
     * The app's clock can run ahead of the server's; a future date would shadow every later server-side edit.
     *
     * @param  array<string, mixed>  $record
     */
    private function modifiedAt(array $record): Carbon
    {
        return ClientTime::parse($record['modified_at'])->min(now());
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function addressDetails(array $address): CustomerAddressDetails
    {
        return new CustomerAddressDetails(
            street: $address['street'],
            apartment: $address['apartment'] ?? null,
            floor: $address['floor'] ?? null,
            visualReference: $address['visual_reference'] ?? null,
            location: new AddressLocation($address['neighborhood'] ?? null, $address['latitude'] ?? null, $address['longitude'] ?? null),
        );
    }

    /**
     * Builds before the floor became text send it as a JSON number; it is kept in its text form.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function normalize(array $record): array
    {
        if (is_int($record['address']['floor'] ?? null)) {
            $record['address']['floor'] = (string) $record['address']['floor'];
        }

        return $record;
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
            'address.floor' => ['nullable', 'string', 'max:20'],
            'address.visual_reference' => ['nullable', 'string'],
            'address.neighborhood' => ['nullable', 'string'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
