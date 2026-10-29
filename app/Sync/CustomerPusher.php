<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\AddressLocation;
use App\Customers\Customers\CustomerAddressDetails;
use App\Customers\Customers\CustomerEdit;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\CustomerRegistration;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;

class CustomerPusher extends EntityPusher
{
    public function __construct(private readonly CustomerIntake $intake) {}

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $customer = $this->findPushed(Customer::class, $record, 'customer');

        try {
            if ($customer === null) {
                $customer = $this->create($driver, $user, $record);
            } else {
                $this->flagForReviewIfForeign($customer, $driver);
                $this->edit($customer, $user, $record);
            }
        } catch (InvalidCustomerChange $invalid) {
            throw new RejectedRecord($invalid->getMessage());
        }

        return $this->accepted($record['uuid'], $customer->id, $this->pendingReason($customer));
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
            addressClientUuid: $record['address']['uuid'] ?? null,
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

        $disable = ($record['enabled'] ?? null) === false && $customer->enabled;

        $this->intake->update($customer, new CustomerEdit(
            name: $record['name'],
            phoneNumber: $record['phone_number'] ?? null,
            address: $this->addressDetails((array) $record['address']),
            modifiedBy: $user,
            reassignDriver: false,
            clientUuid: $record['uuid'],
            appModifiedAt: $modifiedAt,
            disable: $disable,
            addressClientUuid: $record['address']['uuid'] ?? null,
        ));

        // Accept-and-mark (ADR 0001): the driver's disable applies at once and the admin can enable the customer again.
        if ($disable) {
            $customer->timestamps = false;
            $customer->flag(ReviewReason::DriverDisabledCustomer);
        }
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
            'enabled' => ['nullable', 'boolean:strict'],
            'address' => ['required', 'array'],
            'address.uuid' => ['nullable', 'uuid'],
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
