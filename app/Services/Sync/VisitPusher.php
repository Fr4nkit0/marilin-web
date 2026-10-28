<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Enums\VisitStatus;
use App\Routes\Visits\Models\Visit;
use App\Shared\ClientTime;

class VisitPusher extends EntityPusher
{
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            ...$this->referenceRules('customer', required: true),
            ...$this->referenceRules('route_stop'),
            'outcome_id' => ['nullable', 'integer', 'exists:visit_outcomes,id'],
            'arrived_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        if ($replay = $this->alreadyPushed(Visit::class, $record)) {
            return $replay;
        }

        /** @var Customer $customer */
        $customer = $this->reference(Customer::class, $record, 'customer');
        $stop = $this->reference(RouteStop::class, $record, 'route_stop');

        $reason = $this->reviewReasonForCustomer($customer, $driver);

        $visit = Visit::create([
            'client_uuid' => $record['uuid'],
            'route_stop_id' => $stop?->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'outcome_id' => $record['outcome_id'] ?? null,
            'arrived_at' => ClientTime::parse($record['arrived_at']),
            'latitude' => $record['latitude'] ?? null,
            'longitude' => $record['longitude'] ?? null,
            'altitude' => $record['altitude'] ?? null,
            'status' => VisitStatus::Finished->value,
            'notes' => $record['notes'] ?? null,
            'created_by' => $user->id,
            'enabled' => true,
            ...$this->reviewAttributes($reason),
        ]);

        return $this->accepted($record['uuid'], $visit->id, $reason?->value);
    }
}
