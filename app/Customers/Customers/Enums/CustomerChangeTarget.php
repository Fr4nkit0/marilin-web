<?php

declare(strict_types=1);

namespace App\Customers\Customers\Enums;

/**
 * What a pending customer change edits: the push entity it came in.
 */
enum CustomerChangeTarget: string
{
    case Customer = 'customer';
    case Address = 'customer_address';
    case Preference = 'customer_preference';
}
