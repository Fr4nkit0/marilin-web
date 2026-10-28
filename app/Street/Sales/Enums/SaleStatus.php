<?php

declare(strict_types=1);

namespace App\Street\Sales\Enums;

enum SaleStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Annulled = 'annulled';
}
