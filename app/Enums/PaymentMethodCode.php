<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodCode: string
{
    case Cash = 'CASH';
    case Transfer = 'TRANSFER';
}
