<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use Carbon\CarbonInterface;

/**
 * An offline edit of a customer from the app: only the fields the driver changed, merged field by field.
 */
final readonly class CustomerAppEdit
{
    /**
     * @param  array<string, mixed>  $fields  the customer fields it carries (see Customer::SYNCED_FIELDS); `enabled` may only be false
     * @param  ?array<string, mixed>  $address  the primary address fields it carries, or null when it carries none
     * @param  string  $clientUuid  adopted only when the customer has none yet
     * @param  ?string  $addressClientUuid  the app's id of the primary address, adopted only when it has none yet
     */
    public function __construct(
        public array $fields,
        public ?array $address,
        public CarbonInterface $modifiedAt,
        public User $modifiedBy,
        public string $clientUuid,
        public ?string $addressClientUuid = null,
    ) {}
}
