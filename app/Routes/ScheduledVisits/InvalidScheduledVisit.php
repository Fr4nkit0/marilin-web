<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits;

use DomainException;

/**
 * A scheduled visit that cannot be scheduled or cancelled; nothing of it is saved.
 * The message is shown as is on the web; the field names the input it is about, or none when it is about the customer or the visit.
 */
class InvalidScheduledVisit extends DomainException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
