<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Controllers;

use App\Accounts\Models\Driver;
use App\Customers\Customers\DriverAssignment;
use App\Customers\Customers\Http\Requests\TransferCustomersRequest;
use App\Customers\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Transferir cartera": the admin hands a driver's customers to other drivers, some to one and some to another,
 * repeating the transfer from the same screen until the portfolio is split.
 */
class CustomerTransferController extends Controller
{
    public function show(Driver $driver): Response
    {
        $driver->load('user');

        $customers = Customer::query()
            ->with('primaryAddress')
            ->where('driver_id', $driver->id)
            ->where('enabled', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'street' => $customer->primaryAddress?->street,
                'neighborhood' => $customer->primaryAddress?->neighborhood,
            ]);

        $drivers = Driver::query()
            ->where('drivers.enabled', true)
            ->whereKeyNot($driver->id)
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->orderBy('users.name')
            ->orderBy('drivers.id')
            ->get(['drivers.id', 'users.name'])
            ->map(fn (Driver $destination): array => ['id' => $destination->id, 'name' => $destination->name]);

        return Inertia::render('Customers/Customers/Transfer', [
            'driver' => ['id' => $driver->id, 'name' => $driver->user?->name ?? 'Sin nombre'],
            'customers' => $customers,
            'drivers' => $drivers,
        ]);
    }

    public function store(TransferCustomersRequest $request, Driver $driver, DriverAssignment $assignment): RedirectResponse
    {
        $destination = Driver::query()->with('user')->findOrFail($request->integer('to_driver_id'));

        $moved = CustomerPreferenceController::asFieldErrors(
            fn (): int => $assignment->transfer($request->customerIds(), $driver->id, $destination->id, $request->user()),
        );

        $name = $destination->user?->name ?? 'Sin nombre';
        $message = $moved === 1 ? "1 cliente transferido a {$name}." : "{$moved} clientes transferidos a {$name}.";

        return redirect()->route('customers.transfer', $driver)->with('success', $message);
    }
}
