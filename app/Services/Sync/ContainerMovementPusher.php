<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\ContainerMovement;
use App\Models\Customer;
use App\Models\CustomerContainerBalance;
use App\Models\Driver;
use App\Models\User;
use App\Models\Visit;

class ContainerMovementPusher extends EntityPusher
{
    /**
     * Containers the customer holds go up on a delivery and down on a return.
     *
     * @var array<string, int>
     */
    private const array DIRECTIONS = ['DELIVERED' => 1, 'RETURNED' => -1];

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules('customer', required: true),
            ...$this->referenceRules('visit'),
            'container_type_id' => ['required', 'integer', 'exists:container_types,id'],
            'movement_type' => ['required', 'in:'.implode(',', array_keys(self::DIRECTIONS))],
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
        $direction = self::DIRECTIONS[$record['movement_type']];
        $reason = $this->reviewReasonForCustomer($customer, $driver);

        $movement = ContainerMovement::create([
            'client_uuid' => $record['uuid'],
            'customer_id' => $customer->id,
            'container_type_id' => $record['container_type_id'],
            'visit_id' => $visit?->id,
            'movement_type' => $record['movement_type'],
            'quantity' => $record['quantity'],
            'direction' => $direction,
            'created_by' => $user->id,
            'enabled' => true,
            ...$this->reviewAttributes($reason),
        ]);

        // Guarantees the row exists so the lock below always has something to lock when two first movements race.
        CustomerContainerBalance::query()->insertOrIgnore([
            'customer_id' => $customer->id,
            'container_type_id' => $record['container_type_id'],
            'balance' => 0,
            'created_by' => $user->id,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balance = CustomerContainerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('container_type_id', $record['container_type_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $balance->fill([
            'balance' => $balance->balance + $record['quantity'] * $direction,
            'last_movement_id' => $movement->id,
            'last_modified_by' => $user->id,
        ])->save();

        return $this->accepted($record['uuid'], $movement->id, $reason?->value);
    }
}
