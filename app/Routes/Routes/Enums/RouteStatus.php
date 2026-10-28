<?php

declare(strict_types=1);

namespace App\Routes\Routes\Enums;

enum RouteStatus: string
{
    case Planned = 'PLANNED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
