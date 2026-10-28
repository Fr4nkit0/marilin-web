<?php

declare(strict_types=1);

namespace App\Routes\Visits\Enums;

enum VisitStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
}
