<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;

class PushService
{
    public function __construct(
        private readonly CustomerPusher $customers,
        private readonly CustomerAgreementPusher $customerAgreements,
        private readonly RescheduledStopPusher $routeStops,
        private readonly VisitPusher $visits,
        private readonly ContainerMovementPusher $containerMovements,
        private readonly SalePusher $sales,
        private readonly PaymentPusher $payments,
        private readonly SaleCancellationPusher $saleCancellations,
        private readonly PaymentCancellationPusher $paymentCancellations,
    ) {}

    /**
     * Entities in dependency order: later ones may reference earlier ones of the same request by uuid.
     *
     * @return array<string, EntityPusher>
     */
    private function pushers(): array
    {
        return [
            'customers' => $this->customers,
            'customer_agreements' => $this->customerAgreements,
            'route_stops' => $this->routeStops,
            'visits' => $this->visits,
            'container_movements' => $this->containerMovements,
            'sales' => $this->sales,
            'payments' => $this->payments,
            'sale_cancellations' => $this->saleCancellations,
            'payment_cancellations' => $this->paymentCancellations,
        ];
    }

    /**
     * Processes each record independently and answers one result per record, grouped by entity.
     *
     * @param  array<string, array<int, mixed>>  $records
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function push(Driver $driver, User $user, array $records): array
    {
        $results = [];

        foreach ($this->pushers() as $entity => $pusher) {
            $results[$entity] = array_map(
                fn (mixed $record): array => $pusher->push($driver, $user, (array) $record),
                $records[$entity] ?? [],
            );
        }

        return $results;
    }
}
