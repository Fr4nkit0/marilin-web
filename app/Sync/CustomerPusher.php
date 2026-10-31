<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\AddressLocation;
use App\Customers\Customers\CustomerAddressDetails;
use App\Customers\Customers\CustomerAppEdit;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\CustomerRegistration;
use App\Customers\Customers\FieldMerge;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;

class CustomerPusher extends EntityPusher
{
    public function __construct(private readonly CustomerIntake $intake) {}

    /**
     * Customer fields an edit may carry; any one it leaves out is never touched.
     */
    private const array FIELDS = ['name', 'phone_number'];

    /**
     * Primary address fields an edit may carry under `address`.
     */
    private const array ADDRESS_FIELDS = ['street', 'apartment', 'floor', 'visual_reference', 'neighborhood', 'latitude', 'longitude'];

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $customer = $this->findPushed(Customer::class, $record, 'customer');

        try {
            if ($customer === null) {
                $this->assertValid($record, [
                    'name' => ['required'],
                    'address' => ['required', 'array'],
                    'address.street' => ['required'],
                ]);
                $customer = $this->create($driver, $user, $record);

                return $this->accepted($record['uuid'], $customer->id, $this->pendingReason($customer));
            }

            $this->flagForReviewIfForeign($customer, $driver);
            $merge = $this->edit($customer, $user, $record);
        } catch (InvalidCustomerChange $invalid) {
            throw new RejectedRecord($invalid->getMessage());
        }

        return $merge->isOutdated()
            ? $this->outdated($record['uuid'], $customer->id, $this->pendingReason($customer))
            : $this->accepted($record['uuid'], $customer->id, $this->pendingReason($customer));
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
     * Last write wins per field: only the fields the edit carries, each when it is newer than the server's.
     *
     * @param  array<string, mixed>  $record
     */
    private function edit(Customer $customer, User $user, array $record): FieldMerge
    {
        $fields = array_intersect_key($record, array_flip(self::FIELDS));

        // The app may disable a customer, never enable one: an `enabled` true is ignored.
        if (($record['enabled'] ?? null) === false) {
            $fields['enabled'] = false;
        }

        $address = array_intersect_key((array) ($record['address'] ?? []), array_flip(self::ADDRESS_FIELDS));
        $wasEnabled = $customer->enabled;

        $merge = $this->intake->mergeAppEdit($customer, new CustomerAppEdit(
            fields: $fields,
            address: $address === [] ? null : $address,
            modifiedAt: $this->modifiedAt($record),
            modifiedBy: $user,
            clientUuid: $record['uuid'],
            addressClientUuid: $record['address']['uuid'] ?? null,
        ));

        // Accept-and-mark (ADR 0001): the driver's disable applies at once and the admin can enable the customer again.
        if ($wasEnabled && ! $customer->enabled) {
            $customer->timestamps = false;
            $customer->flag(ReviewReason::DriverDisabledCustomer);
        }

        return $merge;
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
     * The shape of what a record carries; a create also needs its name and street (see handle).
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'server_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'modified_at' => ['required', 'date'],
            'enabled' => ['sometimes', 'nullable', 'boolean:strict'],
            'address' => ['sometimes', 'array'],
            'address.uuid' => ['nullable', 'uuid'],
            'address.street' => ['sometimes', 'required', 'string'],
            'address.apartment' => ['sometimes', 'nullable', 'string'],
            'address.floor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address.visual_reference' => ['sometimes', 'nullable', 'string'],
            'address.neighborhood' => ['sometimes', 'nullable', 'string'],
            'address.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
