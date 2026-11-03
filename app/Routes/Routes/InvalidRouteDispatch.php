<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use DomainException;

/**
 * A route that cannot be dispatched; nothing of it changes. The message is shown as is on the web.
 */
class InvalidRouteDispatch extends DomainException {}
