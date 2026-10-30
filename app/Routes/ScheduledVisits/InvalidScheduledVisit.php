<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits;

use DomainException;

/**
 * A scheduled visit that cannot be scheduled or cancelled; nothing of it is saved.
 * The message is shown as is on the web.
 */
class InvalidScheduledVisit extends DomainException {}
