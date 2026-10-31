<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use Carbon\CarbonInterface;

/**
 * An offline edit of an address from the app: only the fields the driver changed, merged field by field.
 */
final readonly class CustomerAddressAppEdit
{
    /**
     * @param  array<string, mixed>  $fields  the address fields it carries (see CustomerAddress::SYNCED_FIELDS)
     * @param  string  $clientUuid  adopted only when the address has none yet
     */
    public function __construct(
        public array $fields,
        public CarbonInterface $modifiedAt,
        public User $modifiedBy,
        public string $clientUuid,
    ) {}
}
