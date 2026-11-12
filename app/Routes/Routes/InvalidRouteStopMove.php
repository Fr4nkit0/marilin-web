<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use DomainException;

/**
 * A move of route stops that cannot be done; nothing of it changes. The message is shown as is on the web, under its field.
 */
class InvalidRouteStopMove extends DomainException
{
    public function __construct(string $message, public readonly string $field = 'move')
    {
        parent::__construct($message);
    }
}
