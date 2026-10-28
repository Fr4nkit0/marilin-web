<?php

declare(strict_types=1);

namespace App\Operations;

final readonly class AddressLocation
{
    public function __construct(
        public ?string $neighborhood = null,
        public string|int|float|null $latitude = null,
        public string|int|float|null $longitude = null,
    ) {}
}
