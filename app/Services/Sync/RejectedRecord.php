<?php

declare(strict_types=1);

namespace App\Services\Sync;

use RuntimeException;

/**
 * Thrown inside a record's transaction when its data is invalid; the push answers it as rejected and rolls the record back.
 */
class RejectedRecord extends RuntimeException {}
