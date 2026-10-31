<?php

declare(strict_types=1);

namespace App\Street\Containers;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Sales\Models\Sale;

/**
 * Containers delivered to or returned by a customer; moved with a sale, cancelling the sale gives them back.
 */
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
        /** @var list<ReviewReason|null> why the record is left for the admin's review; none (or only nulls) leaves it clean */
        public array $reviewReasons = [],
        public ?Sale $sale = null,
    ) {}
}
