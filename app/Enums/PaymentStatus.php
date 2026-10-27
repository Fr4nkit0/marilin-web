<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Annulled = 'ANNULLED';
}
