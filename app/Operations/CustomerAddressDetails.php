<?php

declare(strict_types=1);

namespace App\Operations;

final readonly class CustomerAddressDetails
{
    /**
     * @param  ?AddressLocation  $location  null leaves the stored neighborhood and coordinates untouched
     */
    public function __construct(
        public string $street,
        public ?string $apartment = null,
        public ?string $floor = null,
        public ?string $visualReference = null,
        public ?AddressLocation $location = null,
    ) {}
}
