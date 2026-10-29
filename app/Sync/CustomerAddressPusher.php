<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\AddressLocation;
use App\Customers\Customers\CustomerAddressChange;
use App\Customers\Customers\CustomerAddressDetails;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;

/**
 * An address the driver added or changed: created, or edited with last write wins like the customer itself.
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

        $modifiedAt = $this->modifiedAt($record);

        if ($address === null || $modifiedAt->greaterThan($address->updated_at)) {
            try {
                $address = $this->intake->saveAddress($customer, $address, new CustomerAddressChange(
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
                    appModifiedAt: $modifiedAt,
                ));
            } catch (InvalidCustomerChange $invalid) {
                throw new RejectedRecord($invalid->getMessage());
            }
        }

        return $this->accepted($record['uuid'], $address->id, $this->pendingReason($customer));
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

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'server_id' => ['nullable', 'integer'],
            ...$this->referenceRules('customer', required: true),
            'street' => ['required', 'string', 'max:200'],
            'apartment' => ['nullable', 'string', 'max:20'],
            'floor' => ['nullable', 'string', 'max:20'],
            'visual_reference' => ['nullable', 'string'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['required', 'boolean:strict'],
            'enabled' => ['required', 'boolean:strict'],
            'modified_at' => ['required', 'date'],
        ];
    }
}
