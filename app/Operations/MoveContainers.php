<?php

declare(strict_types=1);

namespace App\Operations;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Enums\ContainerMovementType;
use App\Models\Visit;
use App\Review\ReviewReason;

final readonly class MoveContainers
{
    public function __construct(
        public Customer $customer,
        public int $containerTypeId,
        public ContainerMovementType $type,
        public int $quantity,
        public ?Visit $visit,
        public User $recordedBy,
        public ?string $clientUuid = null,
        public ?ReviewReason $reviewReason = null,
    ) {}
}
