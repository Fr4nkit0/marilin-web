<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;

/**
 * The admin's edit of a customer from the web; the app's offline edits are a CustomerAppEdit.
 */
final readonly class CustomerEdit
{
    /**
     * @param  bool  $reassignDriver  false keeps the customer's driver
     * @param  list<CustomerPreferenceChange>  $preferences  delivery preferences added with it, in the same transaction
     */
    public function __construct(
        public string $name,
        public ?string $phoneNumber,
        public CustomerAddressDetails $address,
        public User $modifiedBy,
        public ?int $driverId = null,
        public bool $reassignDriver = true,
        public array $preferences = [],
    ) {}
}
