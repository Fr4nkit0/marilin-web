<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use Carbon\CarbonInterface;

final readonly class CustomerRegistration
{
    /**
     * @param  string  $initialDebt  what the customer owes on entering, as a decimal string
     * @param  array<string, int>  $initialContainers  containers the customer holds on entering, keyed by container type code
     * @param  bool  $opensAccount  false leaves the customer without a balance row until its first entry (the app's customers)
     * @param  ?CarbonInterface  $appModifiedAt  set when the record comes from the app: it dates the rows and marks them as received now
     */
    public function __construct(
        public string $name,
        public ?string $phoneNumber,
        public ?int $driverId,
        public CustomerAddressDetails $address,
        public User $createdBy,
        public ?string $clientUuid = null,
        public string $initialDebt = '0',
        public array $initialContainers = [],
        public bool $opensAccount = true,
        public ?CarbonInterface $appModifiedAt = null,
    ) {}
}
