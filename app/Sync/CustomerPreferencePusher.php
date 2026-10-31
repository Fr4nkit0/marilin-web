<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\CustomerPreferenceChange;
use App\Customers\Customers\Enums\CustomerChangeTarget;
use App\Customers\Customers\ForeignCustomerChanges;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerPreference;
use Illuminate\Database\Eloquent\Model;

/**
 * A delivery preference the driver added or changed: created, or edited with last write wins like the customer itself.
 */
class CustomerPreferencePusher extends EntityPusher
{
    public function __construct(
        private readonly CustomerIntake $intake,
        private readonly ForeignCustomerChanges $foreignChanges,
        private readonly PullService $pull,
    ) {}

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $preference = $this->findPushed(CustomerPreference::class, $record, 'customer preference');

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');
        $this->assertBelongsToCustomer($preference, $customer, 'record');

        $modifiedAt = $this->modifiedAt($record);

        if ($this->isForeign($customer, $driver)) {
            $fields = array_intersect_key($record, array_flip(ForeignCustomerChanges::PREFERENCE_FIELDS));
            $this->foreignChanges->propose($customer, CustomerChangeTarget::Preference, $preference, $record['uuid'], $fields, $modifiedAt, $user);

            return $this->heldForAdmin($record['uuid'], $preference?->id);
        }

        if ($preference === null || $modifiedAt->greaterThan($preference->updated_at)) {
            try {
                $preference = $this->intake->savePreference($customer, $preference, new CustomerPreferenceChange(
                    dayOfWeek: $record['day_of_week'],
                    timeWindowStart: $record['time_window_start'] ?? null,
                    timeWindowEnd: $record['time_window_end'] ?? null,
                    enabled: $record['enabled'],
                    modifiedBy: $user,
                    clientUuid: $record['uuid'],
                    appModifiedAt: $modifiedAt,
                ));
            } catch (InvalidCustomerChange $invalid) {
                throw new RejectedRecord($invalid->getMessage());
            }
        }

        return $this->accepted($record['uuid'], $preference->id, $this->pendingReasons($customer));
    }

    /**
     * Only a record that points at its own server row gets it back: an insert rejected because its day and start are
     * taken (audit E6b) has no row on the server, so it answers no `current` and the app drops its local row.
     */
    protected function current(Model $row): array
    {
        return $this->pull->row($row);
    }

    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'server_id' => ['nullable', 'integer'],
            ...$this->referenceRules('customer', required: true),
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'time_window_start' => ['nullable', 'date_format:H:i'],
            'time_window_end' => ['nullable', 'date_format:H:i'],
            'enabled' => ['required', 'boolean:strict'],
            'modified_at' => ['required', 'date'],
        ];
    }
}
