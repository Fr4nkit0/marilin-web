<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use Carbon\CarbonInterface;

final readonly class CustomerEdit
{
    /**
     * @param  bool  $reassignDriver  false keeps the customer's driver (the app never moves a customer)
     * @param  ?string  $clientUuid  adopted only when the customer has none yet
     * @param  ?CarbonInterface  $appModifiedAt  set when the edit comes from the app: it dates the rows and marks the customer as received now
     */
    public function __construct(
        public string $name,
        public ?string $phoneNumber,
        public CustomerAddressDetails $address,
        public User $modifiedBy,
        public ?int $driverId = null,
        public bool $reassignDriver = true,
        public ?string $clientUuid = null,
        public ?CarbonInterface $appModifiedAt = null,
    ) {}
}
