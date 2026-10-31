<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\MoveContainers;
use App\Street\Ledger\CustomerLedger;
use App\Street\StreetOperation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * How a customer enters the system and how its details (addresses, delivery preferences) change, from the web or from the app.
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

            $this->assertAddressUuidIsFree($input->addressClientUuid, null);

            (new CustomerAddress)->forceFill([
                ...$this->addressAttributes($input->address),
                'client_uuid' => $input->addressClientUuid,
                'customer_id' => $customer->id,
                'is_primary' => true,
                'created_by' => $input->createdBy->id,
                'enabled' => true,
                ...($input->appModifiedAt === null ? [] : ['received_at' => now()]),
                ...$stamps,
            ])->save();

            if ($input->opensAccount) {
                $this->ledger->openAccount($customer, $input->initialDebt, $input->createdBy);
            }
            $this->recordInitialContainers($customer, $input);
            $this->addPreferences($customer, $input->preferences);

            return $customer;
        });
    }

    /**
     * The admin's edit from the web. Only the fields whose value changes get a new clock, so an older offline edit
     * of the other fields still applies.
     */
    public function update(Customer $customer, CustomerEdit $input): Customer
    {
        return DB::transaction(function () use ($customer, $input): Customer {
            $customer->forceFill([
                'name' => $input->name,
                'phone_number' => $input->phoneNumber,
                'last_modified_by' => $input->modifiedBy->id,
                ...($input->reassignDriver ? ['driver_id' => $input->driverId] : []),
            ]);
            FieldClock::stampDirty($customer, Customer::SYNCED_FIELDS, now());
            $customer->save();

            $address = $customer->primaryAddress ?? $this->newPrimaryAddress($customer, $input->modifiedBy);

            $address->forceFill([
                ...$this->addressAttributes($input->address),
                'last_modified_by' => $input->modifiedBy->id,
            ]);
            FieldClock::stampDirty($address, CustomerAddress::SYNCED_FIELDS, now());
            $address->save();

            $this->addPreferences($customer, $input->preferences);

            return $customer;
        });
    }

    /**
     * The app's offline edit: each field it carries applies only when it is newer than that field's clock
     * (last write wins per field); the fields it does not carry are never touched. The rows it changes keep
     * their latest update date and are marked as received now, so the pull still sends them.
     */
    public function mergeAppEdit(Customer $customer, CustomerAppEdit $input): FieldMerge
    {
        return DB::transaction(function () use ($customer, $input): FieldMerge {
            $customerMerge = FieldClock::merge($customer, $input->fields, $input->modifiedAt);
            $address = null;
            $addressMerge = new FieldMerge;

            if ($input->address !== null) {
                $address = $customer->primaryAddress ?? $this->newPrimaryAddress($customer, $input->modifiedBy);
                $addressClientUuid = $address->client_uuid ?? $input->addressClientUuid;
                $this->assertAddressUuidIsFree($addressClientUuid, $address);

                $addressMerge = FieldClock::merge($address, $input->address, $input->modifiedAt);
                $address->forceFill(['client_uuid' => $addressClientUuid]);
            }

            $merge = $customerMerge->plus($addressMerge);

            if (! $merge->changedAny()) {
                return $merge;
            }

            $customer->forceFill(['client_uuid' => $customer->client_uuid ?? $input->clientUuid]);

            if ($customerMerge->changedAny()) {
                $this->stampAppChange($customer, $input->modifiedAt, $input->modifiedBy);
            }

            $customer->timestamps = false;
            $customer->save();

            if ($address !== null && $addressMerge->changedAny()) {
                $this->stampAppChange($address, $input->modifiedAt, $input->modifiedBy);
                $address->save();
            }

            return $merge;
        });
    }

    /**
     * The app's offline edit of an address, merged field by field like the customer's. A customer always keeps
     * exactly one primary address, and it stays enabled.
     */
    public function mergeAppAddressEdit(Customer $customer, CustomerAddress $address, CustomerAddressAppEdit $input): FieldMerge
    {
        return DB::transaction(function () use ($customer, $address, $input): FieldMerge {
            $merge = FieldClock::merge($address, $input->fields, $input->modifiedAt);

            if (! $merge->changedAny()) {
                return $merge;
            }

            if (! $address->enabled && ($address->is_primary || $address->getOriginal('is_primary'))) {
                throw new InvalidCustomerChange('The primary address cannot be disabled.');
            }

            if ($address->is_primary && $merge->wasApplied('is_primary')) {
                $this->unsetOtherPrimaries($customer, $address, $input->modifiedBy, $input->modifiedAt);
            }

            $address->forceFill(['client_uuid' => $address->client_uuid ?? $input->clientUuid]);
            $this->stampAppChange($address, $input->modifiedAt, $input->modifiedBy);
            $address->save();

            if (! $customer->addresses()->where('is_primary', true)->exists()) {
                throw new InvalidCustomerChange('A customer needs a primary address.');
            }

            return $merge;
        });
    }

    /**
     * Adds a delivery preference from the web. A removed preference for the same day and start comes back
     * with the new window instead of clashing with it.
     */
    public function addPreference(Customer $customer, CustomerPreferenceChange $input): CustomerPreference
    {
        $start = $this->timeOfDay($input->timeWindowStart);

        $removed = $customer->preferences()
            ->where('enabled', false)
            ->where('day_of_week', $input->dayOfWeek)
            ->when($start === null, fn ($query) => $query->whereNull('time_window_start'), fn ($query) => $query->where('time_window_start', $start))
            ->first();

        return $this->savePreference($customer, $removed, $input);
    }

    /**
     * Adds a delivery preference when `$preference` is null, or changes it. A customer has one preference per day and start,
     * and the windows of its enabled preferences of a day never overlap.
     */
    public function savePreference(Customer $customer, ?CustomerPreference $preference, CustomerPreferenceChange $input): CustomerPreference
    {
        return DB::transaction(function () use ($customer, $preference, $input): CustomerPreference {
            $start = $this->timeOfDay($input->timeWindowStart);
            $end = $this->timeOfDay($input->timeWindowEnd);

            if ($start !== null && $end !== null && $end <= $start) {
                throw new InvalidCustomerChange('The time window end must be after its start.', 'time_window_end');
            }

            $taken = $customer->preferences()
                ->where('day_of_week', $input->dayOfWeek)
                ->when($start === null, fn ($query) => $query->whereNull('time_window_start'), fn ($query) => $query->where('time_window_start', $start))
                ->when($preference !== null, fn ($query) => $query->whereKeyNot($preference->id))
                ->exists();

            if ($taken) {
                throw new InvalidCustomerChange('The customer already has a preference for that day and time.', 'day_of_week');
            }

            if ($input->enabled) {
                $this->assertWindowIsFree($customer, $preference, $input->dayOfWeek, $start, $end);
            }

            $preference ??= new CustomerPreference(['customer_id' => $customer->id, 'created_by' => $input->modifiedBy->id]);

            $preference->timestamps = $input->appModifiedAt === null;
            $preference->forceFill([
                'client_uuid' => $preference->client_uuid ?? $input->clientUuid,
                'day_of_week' => $input->dayOfWeek,
                'time_window_start' => $start,
                'time_window_end' => $end,
                'enabled' => $input->enabled,
                ...($preference->exists ? ['last_modified_by' => $input->modifiedBy->id] : []),
                ...$this->appStamps($input->appModifiedAt, created: ! $preference->exists),
            ])->save();

            return $preference;
        });
    }

    /**
     * Adds an address when `$address` is null, or changes it. A customer always keeps exactly one primary address,
     * and it stays enabled: making an address primary unsets the previous one.
     */
    public function saveAddress(Customer $customer, ?CustomerAddress $address, CustomerAddressChange $input): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $input): CustomerAddress {
            if (! $input->enabled && ($input->isPrimary || $address?->is_primary)) {
                throw new InvalidCustomerChange('The primary address cannot be disabled.');
            }

            if ($input->isPrimary) {
                $this->unsetOtherPrimaries($customer, $address, $input->modifiedBy, $input->appModifiedAt);
            }

            $address ??= new CustomerAddress(['customer_id' => $customer->id, 'created_by' => $input->modifiedBy->id]);

            $address->timestamps = $input->appModifiedAt === null;
            $address->forceFill([
                ...$this->addressAttributes($input->address),
                'client_uuid' => $address->client_uuid ?? $input->clientUuid,
                'is_primary' => $input->isPrimary,
                'enabled' => $input->enabled,
                ...($address->exists ? ['last_modified_by' => $input->modifiedBy->id] : []),
                ...$this->appStamps($input->appModifiedAt, created: ! $address->exists),
            ]);

            if ($input->appModifiedAt === null) {
                FieldClock::stampDirty($address, CustomerAddress::SYNCED_FIELDS, now());
            }

            $address->save();

            if (! $customer->addresses()->where('is_primary', true)->exists()) {
                throw new InvalidCustomerChange('A customer needs a primary address.');
            }

            return $address;
        });
    }

    /**
     * Making an address primary unsets the customer's previous primary; from the app, its flag is dated by the edit.
     */
    private function unsetOtherPrimaries(Customer $customer, ?CustomerAddress $kept, User $modifiedBy, ?CarbonInterface $appModifiedAt): void
    {
        $customer->addresses()
            ->where('is_primary', true)
            ->when($kept?->exists, fn ($query) => $query->whereKeyNot($kept->id))
            ->get()
            ->each(function (CustomerAddress $previous) use ($modifiedBy, $appModifiedAt): void {
                $previous->forceFill(['is_primary' => false, 'last_modified_by' => $modifiedBy->id]);
                FieldClock::stamp($previous, ['is_primary'], $appModifiedAt ?? now());

                if ($appModifiedAt !== null) {
                    $this->stampAppChange($previous, $appModifiedAt, $modifiedBy);
                }

                $previous->save();
            });
    }

    private function newPrimaryAddress(Customer $customer, User $createdBy): CustomerAddress
    {
        return new CustomerAddress([
            'customer_id' => $customer->id,
            'is_primary' => true,
            'created_by' => $createdBy->id,
            'enabled' => true,
        ]);
    }

    /**
     * A row an offline edit changed: it keeps its latest update date (the edit may be older than a later web change)
     * and is marked as received now, so the pull sends it whatever that date is.
     */
    private function stampAppChange(Customer|CustomerAddress $row, CarbonInterface $modifiedAt, User $modifiedBy): void
    {
        $updatedAt = $row->getAttributes()['updated_at'] ?? null;

        $row->timestamps = false;
        $row->forceFill([
            'last_modified_by' => $modifiedBy->id,
            'received_at' => now(),
            'updated_at' => $updatedAt === null ? $modifiedAt : Carbon::parse($updatedAt)->max($modifiedAt),
            ...($row->exists ? [] : ['created_at' => $modifiedAt]),
        ]);
    }

    /**
     * @param  list<CustomerPreferenceChange>  $preferences
     */
    private function addPreferences(Customer $customer, array $preferences): void
    {
        foreach ($preferences as $index => $preference) {
            try {
                $this->addPreference($customer, $preference);
            } catch (InvalidCustomerChange $invalid) {
                throw $invalid->under("preferences.{$index}");
            }
        }
    }

    private function recordInitialContainers(Customer $customer, CustomerRegistration $input): void
    {
        foreach ($input->initialContainers as $containerTypeId => $quantity) {
            $type = $quantity > 0 ? ContainerType::query()->enabled()->find($containerTypeId) : null;

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
     * A change from the app is dated by the app and marked as received now, so the pull still sends it when that date is old.
     *
     * @return array<string, mixed>
     */
    private function appStamps(?CarbonInterface $appModifiedAt, bool $created = false): array
    {
        return $appModifiedAt === null ? [] : [
            ...($created ? ['created_at' => $appModifiedAt] : []),
            'updated_at' => $appModifiedAt,
            'received_at' => now(),
        ];
    }

    /**
     * The app's id of an address names one address only; another address already holding it means the app mixed them up.
     */
    private function assertAddressUuidIsFree(?string $clientUuid, ?CustomerAddress $address): void
    {
        $taken = $clientUuid !== null && CustomerAddress::query()
            ->where('client_uuid', $clientUuid)
            ->when($address?->exists, fn ($query) => $query->whereKeyNot($address->id))
            ->exists();

        if ($taken) {
            throw new InvalidCustomerChange('The address uuid belongs to another address.');
        }
    }

    /**
     * Windows are half open, [start, end): one that ends when another starts does not overlap it. A window without
     * a start runs from midnight and one without an end until midnight. Removed preferences never block.
     * The message is the app's, in Spanish (it names the window, so it is not a translation key).
     */
    private function assertWindowIsFree(Customer $customer, ?CustomerPreference $preference, int $dayOfWeek, ?string $start, ?string $end): void
    {
        $overlapping = $customer->preferences()
            ->where('enabled', true)
            ->where('day_of_week', $dayOfWeek)
            ->when($preference?->exists, fn ($query) => $query->whereKeyNot($preference->id))
            ->orderByRaw('time_window_start nulls first')
            ->get()
            ->first(fn (CustomerPreference $other): bool => ($start ?? '00:00:00') < ($other->time_window_end ?? '24:00:00')
                && ($other->time_window_start ?? '00:00:00') < ($end ?? '24:00:00'));

        if ($overlapping === null) {
            return;
        }

        $from = $overlapping->time_window_start === null ? null : substr($overlapping->time_window_start, 0, 5);
        $until = $overlapping->time_window_end === null ? null : substr($overlapping->time_window_end, 0, 5);

        $window = match (true) {
            $from !== null && $until !== null => "{$from}–{$until}",
            $from !== null => "desde {$from}",
            $until !== null => "hasta {$until}",
            default => 'todo el día',
        };

        throw new InvalidCustomerChange("Se superpone con {$window}.", 'time_window_start');
    }

    private function timeOfDay(?string $time): ?string
    {
        return $time === null ? null : Carbon::parse($time)->format('H:i:s');
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
