<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Which driver a customer belongs to. Every change of driver records a CustomerReassignment: the pull tells the old
 * driver the customer left (customers_removed) and sends it whole to the new one.
 */
class DriverAssignment
{
    /**
     * Gives the customer to `$driverId`, or to no driver when null. Returns whether its driver changed;
     * keeping the same driver records nothing.
     */
    public function reassign(Customer $customer, ?int $driverId, User $reassignedBy): bool
    {
        return DB::transaction(function () use ($customer, $driverId, $reassignedBy): bool {
            $previousDriverId = $customer->driver_id;

            if ($previousDriverId === $driverId) {
                return false;
            }

            $customer->forceFill(['driver_id' => $driverId, 'last_modified_by' => $reassignedBy->id]);
            FieldClock::stampDirty($customer, Customer::SYNCED_FIELDS, now());
            $customer->save();

            $customer->reassignments()->create([
                'from_driver_id' => $previousDriverId,
                'to_driver_id' => $driverId,
                'reassigned_by' => $reassignedBy->id,
                'reassigned_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Moves part or all of a driver's portfolio to another driver at once: either every customer moves or none does.
     *
     * @param  list<int>  $customerIds
     * @return int how many customers moved
     *
     * @throws InvalidCustomerChange when a customer is not the source driver's
     */
    public function transfer(array $customerIds, int $fromDriverId, int $toDriverId, User $transferredBy): int
    {
        return DB::transaction(function () use ($customerIds, $fromDriverId, $toDriverId, $transferredBy): int {
            $customerIds = array_values(array_unique($customerIds));
            $customers = Customer::query()
                ->whereKey($customerIds)
                ->where('driver_id', $fromDriverId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($customers->count() !== count($customerIds)) {
                throw new InvalidCustomerChange('Some selected customers no longer belong to this driver.', 'customer_ids');
            }

            $customers->each(fn (Customer $customer) => $this->reassign($customer, $toDriverId, $transferredBy));

            return $customers->count();
        });
    }

    /**
     * Leaves every customer of the driver, enabled or not, without a driver.
     *
     * @return int how many customers were left without a driver
     */
    public function unassignAll(int $driverId, User $unassignedBy): int
    {
        return DB::transaction(function () use ($driverId, $unassignedBy): int {
            $customers = Customer::query()->where('driver_id', $driverId)->orderBy('id')->lockForUpdate()->get();

            $customers->each(fn (Customer $customer) => $this->reassign($customer, null, $unassignedBy));

            return $customers->count();
        });
    }
}
