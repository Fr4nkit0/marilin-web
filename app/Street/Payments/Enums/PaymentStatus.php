<?php

declare(strict_types=1);

namespace App\Street\Payments\Enums;

enum PaymentStatus: string
{
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Annulled = 'ANNULLED';
}
