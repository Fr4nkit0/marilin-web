<?php

namespace App\Routes\Routes\Database\Factories;

use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    protected $model = RouteStop::class;

    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'customer_id' => Customer::factory(),
            'address_id' => CustomerAddress::factory(),
            'is_rescheduled_from' => null,
            'enabled' => true,
        ];
    }
}
