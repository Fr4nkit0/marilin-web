<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;

class VisitPusher extends EntityPusher
{
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', 'required_without:customer_uuid'],
            'customer_uuid' => ['nullable', 'uuid', 'required_without:customer_id'],
            'route_stop_id' => ['nullable', 'integer'],
            'route_stop_uuid' => ['nullable', 'uuid'],
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
        $existing = Visit::query()->where('client_uuid', $record['uuid'])->first();

        if ($existing !== null) {
            return $this->accepted($record['uuid'], $existing->id, $existing->needs_review ? $existing->review_reason : null);
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
            'arrived_at' => Carbon::parse($record['arrived_at']),
            'latitude' => $record['latitude'] ?? null,
            'longitude' => $record['longitude'] ?? null,
            'altitude' => $record['altitude'] ?? null,
            'status' => 'FINISHED',
            'notes' => $record['notes'] ?? null,
            'created_by' => $user->id,
            'enabled' => true,
            'needs_review' => $reason !== null,
            'review_reason' => $reason?->value,
        ]);

        return $this->accepted($record['uuid'], $visit->id, $reason?->value);
    }
}
