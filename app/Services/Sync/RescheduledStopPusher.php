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
            ...$this->referenceRules('rescheduled_from', required: true),
            'scheduled_time' => ['required', 'date_format:H:i'],
        ];
    }

    protected function handle(Driver $driver, User $user, array $record): array
    {
        if ($replay = $this->alreadyPushed(RouteStop::class, $record)) {
            return $replay;
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
