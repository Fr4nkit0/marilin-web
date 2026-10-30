<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use Carbon\CarbonInterface;

final readonly class CustomerAddressChange
{
    /**
     * @param  bool  $isPrimary  true makes it the customer's only primary address
     * @param  bool  $enabled  false disables it; the primary address cannot be disabled
     * @param  ?string  $clientUuid  adopted only when the address has none yet
     * @param  ?CarbonInterface  $appModifiedAt  set when the change comes from the app: it dates the rows and marks them as received now
     */
    public function __construct(
        public CustomerAddressDetails $address,
        public bool $isPrimary,
        public bool $enabled,
        public User $modifiedBy,
        public ?string $clientUuid = null,
        public ?CarbonInterface $appModifiedAt = null,
    ) {}
}
