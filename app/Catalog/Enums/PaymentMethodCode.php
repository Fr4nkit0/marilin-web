<?php

declare(strict_types=1);

namespace App\Catalog\Enums;

enum PaymentMethodCode: string
{
    case Cash = 'CASH';
    case Transfer = 'TRANSFER';
    case Card = 'CREDIT_CARD';
}
