<?php

declare(strict_types=1);

namespace App\Street\Containers\Enums;

enum ContainerMovementType: string
{
    case Delivered = 'DELIVERED';
    case Returned = 'RETURNED';
    case Adjustment = 'ADJUSTMENT';
    case Lost = 'LOST';
    case Damaged = 'DAMAGED';
}
