<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Route as DeliveryRoute;
use App\Models\User;
use App\Operations\StreetOperation;

class RegisterTruckLoadAction
{
    public function __construct(private readonly StreetOperation $operation) {}

    /**
     * Records one load of the truck (the first of the day or a reload) and adds it to the truck stock,
     * which reaches the driver's app on its next pull.
     *
     * @param  array<int, int>  $quantities  quantity keyed by product id
     */
    public function execute(DeliveryRoute $route, array $quantities, User $loadedBy): void
    {
        $this->operation->loadRoute($route, $quantities, $loadedBy);
    }
}
