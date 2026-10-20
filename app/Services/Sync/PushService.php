<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Driver;
use App\Models\User;

class PushService
{
    public function __construct(private readonly CustomerPusher $customers) {}

    /**
     * Processes each record independently and answers one result per record, grouped by entity.
     *
     * @param  array<int, mixed>  $customers
     * @return array{customers: array<int, array<string, mixed>>}
     */
    public function push(Driver $driver, User $user, array $customers): array
    {
        return [
            'customers' => array_map(
                fn (mixed $record): array => $this->customers->push($driver, $user, (array) $record),
                $customers,
            ),
        ];
    }
}
