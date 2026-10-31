<?php

declare(strict_types=1);

namespace App\Sync;

use RuntimeException;

/**
 * Thrown inside a record's transaction when its data is invalid; the push answers it as rejected and rolls the record back.
 * The message is written in English as a stable key and translated (lang/es.json): the driver reads it in the app.
 */
class RejectedRecord extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replace  the placeholders of the message (e.g. `:days`)
     */
    public function __construct(string $message, array $replace = [])
    {
        parent::__construct(__($message, $replace));
    }
}
