<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits\Enums;

/**
 * The stored state; what the admin reads is ScheduledVisitDisplayStatus, derived from its stop.
 */
enum ScheduledVisitStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Cancelled = 'CANCELLED';
}
