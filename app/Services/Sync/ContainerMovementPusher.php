<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\ContainerMovementType;
use App\Models\ContainerMovement;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Visit;
use App\Operations\MoveContainers;
use App\Operations\StreetOperation;

class ContainerMovementPusher extends EntityPusher
{
    /** Movement types the app may push; the others are written by the server. */
    private const array PUSHABLE = [ContainerMovementType::Delivered->value, ContainerMovementType::Returned->value];

    public function __construct(private readonly StreetOperation $operation) {}

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules('customer', required: true),
            ...$this->referenceRules('visit'),
            'container_type_id' => ['required', 'integer', 'exists:container_types,id'],
            'movement_type' => ['required', 'in:'.implode(',', self::PUSHABLE)],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        if ($replay = $this->alreadyPushed(ContainerMovement::class, $record)) {
            return $replay;
        }

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');
        $visit = $this->reference(Visit::class, $record, 'visit');
        $reason = $this->reviewReasonForCustomer($customer, $driver);

        $movement = $this->operation->moveContainers(new MoveContainers(
            customer: $customer,
            containerTypeId: $record['container_type_id'],
            type: ContainerMovementType::from($record['movement_type']),
            quantity: $record['quantity'],
            visit: $visit,
            recordedBy: $user,
            clientUuid: $record['uuid'],
            reviewReason: $reason,
        ));

        return $this->accepted($record['uuid'], $movement->id, $reason?->value);
    }
}
