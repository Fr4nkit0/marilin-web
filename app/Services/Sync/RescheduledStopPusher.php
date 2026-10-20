<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Driver;
use App\Models\RouteStop;
use App\Models\User;

/**
 * ADR 0002: the app creates a stop later the same day, in the route of the original stop, linked to it.
 */
class RescheduledStopPusher extends EntityPusher
{
    protected function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'rescheduled_from_id' => ['nullable', 'integer', 'required_without:rescheduled_from_uuid'],
            'rescheduled_from_uuid' => ['nullable', 'uuid', 'required_without:rescheduled_from_id'],
            'scheduled_time' => ['required', 'date_format:H:i'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        $existing = RouteStop::query()->where('client_uuid', $record['uuid'])->first();

        if ($existing !== null) {
            return $this->accepted($record['uuid'], $existing->id);
        }

        /** @var RouteStop $original */
        $original = $this->reference(RouteStop::class, $record, 'rescheduled_from');

        if (RouteStop::query()->where('is_rescheduled_from', $original->id)->exists()) {
            throw new RejectedRecord('The stop was already rescheduled.');
        }

        $stop = RouteStop::create([
            'client_uuid' => $record['uuid'],
            'route_id' => $original->route_id,
            'customer_id' => $original->customer_id,
            'address_id' => $original->address_id,
            'is_rescheduled_from' => $original->id,
            'scheduled_time' => $record['scheduled_time'],
            'enabled' => true,
        ]);

        return $this->accepted($record['uuid'], $stop->id);
    }
}
