<?php

namespace Database\Seeders;

use App\Models\ContainerMovement;
use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerAgreement;
use App\Models\CustomerBalance;
use App\Models\CustomerContainerBalance;
use App\Models\CustomerPreference;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Repartidor;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\RouteInventoryLoad;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitOutcome;
use App\Models\VisitStatusHistory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Core Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@marilin.com'],
            [
                'name' => 'Administrador Marilin',
                'username' => 'admin',
                'phone_number' => '+54 11 4000-0001',
                'is_super_user' => true,
                'enabled' => true,
                'password' => Hash::make('password'),
            ]
        );

        $driverUser1 = User::firstOrCreate(
            ['email' => 'juan.perez@marilin.com'],
            [
                'name' => 'Juan Pérez',
                'username' => 'jperez',
                'phone_number' => '+54 11 4000-0002',
                'is_super_user' => false,
                'enabled' => true,
                'password' => Hash::make('password'),
            ]
        );

        $driverUser2 = User::firstOrCreate(
            ['email' => 'carlos.gomez@marilin.com'],
            [
                'name' => 'Carlos Gómez',
                'username' => 'cgomez',
                'phone_number' => '+54 11 4000-0003',
                'is_super_user' => false,
                'enabled' => true,
                'password' => Hash::make('password'),
            ]
        );

        $repartidor1 = Repartidor::firstOrCreate(
            ['user_id' => $driverUser1->id],
            ['enabled' => true]
        );

        $repartidor2 = Repartidor::firstOrCreate(
            ['user_id' => $driverUser2->id],
            ['enabled' => true]
        );

        // 2. Container Types
        $bidon20L = ContainerType::firstOrCreate(
            ['code' => 'BIDON_20L'],
            [
                'description' => 'Bidón 20 Litros Retornable',
                'enabled' => true,
            ]
        );

        $sifon2L = ContainerType::firstOrCreate(
            ['code' => 'SIFON_2L'],
            [
                'description' => 'Sifón Soda 2 Litros Retornable',
                'enabled' => true,
            ]
        );

        // 3. Products
        $productAgua20L = Product::firstOrCreate(
            ['name' => 'Bidón de Agua Purificada 20L'],
            [
                'description' => 'Agua purificada y mineralizada de 20 litros en bidón retornable',
                'requires_container' => true,
                'container_type_id' => $bidon20L->id,
                'available_quantity' => 250.00,
                'enabled' => true,
            ]
        );

        $productSoda2L = Product::firstOrCreate(
            ['name' => 'Sifón de Soda 2L'],
            [
                'description' => 'Soda en sifón retornable de 2 litros',
                'requires_container' => true,
                'container_type_id' => $sifon2L->id,
                'available_quantity' => 180.00,
                'enabled' => true,
            ]
        );

        $productDispenser = Product::firstOrCreate(
            ['name' => 'Dispensador de Mesa Natural'],
            [
                'description' => 'Soporte con canilla para bidón de 20L',
                'requires_container' => false,
                'container_type_id' => null,
                'available_quantity' => 45.00,
                'enabled' => true,
            ]
        );

        // 4. Payment Methods
        $cashMethod = PaymentMethod::firstOrCreate(
            ['code' => 'EFECTIVO'],
            ['name' => 'Efectivo', 'enabled' => true]
        );

        $transferMethod = PaymentMethod::firstOrCreate(
            ['code' => 'TRANSFERENCIA'],
            ['name' => 'Transferencia Bancaria', 'enabled' => true]
        );

        $cardMethod = PaymentMethod::firstOrCreate(
            ['code' => 'TARJETA'],
            ['name' => 'Tarjeta de Débito/Crédito', 'enabled' => true]
        );

        // 5. Visit Outcomes
        $outcomeDelivered = VisitOutcome::firstOrCreate(
            ['name' => 'Entrega Exitosa'],
            ['triggers_reschedule' => false, 'enabled' => true]
        );

        $outcomeAbsent = VisitOutcome::firstOrCreate(
            ['name' => 'Cliente Ausente'],
            ['triggers_reschedule' => true, 'enabled' => true]
        );

        $outcomeRejected = VisitOutcome::firstOrCreate(
            ['name' => 'Rechazado por Cliente'],
            ['triggers_reschedule' => false, 'enabled' => true]
        );

        $outcomePostponed = VisitOutcome::firstOrCreate(
            ['name' => 'Reprogramado por Lluvia'],
            ['triggers_reschedule' => true, 'enabled' => true]
        );

        // 6. Customers, Addresses, Preferences & Agreements
        $customerData = [
            [
                'name' => 'Empresa Los Robles S.A.',
                'phone' => '+54 11 5555-1001',
                'repartidor_id' => $repartidor1->id,
                'street' => 'Av. Corrientes 1234',
                'apartment' => '4B',
                'lat' => -34.603722,
                'lng' => -58.381592,
                'bidon_balance' => 6,
                'unit_price_agua' => 1800.00,
            ],
            [
                'name' => 'Consultorio Dr. Martínez',
                'phone' => '+54 11 5555-1002',
                'repartidor_id' => $repartidor1->id,
                'street' => 'San Martín 567',
                'apartment' => null,
                'lat' => -34.608056,
                'lng' => -58.372778,
                'bidon_balance' => 2,
                'unit_price_agua' => 2000.00,
            ],
            [
                'name' => 'Gimnasio Fitness Hub',
                'phone' => '+54 11 5555-1003',
                'repartidor_id' => $repartidor2->id,
                'street' => 'Av. Santa Fe 3450',
                'apartment' => null,
                'lat' => -34.588000,
                'lng' => -58.410000,
                'bidon_balance' => 10,
                'unit_price_agua' => 1700.00,
            ],
        ];

        foreach ($customerData as $data) {
            $customer = Customer::firstOrCreate(
                ['name' => $data['name']],
                [
                    'repartidor_id' => $data['repartidor_id'],
                    'phone_number' => $data['phone'],
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            CustomerAddress::firstOrCreate(
                ['customer_id' => $customer->id, 'street' => $data['street']],
                [
                    'apartment' => $data['apartment'],
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'is_primary' => true,
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            CustomerPreference::firstOrCreate(
                ['customer_id' => $customer->id, 'day_of_week' => 1],
                [
                    'time_window_start' => '09:00:00',
                    'time_window_end' => '13:00:00',
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            CustomerContainerBalance::firstOrCreate(
                ['customer_id' => $customer->id, 'container_type_id' => $bidon20L->id],
                [
                    'balance' => $data['bidon_balance'],
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            $agreement = CustomerAgreement::firstOrCreate(
                ['customer_id' => $customer->id, 'product_id' => $productAgua20L->id],
                [
                    'unit_price' => $data['unit_price_agua'],
                    'valid_from' => now()->startOfYear()->toDateString(),
                    'reason' => 'Tarifa corporativa anual',
                    'created_by' => $admin->id,
                ]
            );
        }

        // 7. Route and Operations for today
        $todayRoute = Route::firstOrCreate(
            [
                'assigned_repartidor_id' => $repartidor1->id,
                'date' => now()->toDateString(),
            ],
            [
                'status' => 'IN_PROGRESS',
                'started_at' => now()->startOfDay()->addHours(8),
                'enabled' => true,
            ]
        );

        RouteInventory::firstOrCreate(
            ['route_id' => $todayRoute->id, 'product_id' => $productAgua20L->id],
            [
                'current_quantity' => 40.00,
                'created_by' => $admin->id,
                'enabled' => true,
            ]
        );

        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $todayRoute->id, 'product_id' => $productAgua20L->id, 'quantity' => 50.00],
            [
                'loaded_at' => now()->startOfDay()->addHours(7)->addMinutes(30),
                'created_by' => $admin->id,
            ]
        );

        $firstCustomer = Customer::where('name', 'Empresa Los Robles S.A.')->first();
        $firstAddress = $firstCustomer->primaryAddress ?? CustomerAddress::where('customer_id', $firstCustomer->id)->first();

        $stop1 = RouteStop::firstOrCreate(
            ['route_id' => $todayRoute->id, 'customer_id' => $firstCustomer->id],
            [
                'address_id' => $firstAddress->id,
                'enabled' => true,
            ]
        );

        $visit1 = Visit::firstOrCreate(
            ['route_stop_id' => $stop1->id],
            [
                'customer_id' => $firstCustomer->id,
                'repartidor_id' => $repartidor1->id,
                'outcome_id' => $outcomeDelivered->id,
                'arrived_at' => now()->startOfDay()->addHours(9)->addMinutes(15),
                'latitude' => -34.603722,
                'longitude' => -58.381592,
                'status' => 'FINISHED',
                'notes' => 'Entregados 4 bidones llenos y retirados 4 vacíos.',
                'created_by' => $driverUser1->id,
                'enabled' => true,
            ]
        );

        VisitStatusHistory::firstOrCreate(
            ['visit_id' => $visit1->id, 'status' => 'FINISHED'],
            [
                'outcome_id' => $outcomeDelivered->id,
                'notes' => 'Completada',
                'created_by' => $driverUser1->id,
                'enabled' => true,
            ]
        );

        // Container Movements for visit
        ContainerMovement::firstOrCreate(
            ['visit_id' => $visit1->id, 'movement_type' => 'DELIVERED'],
            [
                'customer_id' => $firstCustomer->id,
                'container_type_id' => $bidon20L->id,
                'quantity' => 4,
                'direction' => 1,
                'reason' => 'Entrega rutinaria de bidones',
                'created_by' => $driverUser1->id,
                'enabled' => true,
            ]
        );

        ContainerMovement::firstOrCreate(
            ['visit_id' => $visit1->id, 'movement_type' => 'RETURNED'],
            [
                'customer_id' => $firstCustomer->id,
                'container_type_id' => $bidon20L->id,
                'quantity' => 4,
                'direction' => -1,
                'reason' => 'Retiro de bidones vacíos',
                'created_by' => $driverUser1->id,
                'enabled' => true,
            ]
        );

        // Sale and payment
        $saleTotal = 4 * 1800.00;
        $sale1 = Sale::firstOrCreate(
            ['customer_id' => $firstCustomer->id, 'visit_id' => $visit1->id],
            [
                'date' => now()->startOfDay()->addHours(9)->addMinutes(20),
                'total' => $saleTotal,
                'status' => 'confirmed',
                'is_credit_sale' => false,
                'notes' => 'Venta regular con precio acordado',
                'created_by' => $driverUser1->id,
                'enabled' => true,
            ]
        );

        SaleDetail::firstOrCreate(
            ['sale_id' => $sale1->id, 'product_id' => $productAgua20L->id],
            [
                'quantity' => 4.00,
                'unit_price' => 1800.00,
            ]
        );

        $tx1 = Transaction::create([
            'type' => 'IN',
            'amount' => $saleTotal,
        ]);

        $payment1 = Payment::firstOrCreate(
            ['sale_id' => $sale1->id],
            [
                'transaction_id' => $tx1->id,
                'customer_id' => $firstCustomer->id,
                'visit_id' => $visit1->id,
                'payment_method_id' => $cashMethod->id,
                'amount' => $saleTotal,
                'received_by' => $driverUser1->id,
                'paid_at' => now()->startOfDay()->addHours(9)->addMinutes(25),
                'status' => 'CONFIRMED',
            ]
        );

        CustomerBalance::firstOrCreate(
            ['customer_id' => $firstCustomer->id],
            [
                'current_balance' => 0.00,
                'created_by' => $admin->id,
                'enabled' => true,
            ]
        );
    }
}
