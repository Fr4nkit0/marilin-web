<?php

declare(strict_types=1);

namespace App\Services\Sync;

/**
 * Why a record the app uploaded was accepted but left for the admin to review (ADR 0001).
 */
enum ReviewReason: string
{
    case ForeignCustomer = 'Customer is assigned to another driver.';
    case DisabledCustomer = 'Customer is disabled.';
}
