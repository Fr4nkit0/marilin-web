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
use App\Models\CustomerAccountEntry;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Route as DeliveryRoute;
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
use Carbon\Carbon;
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
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // 1. Core Users (Admin & Repartidores/Drivers)
        $admin = User::firstOrCreate(
            ['email' => 'admin@marilin.com'],
            [
                'name' => 'Administrador Marilin',
                'username' => 'admin',
                'phone_number' => '+54 388 400-0001',
                'is_super_user' => true,
                'enabled' => true,
                'password' => Hash::make('password'),
            ]
        );

        $driverUsers = [
            [
                'email' => 'juan.perez@marilin.com',
                'name' => 'Juan Pérez',
                'username' => 'jperez',
                'phone' => '+54 388 421-1001',
            ],
            [
                'email' => 'carlos.gomez@marilin.com',
                'name' => 'Carlos Gómez',
                'username' => 'cgomez',
                'phone' => '+54 388 421-1002',
            ],
            [
                'email' => 'marcos.morales@marilin.com',
                'name' => 'Marcos Morales',
                'username' => 'mmorales',
                'phone' => '+54 388 421-1003',
            ],
            [
                'email' => 'esteban.benitez@marilin.com',
                'name' => 'Esteban Benítez',
                'username' => 'ebenitez',
                'phone' => '+54 388 421-1004',
            ],
        ];

        $drivers = [];
        foreach ($driverUsers as $du) {
            $user = User::firstOrCreate(
                ['email' => $du['email']],
                [
                    'name' => $du['name'],
                    'username' => $du['username'],
                    'phone_number' => $du['phone'],
                    'is_super_user' => false,
                    'enabled' => true,
                    'password' => Hash::make('password'),
                ]
            );

            $drv = Driver::firstOrCreate(
                ['user_id' => $user->id],
                ['enabled' => true]
            );

            $drivers[] = $drv;
        }

        $repJuan = $drivers[0];
        $repCarlos = $drivers[1];
        $repMarcos = $drivers[2];
        $repEsteban = $drivers[3];

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
                'available_quantity' => 500.00,
                'enabled' => true,
            ]
        );

        $productSoda2L = Product::firstOrCreate(
            ['name' => 'Sifón de Soda 2L'],
            [
                'description' => 'Soda en sifón retornable de 2 litros',
                'requires_container' => true,
                'container_type_id' => $sifon2L->id,
                'available_quantity' => 350.00,
                'enabled' => true,
            ]
        );

        $productDispenser = Product::firstOrCreate(
            ['name' => 'Dispensador de Mesa Natural'],
            [
                'description' => 'Soporte con canilla para bidón de 20L',
                'requires_container' => false,
                'container_type_id' => null,
                'available_quantity' => 60.00,
                'enabled' => true,
            ]
        );

        // 4. Payment Methods (Standardized Codes)
        $cashMethod = PaymentMethod::firstOrCreate(
            ['code' => 'CASH'],
            ['name' => 'Efectivo', 'enabled' => true]
        );

        $transferMethod = PaymentMethod::firstOrCreate(
            ['code' => 'TRANSFER'],
            ['name' => 'Transferencia Bancaria', 'enabled' => true]
        );

        $cardMethod = PaymentMethod::firstOrCreate(
            ['code' => 'CREDIT_CARD'],
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

        $outcomePostponed = VisitOutcome::firstOrCreate(
            ['name' => 'Reprogramado por Lluvia'],
            ['triggers_reschedule' => true, 'enabled' => true]
        );

        // 6. Customers across San Salvador de Jujuy & Palpalá
        $customersConfig = [
            // A. Empresas & Instituciones (Deudores / Cta Cte)
            [
                'name' => 'Sanatorio Nuestra Señora del Rosario',
                'phone' => '+54 388 422-3000',
                'rep' => $repJuan,
                'street' => 'Belgrano 340',
                'ref' => 'Frente a Plaza Belgrano',
                'lat' => -24.18578,
                'lng' => -65.29947,
                'bidon_balance' => 18,
                'sifon_balance' => 4,
                'price_agua' => 1800.00,
                'initial_debt' => 45000.00,
            ],
            [
                'name' => 'Clínica Los Lapachos',
                'phone' => '+54 388 423-4500',
                'rep' => $repJuan,
                'street' => 'Av. Dr. Vidal 850',
                'ref' => 'Barrio Ciudad de Nieva',
                'lat' => -24.17890,
                'lng' => -65.31200,
                'bidon_balance' => 12,
                'sifon_balance' => 0,
                'price_agua' => 1850.00,
                'initial_debt' => 28500.00,
            ],
            [
                'name' => 'Hotel Altos de la Viña',
                'phone' => '+54 388 426-1500',
                'rep' => $repJuan,
                'street' => 'Pasquini López 450',
                'ref' => 'Alto La Viña',
                'lat' => -24.19500,
                'lng' => -65.27500,
                'bidon_balance' => 15,
                'sifon_balance' => 8,
                'price_agua' => 1900.00,
                'initial_debt' => 34000.00,
            ],
            [
                'name' => 'Distribuidora San Salvador S.R.L.',
                'phone' => '+54 388 425-8800',
                'rep' => $repCarlos,
                'street' => 'Av. Fuerza Aérea 1200',
                'ref' => 'Alto Comedero',
                'lat' => -24.23500,
                'lng' => -65.26000,
                'bidon_balance' => 25,
                'sifon_balance' => 12,
                'price_agua' => 1750.00,
                'initial_debt' => 62000.00,
            ],
            [
                'name' => 'Estudio Jurídico Alvear & Asoc.',
                'phone' => '+54 388 422-9900',
                'rep' => $repJuan,
                'street' => 'Alvear 740',
                'ref' => '1er Piso Oficina B',
                'lat' => -24.18700,
                'lng' => -65.30200,
                'bidon_balance' => 4,
                'sifon_balance' => 2,
                'price_agua' => 2000.00,
                'initial_debt' => 9200.00,
            ],
            [
                'name' => 'Taller Mecánico Palpalá Motors',
                'phone' => '+54 388 427-4400',
                'rep' => $repMarcos,
                'street' => 'Av. Catalano 320',
                'ref' => 'Palpalá Centro',
                'lat' => -24.25600,
                'lng' => -65.21200,
                'bidon_balance' => 6,
                'sifon_balance' => 0,
                'price_agua' => 1900.00,
                'initial_debt' => 11400.00,
            ],

            // B. Salud & Gimnasios
            [
                'name' => 'Gimnasio Go Fitness Jujuy',
                'phone' => '+54 388 501-2233',
                'rep' => $repCarlos,
                'street' => 'Gorriti 280',
                'ref' => 'Barrio Gorriti',
                'lat' => -24.19200,
                'lng' => -65.29500,
                'bidon_balance' => 14,
                'sifon_balance' => 0,
                'price_agua' => 1800.00,
                'initial_debt' => 16800.00,
            ],
            [
                'name' => 'Crossfit Palpalá Box',
                'phone' => '+54 388 502-3344',
                'rep' => $repMarcos,
                'street' => 'Rivadavia 510',
                'ref' => 'Barrio Carolina, Palpalá',
                'lat' => -24.25800,
                'lng' => -65.21500,
                'bidon_balance' => 8,
                'sifon_balance' => 0,
                'price_agua' => 1900.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Laboratorio BioJujuy',
                'phone' => '+54 388 424-1122',
                'rep' => $repJuan,
                'street' => 'Coronel Puch 540',
                'ref' => 'Centro',
                'lat' => -24.18400,
                'lng' => -65.30500,
                'bidon_balance' => 5,
                'sifon_balance' => 0,
                'price_agua' => 2000.00,
                'initial_debt' => 0.00,
            ],

            // C. Comercios & Gastronomía
            [
                'name' => 'Panadería y Confitería La Unión',
                'phone' => '+54 388 425-6677',
                'rep' => $repCarlos,
                'street' => 'Almirante Brown 1450',
                'ref' => 'San Pedrito',
                'lat' => -24.20500,
                'lng' => -65.28900,
                'bidon_balance' => 4,
                'sifon_balance' => 8,
                'price_agua' => 1950.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Café Martínez Belgrano',
                'phone' => '+54 388 422-8811',
                'rep' => $repJuan,
                'street' => 'Belgrano 680',
                'ref' => 'Peatonal Belgrano',
                'lat' => -24.18650,
                'lng' => -65.30050,
                'bidon_balance' => 8,
                'sifon_balance' => 12,
                'price_agua' => 1900.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Restaurante Viracocha',
                'phone' => '+54 388 423-7744',
                'rep' => $repJuan,
                'street' => 'Independencia 820',
                'ref' => 'Centro Histórico',
                'lat' => -24.18800,
                'lng' => -65.30400,
                'bidon_balance' => 6,
                'sifon_balance' => 10,
                'price_agua' => 1900.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Kiosco El Sol',
                'phone' => '+54 388 510-4455',
                'rep' => $repCarlos,
                'street' => 'Av. Párroco Marshke 980',
                'ref' => 'Frente a parada de colectivos',
                'lat' => -24.20200,
                'lng' => -65.29200,
                'bidon_balance' => 3,
                'sifon_balance' => 4,
                'price_agua' => 2000.00,
                'initial_debt' => 0.00,
            ],

            // D. Clientes Residenciales
            [
                'name' => 'Familia Calisaya',
                'phone' => '+54 388 411-2233',
                'rep' => $repJuan,
                'street' => 'Iturbe 245',
                'ref' => 'Ciudad de Nieva',
                'lat' => -24.18100,
                'lng' => -65.30800,
                'bidon_balance' => 3,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Familia Tolaba',
                'phone' => '+54 388 412-3344',
                'rep' => $repJuan,
                'street' => 'Los Álamos 120',
                'ref' => 'Los Perales',
                'lat' => -24.17500,
                'lng' => -65.32000,
                'bidon_balance' => 4,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 4200.00,
            ],
            [
                'name' => 'Familia Ramírez',
                'phone' => '+54 388 413-4455',
                'rep' => $repCarlos,
                'street' => 'Eugenio Tello 630',
                'ref' => 'San Pedrito',
                'lat' => -24.20800,
                'lng' => -65.28500,
                'bidon_balance' => 2,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Familia Morales',
                'phone' => '+54 388 414-5566',
                'rep' => $repMarcos,
                'street' => 'Congreso 420',
                'ref' => 'Barrio San José, Palpalá',
                'lat' => -24.26100,
                'lng' => -65.21000,
                'bidon_balance' => 3,
                'sifon_balance' => 0,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],
        ];

        $createdCustomers = [];
        foreach ($customersConfig as $cfg) {
            $customer = Customer::firstOrCreate(
                ['name' => $cfg['name']],
                [
                    'driver_id' => $cfg['rep']->id,
                    'phone_number' => $cfg['phone'],
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            CustomerAddress::firstOrCreate(
                ['customer_id' => $customer->id, 'street' => $cfg['street']],
                [
                    'visual_reference' => $cfg['ref'],
                    'latitude' => $cfg['lat'],
                    'longitude' => $cfg['lng'],
                    'is_primary' => true,
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            CustomerPreference::firstOrCreate(
                ['customer_id' => $customer->id, 'day_of_week' => 1],
                [
                    'time_window_start' => '08:30:00',
                    'time_window_end' => '13:00:00',
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            // Container balances
            CustomerContainerBalance::firstOrCreate(
                ['customer_id' => $customer->id, 'container_type_id' => $bidon20L->id],
                [
                    'balance' => $cfg['bidon_balance'],
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            if ($cfg['sifon_balance'] > 0) {
                CustomerContainerBalance::firstOrCreate(
                    ['customer_id' => $customer->id, 'container_type_id' => $sifon2L->id],
                    [
                        'balance' => $cfg['sifon_balance'],
                        'created_by' => $admin->id,
                        'enabled' => true,
                    ]
                );
            }

            // Agreement
            CustomerAgreement::firstOrCreate(
                ['customer_id' => $customer->id, 'product_id' => $productAgua20L->id],
                [
                    'unit_price' => $cfg['price_agua'],
                    'valid_from' => now(),
                    'created_by' => $admin->id,
                    
                ]
            );

            // Initial debt / CustomerBalance
            CustomerBalance::firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'current_balance' => $cfg['initial_debt'],
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            // If has debt, create initial ledger account entry
            if ($cfg['initial_debt'] > 0) {
                CustomerAccountEntry::firstOrCreate(
                    ['customer_id' => $customer->id, 'description' => 'Saldo deudor histórico anterior'],
                    [
                        'entry_type' => 'ADJUSTMENT',
                        'direction' => 1,
                        'amount' => $cfg['initial_debt'],
                        'created_by' => $admin->id,
                        'created_at' => $yesterday->copy()->startOfDay()->addHours(10),
                    ]
                );
            }

            $createdCustomers[$cfg['name']] = $customer;
        }

        // 7. Yesterday Sales History (To generate positive salesDelta trend on dashboard)
        $yesterdaySales = [
            ['customer' => 'Sanatorio Nuestra Señora del Rosario', 'total' => 18000.00],
            ['customer' => 'Hotel Altos de la Viña', 'total' => 15200.00],
            ['customer' => 'Café Martínez Belgrano', 'total' => 11400.00],
            ['customer' => 'Panadería y Confitería La Unión', 'total' => 9750.00],
        ];

        foreach ($yesterdaySales as $ys) {
            $cust = $createdCustomers[$ys['customer']] ?? null;
            if ($cust) {
                Sale::firstOrCreate(
                    ['customer_id' => $cust->id, 'date' => $yesterday->copy()->setHour(11)],
                    [
                        'total' => $ys['total'],
                        'status' => 'confirmed',
                        'is_credit_sale' => false,
                        'notes' => 'Venta jornada previa',
                        'created_by' => $admin->id,
                        'enabled' => true,
                    ]
                );
            }
        }

        // 8. TODAY'S ROUTES & TRUCK LOADS

        // --- ROUTE 1: Juan Pérez (IN_PROGRESS - Centro & Nieva) ---
        $routeJuan = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repJuan->id, 'date' => $today->toDateString()],
            [
                'status' => 'IN_PROGRESS',
                'started_at' => $today->copy()->setHour(8)->setMinute(15),
                'enabled' => true,
            ]
        );

        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productAgua20L->id],
            ['quantity' => 60.00, 'created_by' => $admin->id]
        );
        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productSoda2L->id],
            ['quantity' => 30.00, 'created_by' => $admin->id]
        );

        RouteInventory::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productAgua20L->id],
            ['current_quantity' => 38.00, 'created_by' => $admin->id, 'enabled' => true]
        );
        RouteInventory::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productSoda2L->id],
            ['current_quantity' => 20.00, 'created_by' => $admin->id, 'enabled' => true]
        );

        // Stops for Juan
        $juanStops = [
            [
                'customer' => 'Café Martínez Belgrano',
                'status' => 'FINISHED',
                'time' => '08:45',
                'qty_agua' => 4,
                'qty_soda' => 6,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Sanatorio Nuestra Señora del Rosario',
                'status' => 'FINISHED',
                'time' => '09:20',
                'qty_agua' => 8,
                'qty_soda' => 0,
                'method' => null,
                'is_credit' => true, // Crédito ruta
            ],
            [
                'customer' => 'Laboratorio BioJujuy',
                'status' => 'FINISHED',
                'time' => '10:05',
                'qty_agua' => 3,
                'qty_soda' => 0,
                'method' => $transferMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Restaurante Viracocha',
                'status' => 'FINISHED',
                'time' => '10:40',
                'qty_agua' => 4,
                'qty_soda' => 4,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Clínica Los Lapachos',
                'status' => 'FINISHED',
                'time' => '11:15',
                'qty_agua' => 3,
                'qty_soda' => 0,
                'method' => $transferMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Familia Calisaya',
                'status' => 'IN_PROGRESS',
                'time' => '11:50',
                'qty_agua' => 2,
                'qty_soda' => 0,
                'method' => null,
                'is_credit' => false,
            ],
            [
                'customer' => 'Familia Tolaba',
                'status' => 'PENDING',
                'time' => null,
                'qty_agua' => 2,
                'qty_soda' => 0,
                'method' => null,
                'is_credit' => false,
            ],
            [
                'customer' => 'Hotel Altos de la Viña',
                'status' => 'PENDING',
                'time' => null,
                'qty_agua' => 6,
                'qty_soda' => 4,
                'method' => null,
                'is_credit' => false,
            ],
        ];

        foreach ($juanStops as $s) {
            $cust = $createdCustomers[$s['customer']];
            $addr = $cust->primaryAddress;

            $stop = RouteStop::firstOrCreate(
                ['route_id' => $routeJuan->id, 'customer_id' => $cust->id],
                ['address_id' => $addr->id, 'enabled' => true]
            );

            if ($s['status'] !== 'PENDING') {
                $visit = Visit::firstOrCreate(
                    ['route_stop_id' => $stop->id],
                    [
                        'customer_id' => $cust->id,
                        'driver_id' => $repJuan->id,
                        'outcome_id' => $outcomeDelivered->id,
                        'arrived_at' => $s['time'] ? $today->copy()->setHour((int) explode(':', $s['time'])[0])->setMinute((int) explode(':', $s['time'])[1]) : null,
                        'latitude' => $addr->latitude,
                        'longitude' => $addr->longitude,
                        'status' => $s['status'],
                        'notes' => 'Visita registrada',
                        'created_by' => $repJuan->user_id,
                        'enabled' => true,
                    ]
                );

                if ($s['status'] === 'FINISHED') {
                    $totalSale = ($s['qty_agua'] * 1900.00) + ($s['qty_soda'] * 1000.00);
                    $sale = Sale::firstOrCreate(
                        ['customer_id' => $cust->id, 'visit_id' => $visit->id],
                        [
                            'date' => $visit->arrived_at,
                            'total' => $totalSale,
                            'status' => 'confirmed',
                            'is_credit_sale' => $s['is_credit'],
                            'notes' => 'Venta en parada',
                            'created_by' => $repJuan->user_id,
                            'enabled' => true,
                        ]
                    );

                    if ($s['qty_agua'] > 0) {
                        SaleDetail::firstOrCreate(
                            ['sale_id' => $sale->id, 'product_id' => $productAgua20L->id],
                            ['quantity' => $s['qty_agua'], 'unit_price' => 1900.00]
                        );
                    }
                    if ($s['qty_soda'] > 0) {
                        SaleDetail::firstOrCreate(
                            ['sale_id' => $sale->id, 'product_id' => $productSoda2L->id],
                            ['quantity' => $s['qty_soda'], 'unit_price' => 1000.00]
                        );
                    }

                    // Container movements
                    ContainerMovement::firstOrCreate(
                        ['visit_id' => $visit->id, 'movement_type' => 'DELIVERED'],
                        [
                            'customer_id' => $cust->id,
                            'container_type_id' => $bidon20L->id,
                            'quantity' => $s['qty_agua'],
                            'direction' => 1,
                            'reason' => 'Entrega de bidones',
                            'created_by' => $repJuan->user_id,
                            'enabled' => true,
                        ]
                    );
                    ContainerMovement::firstOrCreate(
                        ['visit_id' => $visit->id, 'movement_type' => 'RETURNED'],
                        [
                            'customer_id' => $cust->id,
                            'container_type_id' => $bidon20L->id,
                            'quantity' => $s['qty_agua'],
                            'direction' => -1,
                            'reason' => 'Retiro de bidones vacíos',
                            'created_by' => $repJuan->user_id,
                            'enabled' => true,
                        ]
                    );

                    // If not credit sale, register Payment and Account Entry
                    if (!$s['is_credit'] && $s['method']) {
                        $tx = Transaction::create(['type' => 'IN', 'amount' => $totalSale]);
                        $pm = Payment::firstOrCreate(
                            ['sale_id' => $sale->id],
                            [
                                'transaction_id' => $tx->id,
                                'customer_id' => $cust->id,
                                'visit_id' => $visit->id,
                                'payment_method_id' => $s['method']->id,
                                'amount' => $totalSale,
                                'received_by' => $repJuan->user_id,
                                'paid_at' => $visit->arrived_at,
                                'status' => 'CONFIRMED',
                            ]
                        );

                        CustomerAccountEntry::firstOrCreate(
                            ['sale_id' => $sale->id, 'entry_type' => 'SALE'],
                            [
                                'customer_id' => $cust->id,
                                'direction' => 1,
                                'amount' => $totalSale,
                                'description' => "Venta {$s['qty_agua']}x Bidones 20L",
                                'created_by' => $repJuan->user_id,
                                'created_at' => $visit->arrived_at,
                            ]
                        );

                        CustomerAccountEntry::firstOrCreate(
                            ['payment_id' => $pm->id, 'entry_type' => 'PAYMENT'],
                            [
                                'customer_id' => $cust->id,
                                'direction' => -1,
                                'amount' => $totalSale,
                                'description' => "Cobro {$s['method']->name}",
                                'created_by' => $repJuan->user_id,
                                'created_at' => $visit->arrived_at,
                            ]
                        );
                    } else {
                        // Credit sale adds to debt
                        CustomerAccountEntry::firstOrCreate(
                            ['sale_id' => $sale->id, 'entry_type' => 'SALE'],
                            [
                                'customer_id' => $cust->id,
                                'direction' => 1,
                                'amount' => $totalSale,
                                'description' => "Venta a Crédito {$s['qty_agua']}x Bidones 20L",
                                'created_by' => $repJuan->user_id,
                                'created_at' => $visit->arrived_at,
                            ]
                        );
                    }
                }
            }
        }

        // --- ROUTE 2: Carlos Gómez (COMPLETED - San Pedrito & Alto Comedero) ---
        $routeCarlos = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repCarlos->id, 'date' => $today->toDateString()],
            [
                'status' => 'COMPLETED',
                'started_at' => $today->copy()->setHour(7)->setMinute(45),
                'completed_at' => $today->copy()->setHour(14)->setMinute(30),
                'enabled' => true,
            ]
        );

        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeCarlos->id, 'product_id' => $productAgua20L->id],
            ['quantity' => 50.00, 'created_by' => $admin->id]
        );
        RouteInventory::firstOrCreate(
            ['route_id' => $routeCarlos->id, 'product_id' => $productAgua20L->id],
            ['current_quantity' => 12.00, 'created_by' => $admin->id, 'enabled' => true]
        );

        $carlosStops = [
            [
                'customer' => 'Panadería y Confitería La Unión',
                'status' => 'FINISHED',
                'time' => '08:15',
                'qty_agua' => 4,
                'qty_soda' => 6,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Gimnasio Go Fitness Jujuy',
                'status' => 'FINISHED',
                'time' => '09:00',
                'qty_agua' => 10,
                'qty_soda' => 0,
                'method' => $transferMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Kiosco El Sol',
                'status' => 'FINISHED',
                'time' => '09:45',
                'qty_agua' => 4,
                'qty_soda' => 4,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Distribuidora San Salvador S.R.L.',
                'status' => 'FINISHED',
                'time' => '10:30',
                'qty_agua' => 15,
                'qty_soda' => 6,
                'method' => null,
                'is_credit' => true,
            ],
            [
                'customer' => 'Familia Ramírez',
                'status' => 'FINISHED',
                'time' => '11:15',
                'qty_agua' => 3,
                'qty_soda' => 2,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
        ];

        foreach ($carlosStops as $s) {
            $cust = $createdCustomers[$s['customer']];
            $addr = $cust->primaryAddress;

            $stop = RouteStop::firstOrCreate(
                ['route_id' => $routeCarlos->id, 'customer_id' => $cust->id],
                ['address_id' => $addr->id, 'enabled' => true]
            );

            $visit = Visit::firstOrCreate(
                ['route_stop_id' => $stop->id],
                [
                    'customer_id' => $cust->id,
                    'driver_id' => $repCarlos->id,
                    'outcome_id' => $outcomeDelivered->id,
                    'arrived_at' => $today->copy()->setHour((int) explode(':', $s['time'])[0])->setMinute((int) explode(':', $s['time'])[1]),
                    'latitude' => $addr->latitude,
                    'longitude' => $addr->longitude,
                    'status' => 'FINISHED',
                    'notes' => 'Entrega completada',
                    'created_by' => $repCarlos->user_id,
                    'enabled' => true,
                ]
            );

            $totalSale = ($s['qty_agua'] * 1800.00) + ($s['qty_soda'] * 1000.00);
            $sale = Sale::firstOrCreate(
                ['customer_id' => $cust->id, 'visit_id' => $visit->id],
                [
                    'date' => $visit->arrived_at,
                    'total' => $totalSale,
                    'status' => 'confirmed',
                    'is_credit_sale' => $s['is_credit'],
                    'created_by' => $repCarlos->user_id,
                    'enabled' => true,
                ]
            );

            if ($s['qty_agua'] > 0) {
                SaleDetail::firstOrCreate(
                    ['sale_id' => $sale->id, 'product_id' => $productAgua20L->id],
                    ['quantity' => $s['qty_agua'], 'unit_price' => 1800.00]
                );
            }

            ContainerMovement::firstOrCreate(
                ['visit_id' => $visit->id, 'movement_type' => 'DELIVERED'],
                [
                    'customer_id' => $cust->id,
                    'container_type_id' => $bidon20L->id,
                    'quantity' => $s['qty_agua'],
                    'direction' => 1,
                    'reason' => 'Entrega de bidones',
                    'created_by' => $repCarlos->user_id,
                    'enabled' => true,
                ]
            );
            ContainerMovement::firstOrCreate(
                ['visit_id' => $visit->id, 'movement_type' => 'RETURNED'],
                [
                    'customer_id' => $cust->id,
                    'container_type_id' => $bidon20L->id,
                    'quantity' => $s['qty_agua'],
                    'direction' => -1,
                    'reason' => 'Retiro de bidones vacíos',
                    'created_by' => $repCarlos->user_id,
                    'enabled' => true,
                ]
            );

            if (!$s['is_credit'] && $s['method']) {
                $tx = Transaction::create(['type' => 'IN', 'amount' => $totalSale]);
                Payment::firstOrCreate(
                    ['sale_id' => $sale->id],
                    [
                        'transaction_id' => $tx->id,
                        'customer_id' => $cust->id,
                        'visit_id' => $visit->id,
                        'payment_method_id' => $s['method']->id,
                        'amount' => $totalSale,
                        'received_by' => $repCarlos->user_id,
                        'paid_at' => $visit->arrived_at,
                        'status' => 'CONFIRMED',
                    ]
                );
            }
        }

        // --- ROUTE 3: Marcos Morales (IN_PROGRESS - Palpalá) ---
        $routeMarcos = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repMarcos->id, 'date' => $today->toDateString()],
            [
                'status' => 'IN_PROGRESS',
                'started_at' => $today->copy()->setHour(8)->setMinute(30),
                'enabled' => true,
            ]
        );

        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeMarcos->id, 'product_id' => $productAgua20L->id],
            ['quantity' => 40.00, 'created_by' => $admin->id]
        );
        RouteInventory::firstOrCreate(
            ['route_id' => $routeMarcos->id, 'product_id' => $productAgua20L->id],
            ['current_quantity' => 26.00, 'created_by' => $admin->id, 'enabled' => true]
        );

        $marcosStops = [
            [
                'customer' => 'Crossfit Palpalá Box',
                'status' => 'FINISHED',
                'time' => '09:10',
                'qty_agua' => 6,
                'qty_soda' => 0,
                'method' => $transferMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Taller Mecánico Palpalá Motors',
                'status' => 'FINISHED',
                'time' => '10:00',
                'qty_agua' => 4,
                'qty_soda' => 0,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Familia Morales',
                'status' => 'FINISHED',
                'time' => '10:45',
                'qty_agua' => 4,
                'qty_soda' => 0,
                'method' => $cashMethod,
                'is_credit' => false,
            ],
        ];

        foreach ($marcosStops as $s) {
            $cust = $createdCustomers[$s['customer']];
            $addr = $cust->primaryAddress;

            $stop = RouteStop::firstOrCreate(
                ['route_id' => $routeMarcos->id, 'customer_id' => $cust->id],
                ['address_id' => $addr->id, 'enabled' => true]
            );

            $visit = Visit::firstOrCreate(
                ['route_stop_id' => $stop->id],
                [
                    'customer_id' => $cust->id,
                    'driver_id' => $repMarcos->id,
                    'outcome_id' => $outcomeDelivered->id,
                    'arrived_at' => $today->copy()->setHour((int) explode(':', $s['time'])[0])->setMinute((int) explode(':', $s['time'])[1]),
                    'latitude' => $addr->latitude,
                    'longitude' => $addr->longitude,
                    'status' => 'FINISHED',
                    'notes' => 'Visita entregada en Palpalá',
                    'created_by' => $repMarcos->user_id,
                    'enabled' => true,
                ]
            );

            $totalSale = $s['qty_agua'] * 1900.00;
            $sale = Sale::firstOrCreate(
                ['customer_id' => $cust->id, 'visit_id' => $visit->id],
                [
                    'date' => $visit->arrived_at,
                    'total' => $totalSale,
                    'status' => 'confirmed',
                    'is_credit_sale' => false,
                    'created_by' => $repMarcos->user_id,
                    'enabled' => true,
                ]
            );

            SaleDetail::firstOrCreate(
                ['sale_id' => $sale->id, 'product_id' => $productAgua20L->id],
                ['quantity' => $s['qty_agua'], 'unit_price' => 1900.00]
            );

            ContainerMovement::firstOrCreate(
                ['visit_id' => $visit->id, 'movement_type' => 'DELIVERED'],
                [
                    'customer_id' => $cust->id,
                    'container_type_id' => $bidon20L->id,
                    'quantity' => $s['qty_agua'],
                    'direction' => 1,
                    'reason' => 'Entrega bidones',
                    'created_by' => $repMarcos->user_id,
                    'enabled' => true,
                ]
            );
            ContainerMovement::firstOrCreate(
                ['visit_id' => $visit->id, 'movement_type' => 'RETURNED'],
                [
                    'customer_id' => $cust->id,
                    'container_type_id' => $bidon20L->id,
                    'quantity' => $s['qty_agua'],
                    'direction' => -1,
                    'reason' => 'Retiro de bidones vacíos',
                    'created_by' => $repMarcos->user_id,
                    'enabled' => true,
                ]
            );

            $tx = Transaction::create(['type' => 'IN', 'amount' => $totalSale]);
            Payment::firstOrCreate(
                ['sale_id' => $sale->id],
                [
                    'transaction_id' => $tx->id,
                    'customer_id' => $cust->id,
                    'visit_id' => $visit->id,
                    'payment_method_id' => $s['method']->id,
                    'amount' => $totalSale,
                    'received_by' => $repMarcos->user_id,
                    'paid_at' => $visit->arrived_at,
                    'status' => 'CONFIRMED',
                ]
            );
        }
    }
}
