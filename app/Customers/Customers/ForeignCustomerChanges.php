<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use App\Customers\Customers\Enums\CustomerChangeStatus;
use App\Customers\Customers\Enums\CustomerChangeTarget;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Customers\Customers\Models\PendingCustomerChange;
use App\Review\ReviewReason;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * A driver may not change the data of a customer assigned to another driver at once (ADR 0001, exception): the change
 * waits in the review inbox, the admin approves it (it is applied through the intake like an edit of the admin, dated
 * at the approval) or rejects it (nothing changes). Street records on that customer keep accept-and-mark.
 */
class ForeignCustomerChanges
{
    /**
     * The fields a preference change carries, in the order the admin reads them.
     *
     * @var list<string>
     */
    public const array PREFERENCE_FIELDS = ['day_of_week', 'time_window_start', 'time_window_end', 'enabled'];

    public function __construct(private readonly CustomerIntake $intake) {}

    /**
     * Holds the change for the admin; one that carries no field is not held. A record still waiting keeps one change:
     * a retry is held once, a newer edit of it folds its fields into that change and an older resend changes nothing.
     *
     * @param  array<string, mixed>  $changes  the fields as the app sent them; a customer change carries its primary address fields under `address`
     * @param  ?Model  $row  the row it edits, null when it adds an address or a preference
     */
    public function propose(Customer $customer, CustomerChangeTarget $target, ?Model $row, string $clientUuid, array $changes, CarbonInterface $modifiedAt, User $proposedBy): ?PendingCustomerChange
    {
        if ($changes === []) {
            return null;
        }

        $held = PendingCustomerChange::query()
            ->where('client_uuid', $clientUuid)
            ->where('target', $target->value)
            ->where('status', CustomerChangeStatus::Pending->value)
            ->lockForUpdate()
            ->first();

        if ($held !== null) {
            if ($modifiedAt->greaterThan($held->modified_at)) {
                $held->update([
                    'changes' => array_replace_recursive($held->changes, $changes),
                    'modified_at' => $modifiedAt,
                ]);
            }

            return $held;
        }

        return PendingCustomerChange::query()->create([
            'customer_id' => $customer->id,
            'target' => $target,
            'target_id' => $row?->getKey(),
            'client_uuid' => $clientUuid,
            'changes' => $changes,
            'modified_at' => $modifiedAt,
            'proposed_by' => $proposedBy->id,
            'status' => CustomerChangeStatus::Pending,
            ...ReviewReason::columns(ReviewReason::ForeignCustomer),
        ]);
    }

    /**
     * Applies the change and closes it. It is dated at the approval, like an admin's web edit, so it wins over every
     * earlier change of those fields (even a web edit made after the driver's) and an older offline edit loses to it.
     *
     * @throws InvalidCustomerChange when the change no longer fits the customer (it stays pending)
     * @throws ModelNotFoundException when the change was already approved or rejected
     */
    public function approve(PendingCustomerChange $change, User $admin): void
    {
        DB::transaction(function () use ($change, $admin): void {
            $change = $this->pending($change);
            $this->apply($change, $admin);
            $this->close($change, CustomerChangeStatus::Approved, $admin);
        });
    }

    /**
     * Discards the change: the customer is left as it is.
     *
     * @throws ModelNotFoundException when the change was already approved or rejected
     */
    public function reject(PendingCustomerChange $change, User $admin): void
    {
        DB::transaction(function () use ($change, $admin): void {
            $this->close($this->pending($change), CustomerChangeStatus::Rejected, $admin);
        });
    }

    /**
     * Each proposed field next to its current value (null when the change adds the row), in a fixed order.
     *
     * @return list<array{field: string, current: mixed, proposed: mixed}>
     */
    public function compare(PendingCustomerChange $change): array
    {
        $row = $change->targetRow();
        $changes = $change->changes;

        return match ($change->target) {
            CustomerChangeTarget::Customer => [
                ...$this->fieldRows(Customer::SYNCED_FIELDS, $changes, $row),
                ...$this->fieldRows(CustomerAddress::SYNCED_FIELDS, (array) ($changes['address'] ?? []), $row?->primaryAddress, 'address.'),
            ],
            CustomerChangeTarget::Address => $this->fieldRows(CustomerAddress::SYNCED_FIELDS, $changes, $row),
            CustomerChangeTarget::Preference => $this->fieldRows(self::PREFERENCE_FIELDS, $changes, $row),
        };
    }

    private function pending(PendingCustomerChange $change): PendingCustomerChange
    {
        return PendingCustomerChange::query()
            ->whereKey($change->id)
            ->where('status', CustomerChangeStatus::Pending->value)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function apply(PendingCustomerChange $change, User $admin): void
    {
        $customer = $change->customer;
        $changes = $change->changes;
        $row = $change->targetRow();

        if ($change->target_id !== null && $row === null) {
            throw new InvalidCustomerChange('The record the change edits no longer exists.');
        }

        match ($change->target) {
            CustomerChangeTarget::Customer => $this->intake->mergeAppEdit($customer, new CustomerAppEdit(
                fields: Arr::except($changes, 'address'),
                address: isset($changes['address']) ? (array) $changes['address'] : null,
                modifiedAt: now(),
                modifiedBy: $admin,
            )),
            CustomerChangeTarget::Address => $row instanceof CustomerAddress
                ? $this->intake->mergeAppAddressEdit($customer, $row, new CustomerAddressAppEdit($changes, now(), $admin))
                : $this->intake->saveAddress($customer, null, new CustomerAddressChange(
                    address: new CustomerAddressDetails(
                        street: $changes['street'],
                        apartment: $changes['apartment'] ?? null,
                        floor: $changes['floor'] ?? null,
                        visualReference: $changes['visual_reference'] ?? null,
                        location: new AddressLocation($changes['neighborhood'] ?? null, $changes['latitude'] ?? null, $changes['longitude'] ?? null),
                    ),
                    isPrimary: $changes['is_primary'],
                    enabled: $changes['enabled'],
                    modifiedBy: $admin,
                    clientUuid: $change->client_uuid,
                )),
            CustomerChangeTarget::Preference => $this->intake->savePreference($customer, $row instanceof CustomerPreference ? $row : null, new CustomerPreferenceChange(
                dayOfWeek: $changes['day_of_week'],
                timeWindowStart: $changes['time_window_start'] ?? null,
                timeWindowEnd: $changes['time_window_end'] ?? null,
                enabled: $changes['enabled'],
                modifiedBy: $admin,
                clientUuid: $row === null ? $change->client_uuid : null,
            )),
        };
    }

    private function close(PendingCustomerChange $change, CustomerChangeStatus $status, User $admin): void
    {
        $change->forceFill(['status' => $status]);
        $change->markReviewed($admin);
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $changes
     * @return list<array{field: string, current: mixed, proposed: mixed}>
     */
    private function fieldRows(array $fields, array $changes, ?Model $row, string $prefix = ''): array
    {
        $rows = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $changes)) {
                $rows[] = [
                    'field' => $prefix.$field,
                    'current' => $this->shown($field, $row?->getAttribute($field)),
                    'proposed' => $this->shown($field, $changes[$field]),
                ];
            }
        }

        return $rows;
    }

    /**
     * A time of day reads as H:i, whether stored (H:i:s) or sent by the app (H:i).
     */
    private function shown(string $field, mixed $value): mixed
    {
        return str_starts_with($field, 'time_window_') && is_string($value) ? substr($value, 0, 5) : $value;
    }
}
