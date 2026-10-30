<?php

namespace Database\Seeders;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Agreements\PriceResolver;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitOutcome;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\Payments\Models\Transaction;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use App\Street\Sales\Models\Sale;
use App\Street\Sales\Models\SaleDetail;
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

        // 0. Salta neighborhoods catalog (the same list the mobile app offers)
        $this->call(NeighborhoodSeeder::class);

        // 1. Core Users (Admin & Repartidores/Drivers)
        $admin = User::firstOrCreate(
            ['email' => 'admin@marilin.com'],
            [
                'name' => 'Administrador Marilin',
                'username' => 'admin',
                'phone_number' => '+54 387 400-0001',
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
                'phone' => '+54 387 421-1001',
            ],
            [
                'email' => 'carlos.gomez@marilin.com',
                'name' => 'Carlos Gómez',
                'username' => 'cgomez',
                'phone' => '+54 387 421-1002',
            ],
            [
                'email' => 'marcos.morales@marilin.com',
                'name' => 'Marcos Morales',
                'username' => 'mmorales',
                'phone' => '+54 387 421-1003',
            ],
            [
                'email' => 'esteban.benitez@marilin.com',
                'name' => 'Esteban Benítez',
                'username' => 'ebenitez',
                'phone' => '+54 387 421-1004',
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
                'description' => 'Sifón Soda 1.5 Litros Retornable',
                'enabled' => true,
            ]
        );

        // 3. Products
        $productAgua20L = Product::firstOrCreate(
            ['name' => 'Bidón 20L'],
            [
                'description' => 'Agua purificada y mineralizada de 20 litros en bidón retornable',
                'price' => 6500.00,
                'requires_container' => true,
                'container_type_id' => $bidon20L->id,
                'available_quantity' => 500.00,
                'enabled' => true,
            ]
        );

        $productSoda2L = Product::firstOrCreate(
            ['name' => 'Sifón 1.5L'],
            [
                'description' => 'Soda en sifón retornable de 1.5 litros',
                'price' => 1500.00,
                'requires_container' => true,
                'container_type_id' => $sifon2L->id,
                'available_quantity' => 350.00,
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

        // 5. Visit Outcomes: what happened when there was no sale. Only rescheduling reopens the stop later.
        $outcomeAbsent = VisitOutcome::firstOrCreate(
            ['code' => 'absent'],
            ['name' => 'Ausente', 'triggers_reschedule' => false, 'enabled' => true]
        );

        VisitOutcome::firstOrCreate(
            ['code' => 'refused'],
            ['name' => 'No quiso', 'triggers_reschedule' => false, 'enabled' => true]
        );

        $outcomeReschedule = VisitOutcome::firstOrCreate(
            ['code' => 'reschedule'],
            ['name' => 'Reprogramar', 'triggers_reschedule' => true, 'enabled' => true]
        );

        // 6. Customers across Salta capital (neighborhoods from the catalog)
        $customersConfig = [
            // A. Empresas & Instituciones (Deudores / Cta Cte)
            [
                'name' => 'Sanatorio Nuestra Señora del Rosario',
                'phone' => '+54 387 422-3000',
                'rep' => $repJuan,
                'street' => 'Caseros 450',
                'ref' => 'Frente a Plaza 9 de Julio',
                'neighborhood' => 'Centro',
                'price_soda' => 950.00,
                'lat' => -24.78890,
                'lng' => -65.41050,
                'bidon_balance' => 18,
                'sifon_balance' => 4,
                'price_agua' => 1800.00,
                'initial_debt' => 45000.00,
            ],
            [
                'name' => 'Clínica Los Lapachos',
                'phone' => '+54 387 423-4500',
                'rep' => $repJuan,
                'street' => 'Av. Bolivia 4300',
                'ref' => 'Entrada por guardia',
                'neighborhood' => 'Grand Bourg',
                'lat' => -24.76500,
                'lng' => -65.42300,
                'bidon_balance' => 12,
                'sifon_balance' => 0,
                'price_agua' => 1850.00,
                'initial_debt' => 28500.00,
            ],
            [
                'name' => 'Hotel Cerro San Bernardo',
                'phone' => '+54 387 426-1500',
                'rep' => $repJuan,
                'street' => 'Av. Virrey Toledo 640',
                'ref' => 'Al pie del cerro',
                'neighborhood' => 'San Bernardo',
                'lat' => -24.79300,
                'lng' => -65.40000,
                'bidon_balance' => 15,
                'sifon_balance' => 8,
                'price_agua' => 1900.00,
                'initial_debt' => 34000.00,
            ],
            [
                'name' => 'Distribuidora del Norte S.R.L.',
                'phone' => '+54 387 425-8800',
                'rep' => $repCarlos,
                'street' => 'Av. Bolivia 5200',
                'ref' => 'Depósito con portón azul',
                'neighborhood' => 'Castañares Grupo 200',
                'lat' => -24.74800,
                'lng' => -65.40800,
                'bidon_balance' => 25,
                'sifon_balance' => 12,
                'price_agua' => 1750.00,
                'initial_debt' => 62000.00,
            ],
            [
                'name' => 'Estudio Jurídico Alvear & Asoc.',
                'phone' => '+54 387 422-9900',
                'rep' => $repJuan,
                'street' => 'España 740',
                'ref' => 'Edificio con portero',
                'floor' => '1',
                'apartment' => 'B',
                'neighborhood' => 'Centro',
                'lat' => -24.78750,
                'lng' => -65.40800,
                'bidon_balance' => 4,
                'sifon_balance' => 2,
                'price_agua' => 2000.00,
                'initial_debt' => 9200.00,
            ],
            [
                'name' => 'Taller Mecánico Tavella Motors',
                'phone' => '+54 387 427-4400',
                'rep' => $repMarcos,
                'street' => 'Av. Tavella 3320',
                'ref' => 'Al lado de la gomería',
                'neighborhood' => 'Santa Ana I',
                'lat' => -24.82800,
                'lng' => -65.39800,
                'bidon_balance' => 6,
                'sifon_balance' => 0,
                'price_agua' => 1900.00,
                'initial_debt' => 11400.00,
            ],

            // B. Salud & Gimnasios
            [
                'name' => 'Gimnasio Go Fitness Salta',
                'phone' => '+54 387 501-2233',
                'rep' => $repCarlos,
                'street' => 'Av. Entre Ríos 1280',
                'ref' => 'Primer piso, subir por la escalera',
                'neighborhood' => 'Parque La Vega',
                'lat' => -24.76500,
                'lng' => -65.40000,
                'bidon_balance' => 14,
                'sifon_balance' => 0,
                'price_agua' => 1800.00,
                'initial_debt' => 16800.00,
            ],
            [
                'name' => 'Crossfit Sur Box',
                'phone' => '+54 387 502-3344',
                'rep' => $repMarcos,
                'street' => 'Av. Paraguay 2510',
                'ref' => 'Galpón con mural',
                'neighborhood' => 'Parque General Belgrano Etapa I',
                'lat' => -24.82000,
                'lng' => -65.41800,
                'bidon_balance' => 8,
                'sifon_balance' => 0,
                'price_agua' => 1900.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Laboratorio BioSalta',
                'phone' => '+54 387 424-1122',
                'rep' => $repJuan,
                'street' => 'Zuviría 540',
                'ref' => 'Timbre de recepción',
                'neighborhood' => 'Centro',
                'lat' => -24.78400,
                'lng' => -65.41150,
                'bidon_balance' => 5,
                'sifon_balance' => 0,
                'price_agua' => 2000.00,
                'initial_debt' => 0.00,
            ],

            // C. Comercios & Gastronomía
            [
                'name' => 'Panadería y Confitería La Unión',
                'phone' => '+54 387 425-6677',
                'rep' => $repCarlos,
                'street' => 'Mitre 1450',
                'ref' => 'Entrada por la cuadra de atrás',
                'neighborhood' => 'Ciudad del Milagro',
                'lat' => -24.77200,
                'lng' => -65.39000,
                'bidon_balance' => 4,
                'sifon_balance' => 8,
                'price_agua' => 1950.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Café Martínez Belgrano',
                'phone' => '+54 387 422-8811',
                'rep' => $repJuan,
                'street' => 'Av. Belgrano 680',
                'ref' => 'Esquina con Alberdi',
                'floor' => 'PB',
                'neighborhood' => 'Centro',
                'price_soda' => 900.00,
                'lat' => -24.78650,
                'lng' => -65.40650,
                'bidon_balance' => 8,
                'sifon_balance' => 12,
                'price_agua' => 1900.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Restaurante Viracocha',
                'phone' => '+54 387 423-7744',
                'rep' => $repJuan,
                'street' => 'Alberdi 820',
                'ref' => 'Peatonal Alberdi',
                'neighborhood' => 'Centro',
                'lat' => -24.78300,
                'lng' => -65.40900,
                'bidon_balance' => 6,
                'sifon_balance' => 10,
                'price_agua' => 1900.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Kiosco El Sol',
                'phone' => '+54 387 510-4455',
                'rep' => $repCarlos,
                'street' => 'Balcarce 1980',
                'ref' => 'Frente a parada de colectivos',
                'neighborhood' => 'El Huaico',
                'lat' => -24.73600,
                'lng' => -65.40100,
                'bidon_balance' => 3,
                'sifon_balance' => 4,
                'price_agua' => 2000.00,
                'initial_debt' => 0.00,
            ],

            // D. Clientes Residenciales
            [
                'name' => 'Familia Calisaya',
                'phone' => '+54 387 411-2233',
                'rep' => $repJuan,
                'street' => 'Pasaje Los Ceibos 245',
                'ref' => 'Casa con jardín al frente',
                'neighborhood' => 'Tres Cerritos',
                'lat' => -24.77100,
                'lng' => -65.39600,
                'bidon_balance' => 3,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Familia Tolaba',
                'phone' => '+54 387 412-3344',
                'rep' => $repJuan,
                'street' => 'Los Álamos 120',
                'ref' => 'Portón negro',
                'neighborhood' => 'Lomas De Tres Cerritos',
                'lat' => -24.76700,
                'lng' => -65.39200,
                'bidon_balance' => 4,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 4200.00,
            ],
            [
                'name' => 'Familia Ramírez',
                'phone' => '+54 387 413-4455',
                'rep' => $repCarlos,
                'street' => 'Pasaje Los Tarcos 630',
                'ref' => 'Casa esquina',
                'neighborhood' => 'Castañares Grupo 108',
                'lat' => -24.74500,
                'lng' => -65.40500,
                'bidon_balance' => 2,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Familia Morales',
                'phone' => '+54 387 414-5566',
                'rep' => $repMarcos,
                'street' => 'Los Gladiolos 420',
                'ref' => 'Casa de dos plantas',
                'neighborhood' => 'Santa Ana II',
                'lat' => -24.83300,
                'lng' => -65.39500,
                'bidon_balance' => 3,
                'sifon_balance' => 0,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],

            // E. Limache: Esteban's day, still to be worked from the mobile app
            [
                'name' => 'Supermercado El Tunal',
                'phone' => '+54 387 431-2200',
                'rep' => $repEsteban,
                'street' => 'Av. Paraguay 3520',
                'ref' => 'Portón de carga lateral',
                'neighborhood' => 'Limache',
                'lat' => -24.83500,
                'lng' => -65.42500,
                'bidon_balance' => 10,
                'sifon_balance' => 6,
                'price_agua' => 1750.00,
                'price_soda' => 900.00,
                'initial_debt' => 21000.00,
            ],
            [
                'name' => 'Escuela N° 4032 Limache',
                'phone' => '+54 387 431-4320',
                'rep' => $repEsteban,
                'street' => 'Av. Chile 1800',
                'ref' => 'Entrada por portería',
                'neighborhood' => 'Limache Etapa 2',
                'lat' => -24.83800,
                'lng' => -65.42200,
                'bidon_balance' => 8,
                'sifon_balance' => 0,
                'price_agua' => 1800.00,
                'expired_price_agua' => 1650.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Familia Quispe',
                'phone' => '+54 387 415-6677',
                'rep' => $repEsteban,
                'street' => 'Pasaje Los Molles 45',
                'ref' => 'Casa de rejas verdes',
                'floor' => 'PB',
                'apartment' => '3',
                'neighborhood' => 'Limache Etapa 3',
                'lat' => -24.84000,
                'lng' => -65.42800,
                'bidon_balance' => 2,
                'sifon_balance' => 2,
                'price_agua' => 2100.00,
                'initial_debt' => 4200.00,
            ],
            [
                'name' => 'Familia Mamaní',
                'phone' => '+54 387 416-7788',
                'rep' => $repEsteban,
                'street' => 'Av. Las Palmeras 2100',
                'ref' => 'Monoblock 4',
                'floor' => '2',
                'apartment' => 'C',
                'neighborhood' => 'Limache Etapa 5',
                'lat' => -24.84300,
                'lng' => -65.42000,
                'bidon_balance' => 2,
                'sifon_balance' => 0,
                'price_agua' => 2100.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Consultorio Odontológico Sonrisas',
                'phone' => '+54 387 432-1100',
                'rep' => $repEsteban,
                'street' => 'Av. Paraguay 3800',
                'ref' => 'Galería Limache',
                'floor' => 'Local 2',
                'neighborhood' => 'Limache',
                'lat' => -24.83650,
                'lng' => -65.42650,
                'bidon_balance' => 3,
                'sifon_balance' => 0,
                'price_agua' => 2000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Verdulería Los Andes',
                'phone' => '+54 387 433-5544',
                'rep' => $repEsteban,
                'street' => 'Calle 12 de Octubre 310',
                'ref' => 'Frente a la feria',
                'neighborhood' => 'Limache Etapa 1',
                'lat' => -24.83200,
                'lng' => -65.42400,
                'bidon_balance' => 4,
                'sifon_balance' => 4,
                'price_agua' => 1950.00,
                'initial_debt' => 7800.00,
            ],
            [
                'name' => 'Kiosco Don Ramón',
                'phone' => '+54 387 434-9900',
                'rep' => $repEsteban,
                'street' => 'Av. Las Palmeras 2400',
                'ref' => 'Cerró el local',
                'neighborhood' => 'Limache Etapa 5',
                'lat' => -24.84400,
                'lng' => -65.41900,
                'bidon_balance' => 0,
                'sifon_balance' => 0,
                'price_agua' => 2000.00,
                'initial_debt' => 0.00,
                'enabled' => false,
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
                    'enabled' => $cfg['enabled'] ?? true,
                ]
            );

            CustomerAddress::firstOrCreate(
                ['customer_id' => $customer->id, 'street' => $cfg['street']],
                [
                    'floor' => $cfg['floor'] ?? null,
                    'apartment' => $cfg['apartment'] ?? null,
                    'neighborhood' => $cfg['neighborhood'] ?? null,
                    'visual_reference' => $cfg['ref'],
                    'latitude' => $cfg['lat'],
                    'longitude' => $cfg['lng'],
                    'is_primary' => true,
                    'created_by' => $admin->id,
                    'enabled' => true,
                ]
            );

            // Monday plus today's weekday (0 = Sunday), so the app always has a window for the day it syncs.
            foreach (array_unique([1, $today->dayOfWeek]) as $dayOfWeek) {
                CustomerPreference::firstOrCreate(
                    ['customer_id' => $customer->id, 'day_of_week' => $dayOfWeek],
                    [
                        'time_window_start' => '08:30:00',
                        'time_window_end' => '13:00:00',
                        'created_by' => $admin->id,
                        'enabled' => true,
                    ]
                );
            }

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

            // Agreements: current ones have no end date; products without one sell at list price.
            CustomerAgreement::firstOrCreate(
                ['customer_id' => $customer->id, 'product_id' => $productAgua20L->id, 'valid_to' => null],
                [
                    'unit_price' => $cfg['price_agua'],
                    'valid_from' => $today->copy()->subMonth(),
                    'created_by' => $admin->id,
                ]
            );

            if (isset($cfg['price_soda'])) {
                CustomerAgreement::firstOrCreate(
                    ['customer_id' => $customer->id, 'product_id' => $productSoda2L->id, 'valid_to' => null],
                    [
                        'unit_price' => $cfg['price_soda'],
                        'valid_from' => $today->copy()->subMonth(),
                        'created_by' => $admin->id,
                    ]
                );
            }

            if (isset($cfg['expired_price_agua'])) {
                CustomerAgreement::firstOrCreate(
                    ['customer_id' => $customer->id, 'product_id' => $productAgua20L->id, 'valid_to' => $today->copy()->subMonth()->subDay()->toDateString()],
                    [
                        'unit_price' => $cfg['expired_price_agua'],
                        'valid_from' => $today->copy()->subMonths(4),
                        'reason' => 'Precio promocional vencido',
                        'created_by' => $admin->id,
                    ]
                );
            }

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

        // 7. Yesterday: every driver closed a full route, so the dashboard compares today against a real day.
        // A null method is a credit sale; 'absent' is a visit without sale.
        $yesterdayRoutes = [
            [$repJuan, '08:00', '13:40', [
                ['customer' => 'Sanatorio Nuestra Señora del Rosario', 'time' => '08:30', 'qty_agua' => 10, 'qty_soda' => 0, 'method' => $transferMethod],
                ['customer' => 'Café Martínez Belgrano', 'time' => '09:15', 'qty_agua' => 4, 'qty_soda' => 8, 'method' => $cashMethod],
                ['customer' => 'Estudio Jurídico Alvear & Asoc.', 'time' => '10:00', 'qty_agua' => 3, 'qty_soda' => 0, 'method' => $cashMethod],
                ['customer' => 'Hotel Cerro San Bernardo', 'time' => '11:20', 'qty_agua' => 8, 'qty_soda' => 6, 'method' => null],
                ['customer' => 'Familia Tolaba', 'time' => '12:50', 'absent' => true],
            ]],
            [$repCarlos, '07:45', '13:15', [
                ['customer' => 'Panadería y Confitería La Unión', 'time' => '08:10', 'qty_agua' => 4, 'qty_soda' => 6, 'method' => $cashMethod],
                ['customer' => 'Gimnasio Go Fitness Salta', 'time' => '09:05', 'qty_agua' => 8, 'qty_soda' => 0, 'method' => $transferMethod],
                ['customer' => 'Kiosco El Sol', 'time' => '10:20', 'qty_agua' => 3, 'qty_soda' => 4, 'method' => $cashMethod],
                ['customer' => 'Familia Ramírez', 'time' => '11:40', 'qty_agua' => 2, 'qty_soda' => 0, 'method' => null],
            ]],
            [$repMarcos, '08:30', '12:30', [
                ['customer' => 'Crossfit Sur Box', 'time' => '09:00', 'qty_agua' => 6, 'qty_soda' => 0, 'method' => $transferMethod],
                ['customer' => 'Taller Mecánico Tavella Motors', 'time' => '10:10', 'qty_agua' => 5, 'qty_soda' => 0, 'method' => null],
                ['customer' => 'Familia Morales', 'time' => '11:30', 'qty_agua' => 3, 'qty_soda' => 0, 'method' => $cashMethod],
            ]],
            [$repEsteban, '08:00', '13:00', [
                ['customer' => 'Supermercado El Tunal', 'time' => '08:25', 'qty_agua' => 10, 'qty_soda' => 8, 'method' => $transferMethod],
                ['customer' => 'Verdulería Los Andes', 'time' => '09:10', 'qty_agua' => 3, 'qty_soda' => 2, 'method' => $cashMethod],
                ['customer' => 'Escuela N° 4032 Limache', 'time' => '10:00', 'qty_agua' => 6, 'qty_soda' => 0, 'method' => null],
                ['customer' => 'Familia Quispe', 'time' => '11:15', 'qty_agua' => 2, 'qty_soda' => 0, 'method' => $cashMethod],
                ['customer' => 'Familia Mamaní', 'time' => '12:20', 'qty_agua' => 2, 'qty_soda' => 0, 'method' => $cashMethod],
            ]],
        ];

        foreach ($yesterdayRoutes as [$driver, $startedAt, $completedAt, $stops]) {
            $this->seedCompletedRoute(
                $driver,
                $yesterday,
                $startedAt,
                $completedAt,
                $stops,
                $createdCustomers,
                ['agua' => $productAgua20L, 'soda' => $productSoda2L],
                $bidon20L,
                $outcomeAbsent,
                $admin,
            );
        }

        // 8. TODAY'S ROUTES & TRUCK LOADS

        // --- ROUTE 1: Juan Pérez (IN_PROGRESS - Centro & Tres Cerritos) ---
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
            ['quantity' => 60.00, 'loaded_at' => $routeJuan->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
        );
        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productSoda2L->id],
            ['quantity' => 30.00, 'loaded_at' => $routeJuan->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
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
                'customer' => 'Laboratorio BioSalta',
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
                'scheduled' => '13:00',
                'qty_agua' => 2,
                'qty_soda' => 0,
                'method' => null,
                'is_credit' => false,
            ],
            [
                'customer' => 'Hotel Cerro San Bernardo',
                'status' => 'PENDING',
                'time' => null,
                'scheduled' => '15:30',
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
                ['address_id' => $addr->id, 'scheduled_time' => $s['scheduled'] ?? null, 'enabled' => true]
            );

            if ($s['status'] !== 'PENDING') {
                $visit = Visit::firstOrCreate(
                    ['route_stop_id' => $stop->id],
                    [
                        'customer_id' => $cust->id,
                        'driver_id' => $repJuan->id,
                        // Outcomes explain a visit without sale; these ones sold or are still in progress.
                        'outcome_id' => null,
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
                    if (! $s['is_credit'] && $s['method']) {
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
                        $this->bookCreditSale($sale, $cust, $repJuan->user_id, "Venta a Crédito {$s['qty_agua']}x Bidones 20L");
                    }
                }
            }
        }

        // --- ROUTE 2: Carlos Gómez (COMPLETED - Castañares & El Huaico) ---
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
            ['quantity' => 50.00, 'loaded_at' => $routeCarlos->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
        );
        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeCarlos->id, 'product_id' => $productSoda2L->id],
            ['quantity' => 20.00, 'loaded_at' => $routeCarlos->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
        );
        // Loaded minus sold below: agua 50 - 36, soda 20 - 18.
        RouteInventory::firstOrCreate(
            ['route_id' => $routeCarlos->id, 'product_id' => $productAgua20L->id],
            ['current_quantity' => 14.00, 'created_by' => $admin->id, 'enabled' => true]
        );
        RouteInventory::firstOrCreate(
            ['route_id' => $routeCarlos->id, 'product_id' => $productSoda2L->id],
            ['current_quantity' => 2.00, 'created_by' => $admin->id, 'enabled' => true]
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
                'customer' => 'Gimnasio Go Fitness Salta',
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
                'customer' => 'Distribuidora del Norte S.R.L.',
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
                    'outcome_id' => null,
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
            if ($s['qty_soda'] > 0) {
                SaleDetail::firstOrCreate(
                    ['sale_id' => $sale->id, 'product_id' => $productSoda2L->id],
                    ['quantity' => $s['qty_soda'], 'unit_price' => 1000.00]
                );
            }

            if ($s['is_credit']) {
                $this->bookCreditSale($sale, $cust, $repCarlos->user_id, "Venta a Crédito {$s['qty_agua']}x Bidones 20L");
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

            if (! $s['is_credit'] && $s['method']) {
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

        // --- ROUTE 3: Marcos Morales (IN_PROGRESS - Santa Ana & Parque General Belgrano) ---
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
            ['quantity' => 40.00, 'loaded_at' => $routeMarcos->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
        );
        RouteInventory::firstOrCreate(
            ['route_id' => $routeMarcos->id, 'product_id' => $productAgua20L->id],
            ['current_quantity' => 26.00, 'created_by' => $admin->id, 'enabled' => true]
        );

        $marcosStops = [
            [
                'customer' => 'Crossfit Sur Box',
                'status' => 'FINISHED',
                'time' => '09:10',
                'qty_agua' => 6,
                'qty_soda' => 0,
                'method' => $transferMethod,
                'is_credit' => false,
            ],
            [
                'customer' => 'Taller Mecánico Tavella Motors',
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
                    'outcome_id' => null,
                    'arrived_at' => $today->copy()->setHour((int) explode(':', $s['time'])[0])->setMinute((int) explode(':', $s['time'])[1]),
                    'latitude' => $addr->latitude,
                    'longitude' => $addr->longitude,
                    'status' => 'FINISHED',
                    'notes' => 'Visita entregada en zona sur',
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

        // --- ROUTE 4: Esteban Benítez (IN_PROGRESS - Limache) ---
        // The day to work from the mobile app: almost everything pending, one absent customer already rescheduled.
        $routeEsteban = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repEsteban->id, 'date' => $today->toDateString()],
            [
                'status' => 'IN_PROGRESS',
                'started_at' => $today->copy()->setHour(8),
                'enabled' => true,
            ]
        );

        foreach ([[$productAgua20L, 40.00], [$productSoda2L, 20.00]] as [$product, $quantity]) {
            RouteInventoryLoad::firstOrCreate(
                ['route_id' => $routeEsteban->id, 'product_id' => $product->id],
                ['quantity' => $quantity, 'loaded_at' => $routeEsteban->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
            );
            RouteInventory::firstOrCreate(
                ['route_id' => $routeEsteban->id, 'product_id' => $product->id],
                ['current_quantity' => $quantity, 'created_by' => $admin->id, 'enabled' => true]
            );
        }

        $estebanStops = [
            'Verdulería Los Andes' => '08:30',
            'Supermercado El Tunal' => '09:00',
            'Escuela N° 4032 Limache' => '09:45',
            'Consultorio Odontológico Sonrisas' => '10:30',
            'Familia Quispe' => '11:15',
            'Familia Mamaní' => '12:00',
        ];

        foreach ($estebanStops as $customerName => $scheduledTime) {
            $cust = $createdCustomers[$customerName];

            RouteStop::firstOrCreate(
                ['route_id' => $routeEsteban->id, 'customer_id' => $cust->id, 'is_rescheduled_from' => null],
                ['address_id' => $cust->primaryAddress->id, 'scheduled_time' => $scheduledTime, 'enabled' => true]
            );
        }

        // The greengrocer was closed at 08:30: the driver rescheduled the visit for 16:00.
        $absentCustomer = $createdCustomers['Verdulería Los Andes'];
        $absentStop = RouteStop::query()
            ->where('route_id', $routeEsteban->id)
            ->where('customer_id', $absentCustomer->id)
            ->whereNull('is_rescheduled_from')
            ->firstOrFail();

        Visit::firstOrCreate(
            ['route_stop_id' => $absentStop->id],
            [
                'customer_id' => $absentCustomer->id,
                'driver_id' => $repEsteban->id,
                'outcome_id' => $outcomeReschedule->id,
                'arrived_at' => $today->copy()->setHour(8)->setMinute(35),
                'latitude' => $absentCustomer->primaryAddress->latitude,
                'longitude' => $absentCustomer->primaryAddress->longitude,
                'status' => 'FINISHED',
                'notes' => 'Local cerrado, vuelvo a la tarde',
                'created_by' => $repEsteban->user_id,
                'enabled' => true,
            ]
        );

        RouteStop::firstOrCreate(
            ['route_id' => $routeEsteban->id, 'customer_id' => $absentCustomer->id, 'is_rescheduled_from' => $absentStop->id],
            ['address_id' => $absentStop->address_id, 'scheduled_time' => '16:00', 'enabled' => true]
        );
    }

    /**
     * A finished day for one driver: truck loaded before the start, every stop visited, sales at the
     * customer's price that day, and the cash or transfer payment of every sale that was not on credit.
     *
     * @param  list<array{customer: string, time: string, qty_agua?: int, qty_soda?: int, method?: ?PaymentMethod, absent?: bool}>  $stops
     * @param  array<string, Customer>  $customers
     * @param  array{agua: Product, soda: Product}  $products
     */
    private function seedCompletedRoute(
        Driver $driver,
        Carbon $day,
        string $startedAt,
        string $completedAt,
        array $stops,
        array $customers,
        array $products,
        ContainerType $bidon,
        VisitOutcome $outcomeAbsent,
        User $admin,
    ): void {
        $at = fn (string $time): Carbon => $day->copy()->setTimeFromTimeString($time);
        $prices = app(PriceResolver::class);

        $route = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $driver->id, 'date' => $day->toDateString()],
            ['status' => 'COMPLETED', 'started_at' => $at($startedAt), 'completed_at' => $at($completedAt), 'enabled' => true]
        );

        $sold = ['agua' => 0, 'soda' => 0];

        foreach ($stops as $s) {
            $customer = $customers[$s['customer']];
            $address = $customer->primaryAddress;
            $absent = $s['absent'] ?? false;

            $stop = RouteStop::firstOrCreate(
                ['route_id' => $route->id, 'customer_id' => $customer->id],
                ['address_id' => $address->id, 'enabled' => true]
            );

            $visit = Visit::firstOrCreate(
                ['route_stop_id' => $stop->id],
                [
                    'customer_id' => $customer->id,
                    'driver_id' => $driver->id,
                    'outcome_id' => $absent ? $outcomeAbsent->id : null,
                    'arrived_at' => $at($s['time']),
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                    'status' => 'FINISHED',
                    'notes' => $absent ? 'No había nadie' : 'Entrega completada',
                    'created_by' => $driver->user_id,
                    'enabled' => true,
                ]
            );

            if ($absent) {
                continue;
            }

            $lines = [];
            foreach (['agua' => $s['qty_agua'], 'soda' => $s['qty_soda']] as $key => $quantity) {
                if ($quantity > 0) {
                    $lines[$key] = [$quantity, (float) $prices->priceFor($customer, $products[$key], $day)->unitPrice];
                    $sold[$key] += $quantity;
                }
            }
            $total = array_sum(array_map(fn (array $line): float => $line[0] * $line[1], $lines));
            $isCredit = $s['method'] === null;

            $sale = Sale::firstOrCreate(
                ['customer_id' => $customer->id, 'visit_id' => $visit->id],
                [
                    'date' => $visit->arrived_at,
                    'total' => $total,
                    'status' => 'confirmed',
                    'is_credit_sale' => $isCredit,
                    'notes' => 'Venta en parada',
                    'created_by' => $driver->user_id,
                    'enabled' => true,
                ]
            );

            foreach ($lines as $key => [$quantity, $unitPrice]) {
                SaleDetail::firstOrCreate(
                    ['sale_id' => $sale->id, 'product_id' => $products[$key]->id],
                    ['quantity' => $quantity, 'unit_price' => $unitPrice]
                );
            }

            foreach ([['DELIVERED', 1, 'Entrega de bidones'], ['RETURNED', -1, 'Retiro de bidones vacíos']] as [$type, $direction, $reason]) {
                ContainerMovement::firstOrCreate(
                    ['visit_id' => $visit->id, 'movement_type' => $type],
                    [
                        'customer_id' => $customer->id,
                        'container_type_id' => $bidon->id,
                        'quantity' => $s['qty_agua'],
                        'direction' => $direction,
                        'reason' => $reason,
                        'created_by' => $driver->user_id,
                        'enabled' => true,
                    ]
                );
            }

            if ($isCredit) {
                $this->bookCreditSale($sale, $customer, $driver->user_id, "Venta a Crédito {$s['qty_agua']}x Bidones 20L");

                continue;
            }

            $payment = Payment::query()->where('sale_id', $sale->id)->first() ?? Payment::create([
                'transaction_id' => Transaction::create(['type' => 'IN', 'amount' => $total])->id,
                'sale_id' => $sale->id,
                'customer_id' => $customer->id,
                'visit_id' => $visit->id,
                'payment_method_id' => $s['method']->id,
                'amount' => $total,
                'received_by' => $driver->user_id,
                'paid_at' => $visit->arrived_at,
                'status' => 'CONFIRMED',
            ]);

            // A sale paid on the spot books both sides, so the customer's balance does not move.
            CustomerAccountEntry::firstOrCreate(
                ['sale_id' => $sale->id, 'entry_type' => 'SALE'],
                [
                    'customer_id' => $customer->id,
                    'direction' => 1,
                    'amount' => $total,
                    'description' => "Venta {$s['qty_agua']}x Bidones 20L",
                    'created_by' => $driver->user_id,
                    'created_at' => $visit->arrived_at,
                ]
            );
            CustomerAccountEntry::firstOrCreate(
                ['payment_id' => $payment->id, 'entry_type' => 'PAYMENT'],
                [
                    'customer_id' => $customer->id,
                    'direction' => -1,
                    'amount' => $total,
                    'description' => "Cobro {$s['method']->name}",
                    'created_by' => $driver->user_id,
                    'created_at' => $visit->arrived_at,
                ]
            );
        }

        // The truck leaves with some spare stock; what is left is the load minus what was sold.
        foreach ($products as $key => $product) {
            $loaded = $sold[$key] > 0 ? $sold[$key] + 6 : 0;
            if ($loaded === 0) {
                continue;
            }

            RouteInventoryLoad::firstOrCreate(
                ['route_id' => $route->id, 'product_id' => $product->id],
                ['quantity' => $loaded, 'loaded_at' => $route->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
            );
            RouteInventory::firstOrCreate(
                ['route_id' => $route->id, 'product_id' => $product->id],
                ['current_quantity' => $loaded - $sold[$key], 'created_by' => $admin->id, 'enabled' => true]
            );
        }
    }

    /**
     * A credit sale is owed by the customer: it books the ledger entry and raises the balance once.
     */
    private function bookCreditSale(Sale $sale, Customer $customer, int $userId, string $description): void
    {
        $entry = CustomerAccountEntry::firstOrCreate(
            ['sale_id' => $sale->id, 'entry_type' => 'SALE'],
            [
                'customer_id' => $customer->id,
                'direction' => 1,
                'amount' => $sale->total,
                'description' => $description,
                'created_by' => $userId,
            ]
        );

        if ($entry->wasRecentlyCreated) {
            CustomerBalance::query()->where('customer_id', $customer->id)->increment('current_balance', (float) $sale->total);
        }
    }
}
