<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Routes\RouteProgress;
use App\Routes\Visits\Enums\VisitStatus;
use App\Routes\Visits\Models\Visit;
use App\Shared\ClientTime;

class VisitPusher extends EntityPusher
{
    public function __construct(private readonly RouteProgress $progress) {}

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
            'modified_at' => ['nullable', 'date'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $existing = Visit::query()->where('client_uuid', $record['uuid'])->first();

        if ($existing !== null && isset($record['modified_at'])) {
            $this->edit($existing, $driver, $user, $record);
            $this->recalculateRoute($existing);

            return $this->accepted($record['uuid'], $existing->id, $this->pendingReasons($existing));
        }

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
            'app_modified_at' => isset($record['modified_at']) ? $this->modifiedAt($record) : null,
            ...$this->reviewAttributes($reason),
        ]);

        $this->recalculateRoute($visit);

        return $this->accepted($record['uuid'], $visit->id, ReviewReason::values($reason));
    }

    /**
     * A visit on a stop moves the stop's route; a loose visit (no stop) belongs to no route.
     */
    private function recalculateRoute(Visit $visit): void
    {
        if ($visit->route_stop_id !== null) {
            $this->progress->recalculate($visit->routeStop->route_id);
        }
    }

    /**
     * Only the outcome and notes of the driver's own visits can be edited, on the visit's day; last write wins by the app's modification date.
     *
     * @param  array<string, mixed>  $record
     */
    private function edit(Visit $visit, Driver $driver, User $user, array $record): void
    {
        if ($visit->driver_id !== $driver->id) {
            throw new RejectedRecord('The visit belongs to another driver.');
        }

        $modifiedAt = $this->modifiedAt($record);

        // A stale edit or a replay of the creation changes nothing, whatever day it arrives.
        if ($visit->app_modified_at !== null && ! $modifiedAt->greaterThan($visit->app_modified_at)) {
            return;
        }

        $this->assertActedOnRecordDay(
            $visit->arrived_at,
            $modifiedAt,
            "The edit was not made on the visit's day.",
            'The edit is more than %d days old.',
        );

        $visit->update([
            'outcome_id' => $record['outcome_id'] ?? null,
            'notes' => $record['notes'] ?? null,
            'app_modified_at' => $modifiedAt,
            'last_modified_by' => $user->id,
        ]);
    }
}
