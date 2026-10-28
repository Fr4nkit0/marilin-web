<?php

declare(strict_types=1);

namespace App\Routes\Routes\Enums;

enum StopStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
    case Rescheduled = 'RESCHEDULED';
}
