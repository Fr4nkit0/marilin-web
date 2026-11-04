<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Catalog\Models\PaymentMethod;

/**
 * The admin's changes to the payment methods. The cash close counts by the fixed codes (`PaymentMethodCode`), so a
 * method is only renamed or disabled here: none is added and no code changes. A disabled method keeps reaching
 * the app, so the app hides it.
 */
final class PaymentMethodCatalog
{
    public function rename(PaymentMethod $method, string $name): void
    {
        $method->update(['name' => $name]);
    }

    public function setEnabled(PaymentMethod $method, bool $enabled): void
    {
        $method->update(['enabled' => $enabled]);
    }
}
