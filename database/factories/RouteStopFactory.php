<?php

namespace Database\Factories;

use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Models\Route;
use App\Models\RouteStop;
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
