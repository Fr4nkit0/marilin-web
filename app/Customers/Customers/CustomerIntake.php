<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\MoveContainers;
use App\Street\Ledger\CustomerLedger;
use App\Street\StreetOperation;
use Illuminate\Support\Facades\DB;

/**
 * How a customer enters the system and how its details change, from the web or from the app.
 * The initial debt is an adjustment in the ledger and the initial containers an adjustment movement,
 * so every balance is explained by its history.
 */
class CustomerIntake
{
    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly StreetOperation $street,
    ) {}

    public function register(CustomerRegistration $input): Customer
    {
        return DB::transaction(function () use ($input): Customer {
            $stamps = $input->appModifiedAt === null ? [] : [
                'created_at' => $input->appModifiedAt,
                'updated_at' => $input->appModifiedAt,
            ];

            $customer = (new Customer)->forceFill([
                'driver_id' => $input->driverId,
                'client_uuid' => $input->clientUuid,
                'name' => $input->name,
                'phone_number' => $input->phoneNumber,
                'created_by' => $input->createdBy->id,
                'enabled' => true,
                ...($input->appModifiedAt === null ? [] : ['received_at' => now()]),
                ...$stamps,
            ]);
            $customer->save();

            (new CustomerAddress)->forceFill([
                ...$this->addressAttributes($input->address),
                'customer_id' => $customer->id,
                'is_primary' => true,
                'created_by' => $input->createdBy->id,
                'enabled' => true,
                ...$stamps,
            ])->save();

            if ($input->opensAccount) {
                $this->ledger->openAccount($customer, $input->initialDebt, $input->createdBy);
            }
            $this->recordInitialContainers($customer, $input);

            return $customer;
        });
    }

    public function update(Customer $customer, CustomerEdit $input): Customer
    {
        return DB::transaction(function () use ($customer, $input): Customer {
            $stamps = $input->appModifiedAt === null ? [] : ['updated_at' => $input->appModifiedAt];

            $customer->timestamps = $input->appModifiedAt === null;
            $customer->forceFill([
                'client_uuid' => $customer->client_uuid ?? $input->clientUuid,
                'name' => $input->name,
                'phone_number' => $input->phoneNumber,
                'last_modified_by' => $input->modifiedBy->id,
                ...($input->reassignDriver ? ['driver_id' => $input->driverId] : []),
                ...($input->appModifiedAt === null ? [] : ['received_at' => now()]),
                ...$stamps,
            ])->save();

            $address = $customer->primaryAddress ?? new CustomerAddress([
                'customer_id' => $customer->id,
                'is_primary' => true,
                'created_by' => $input->modifiedBy->id,
                'enabled' => true,
            ]);

            $address->timestamps = $input->appModifiedAt === null;
            $address->forceFill([
                ...$this->addressAttributes($input->address),
                'last_modified_by' => $input->modifiedBy->id,
                ...$stamps,
            ])->save();

            return $customer;
        });
    }

    private function recordInitialContainers(Customer $customer, CustomerRegistration $input): void
    {
        foreach ($input->initialContainers as $code => $quantity) {
            $type = $quantity > 0 ? ContainerType::query()->where('code', $code)->first() : null;

            if ($type === null) {
                continue;
            }

            $this->street->moveContainers(new MoveContainers(
                customer: $customer,
                containerTypeId: $type->id,
                type: ContainerMovementType::Adjustment,
                quantity: $quantity,
                visit: null,
                recordedBy: $input->createdBy,
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function addressAttributes(CustomerAddressDetails $address): array
    {
        return [
            'street' => $address->street,
            'apartment' => $address->apartment,
            'floor' => $address->floor,
            'visual_reference' => $address->visualReference,
            ...($address->location === null ? [] : [
                'neighborhood' => $address->location->neighborhood,
                'latitude' => $address->location->latitude,
                'longitude' => $address->location->longitude,
            ]),
        ];
    }
}
