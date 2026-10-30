<?php

declare(strict_types=1);

use App\Catalog\Enums\PaymentMethodCode;
use App\Catalog\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the cash and transfer factory states use the codes the day figures read', function (): void {
    expect(PaymentMethod::factory()->efectivo()->create()->code)->toBe(PaymentMethodCode::Cash->value)
        ->and(PaymentMethod::factory()->transferencia()->create()->code)->toBe(PaymentMethodCode::Transfer->value);
});
