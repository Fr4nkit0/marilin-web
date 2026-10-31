<?php

declare(strict_types=1);

namespace App\Customers\Customers\Enums;

enum CustomerChangeStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
