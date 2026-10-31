<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\AddressLocation;
use App\Customers\Customers\CustomerAddressAppEdit;
use App\Customers\Customers\CustomerAddressChange;
use App\Customers\Customers\CustomerAddressDetails;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;

/**
 * An address the driver added or changed: created whole, or edited with last write wins per field like the customer itself.
 * The customer push keeps editing whichever address is primary.
 */
class CustomerAddressPusher extends EntityPusher
{
    public function __construct(private readonly CustomerIntake $intake) {}

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $address = $this->findPushed(CustomerAddress::class, $record, 'customer address');

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');
        $this->assertBelongsToCustomer($address, $customer, 'record');
        $this->flagForReviewIfForeign($customer, $driver);

        try {
            if ($address === null) {
                $this->assertValid($record, [
                    'street' => ['required'],
                    'is_primary' => ['required'],
                    'enabled' => ['required'],
                ]);
                $address = $this->create($customer, $user, $record);

                return $this->accepted($record['uuid'], $address->id, $this->pendingReason($customer));
            }

            // Last write wins per field: only the fields the edit carries, each when it is newer than the server's.
            $merge = $this->intake->mergeAppAddressEdit($customer, $address, new CustomerAddressAppEdit(
                fields: array_intersect_key($record, array_flip(CustomerAddress::SYNCED_FIELDS)),
                modifiedAt: $this->modifiedAt($record),
                modifiedBy: $user,
                clientUuid: $record['uuid'],
            ));
        } catch (InvalidCustomerChange $invalid) {
            throw new RejectedRecord($invalid->getMessage());
        }

        return $merge->isOutdated()
            ? $this->outdated($record['uuid'], $address->id, $this->pendingReason($customer))
            : $this->accepted($record['uuid'], $address->id, $this->pendingReason($customer));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function create(Customer $customer, User $user, array $record): CustomerAddress
    {
        return $this->intake->saveAddress($customer, null, new CustomerAddressChange(
            address: new CustomerAddressDetails(
                street: $record['street'],
                apartment: $record['apartment'] ?? null,
                floor: $record['floor'] ?? null,
                visualReference: $record['visual_reference'] ?? null,
                location: new AddressLocation($record['neighborhood'] ?? null, $record['latitude'] ?? null, $record['longitude'] ?? null),
            ),
            isPrimary: $record['is_primary'],
            enabled: $record['enabled'],
            modifiedBy: $user,
            clientUuid: $record['uuid'],
            appModifiedAt: $this->modifiedAt($record),
        ));
    }

    /**
     * A floor sent as a JSON number is kept in its text form, like the customer push does.
     */
    protected function normalize(array $record): array
    {
        if (is_int($record['floor'] ?? null)) {
            $record['floor'] = (string) $record['floor'];
        }

        return $record;
    }

    /**
     * The shape of what a record carries; a create also needs its street and flags (see handle).
     */
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'server_id' => ['nullable', 'integer'],
            ...$this->referenceRules('customer', required: true),
            'street' => ['sometimes', 'required', 'string', 'max:200'],
            'apartment' => ['sometimes', 'nullable', 'string', 'max:20'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'visual_reference' => ['sometimes', 'nullable', 'string'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'required', 'boolean:strict'],
            'enabled' => ['sometimes', 'required', 'boolean:strict'],
            'modified_at' => ['required', 'date'],
        ];
    }
}
