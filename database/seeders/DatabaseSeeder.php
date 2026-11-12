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
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\ScheduledVisits\VisitScheduler;
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
                'phone_number' => '387 400-0001',
                'is_super_user' => true,
                'enabled' => true,
                'password' => Hash::make('password'),
            ]
        );

        $driverUsers = [
            [
                'name' => 'Juan Pérez',
                'username' => 'jperez',
                'phone' => '387 421-1001',
            ],
            [
                'name' => 'Carlos Gómez',
                'username' => 'cgomez',
                'phone' => '387 421-1002',
            ],
            [
                'name' => 'Marcos Morales',
                'username' => 'mmorales',
                'phone' => '387 421-1003',
            ],
            [
                'name' => 'Esteban Benítez',
                'username' => 'ebenitez',
                'phone' => '387 421-1004',
            ],
            [
                'name' => 'Lucía Fernández',
                'username' => 'lfernandez',
                'phone' => '387 421-1005',
            ],
        ];

        $drivers = [];
        foreach ($driverUsers as $du) {
            // Drivers sign in to the app with the username and have no email.
            $user = User::firstOrCreate(
                ['username' => $du['username']],
                [
                    'name' => $du['name'],
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
        // Starts her day with the route planned but not dispatched: the truck is not loaded yet.
        $repLucia = $drivers[4];

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

        // 5. Visit Outcomes: what happened when there was no sale. Only rescheduling reopens the stop later.
        $outcomeAbsent = VisitOutcome::firstOrCreate(
            ['code' => 'absent'],
            ['name' => 'Ausente', 'triggers_reschedule' => false, 'enabled' => true]
        );

        VisitOutcome::firstOrCreate(
            ['code' => 'refused'],
            ['name' => 'Sin pedido', 'triggers_reschedule' => false, 'enabled' => true]
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
                'phone' => '387 422-3000',
                'rep' => $repJuan,
                'street' => 'Caseros 450',
                'ref' => 'Frente a Plaza 9 de Julio',
                'neighborhood' => 'Centro',
                'lat' => -24.78890,
                'lng' => -65.41050,
                'bidon_balance' => 18,
                'sifon_balance' => 4,
                'price_agua' => 5200.00,
                'initial_debt' => 45000.00,
            ],
            [
                'name' => 'Clínica Los Lapachos',
                'phone' => '387 423-4500',
                'rep' => $repJuan,
                'street' => 'Av. Bolivia 4300',
                'ref' => 'Entrada por guardia',
                'neighborhood' => 'Grand Bourg',
                'lat' => -24.76500,
                'lng' => -65.42300,
                'bidon_balance' => 12,
                'sifon_balance' => 0,
                'price_agua' => 5300.00,
                'initial_debt' => 28500.00,
            ],
            [
                'name' => 'Hotel Cerro San Bernardo',
                'phone' => '387 426-1500',
                'rep' => $repJuan,
                'street' => 'Av. Virrey Toledo 640',
                'ref' => 'Al pie del cerro',
                'neighborhood' => 'San Bernardo',
                'lat' => -24.79300,
                'lng' => -65.40000,
                'bidon_balance' => 15,
                'sifon_balance' => 8,
                'price_agua' => 5500.00,
                'initial_debt' => 34000.00,
            ],
            [
                'name' => 'Distribuidora del Norte S.R.L.',
                'phone' => '387 425-8800',
                'rep' => $repCarlos,
                'street' => 'Av. Bolivia 5200',
                'ref' => 'Depósito con portón azul',
                'neighborhood' => 'Castañares Grupo 200',
                'lat' => -24.74800,
                'lng' => -65.40800,
                'bidon_balance' => 25,
                'sifon_balance' => 12,
                'price_agua' => 5000.00,
                'initial_debt' => 62000.00,
            ],
            [
                'name' => 'Estudio Jurídico Alvear & Asoc.',
                'phone' => '387 422-9900',
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
                'price_agua' => 5800.00,
                'initial_debt' => 9200.00,
            ],
            [
                'name' => 'Taller Mecánico Tavella Motors',
                'phone' => '387 427-4400',
                'rep' => $repMarcos,
                'street' => 'Av. Tavella 3320',
                'ref' => 'Al lado de la gomería',
                'neighborhood' => 'Santa Ana I',
                'lat' => -24.82800,
                'lng' => -65.39800,
                'bidon_balance' => 6,
                'sifon_balance' => 0,
                'price_agua' => 5500.00,
                'initial_debt' => 11400.00,
            ],

            // B. Salud & Gimnasios
            [
                'name' => 'Gimnasio Go Fitness Salta',
                'phone' => '387 501-2233',
                'rep' => $repCarlos,
                'street' => 'Av. Entre Ríos 1280',
                'ref' => 'Primer piso, subir por la escalera',
                'neighborhood' => 'Parque La Vega',
                'lat' => -24.76500,
                'lng' => -65.40000,
                'bidon_balance' => 14,
                'sifon_balance' => 0,
                'price_agua' => 5200.00,
                'initial_debt' => 16800.00,
            ],
            [
                'name' => 'Crossfit Sur Box',
                'phone' => '387 502-3344',
                'rep' => $repMarcos,
                'street' => 'Av. Paraguay 2510',
                'ref' => 'Galpón con mural',
                'neighborhood' => 'Parque General Belgrano Etapa I',
                'lat' => -24.82000,
                'lng' => -65.41800,
                'bidon_balance' => 8,
                'sifon_balance' => 0,
                'price_agua' => 5500.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Laboratorio BioSalta',
                'phone' => '387 424-1122',
                'rep' => $repJuan,
                'street' => 'Zuviría 540',
                'ref' => null,
                'neighborhood' => 'Centro',
                'lat' => -24.78400,
                'lng' => -65.41150,
                'bidon_balance' => 5,
                'sifon_balance' => 0,
                'price_agua' => 5800.00,
                'initial_debt' => 0.00,
            ],

            // C. Comercios & Gastronomía
            [
                'name' => 'Panadería y Confitería La Unión',
                'phone' => '387 425-6677',
                'rep' => $repCarlos,
                'street' => 'Mitre 1450',
                'ref' => 'Entrada por la cuadra de atrás',
                'neighborhood' => 'Ciudad del Milagro',
                'lat' => -24.77200,
                'lng' => -65.39000,
                'bidon_balance' => 4,
                'sifon_balance' => 8,
                'price_agua' => 5700.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Café Martínez Belgrano',
                'phone' => '387 422-8811',
                'rep' => $repJuan,
                'street' => 'Av. Belgrano 680',
                'ref' => 'Esquina con Alberdi',
                'floor' => 'PB',
                'neighborhood' => 'Centro',
                'lat' => -24.78650,
                'lng' => -65.40650,
                'bidon_balance' => 8,
                'sifon_balance' => 12,
                'price_agua' => 5500.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Restaurante Viracocha',
                'phone' => '387 423-7744',
                'rep' => $repJuan,
                'street' => 'Alberdi 820',
                'ref' => 'Peatonal Alberdi',
                'neighborhood' => 'Centro',
                'lat' => -24.78300,
                'lng' => -65.40900,
                'bidon_balance' => 6,
                'sifon_balance' => 10,
                'price_agua' => 5500.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Kiosco El Sol',
                'phone' => '387 510-4455',
                'rep' => $repCarlos,
                'street' => 'Balcarce 1980',
                'ref' => 'Frente a parada de colectivos',
                'neighborhood' => 'El Huaico',
                'lat' => -24.73600,
                'lng' => -65.40100,
                'bidon_balance' => 3,
                'sifon_balance' => 4,
                'price_agua' => 5800.00,
                'initial_debt' => 0.00,
            ],

            // D. Clientes Residenciales
            [
                'name' => 'Franco Calisaya',
                'phone' => '387 411-2233',
                'rep' => $repJuan,
                'street' => 'Pasaje Los Ceibos 245',
                'ref' => 'Casa con jardín al frente',
                'neighborhood' => 'Tres Cerritos',
                'lat' => -24.77100,
                'lng' => -65.39600,
                'bidon_balance' => 3,
                'sifon_balance' => 2,
                'price_agua' => 6000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Marta Tolaba',
                'phone' => '387 412-3344',
                'rep' => $repJuan,
                'street' => 'Los Álamos 120',
                'ref' => 'Portón negro',
                'neighborhood' => 'Lomas De Tres Cerritos',
                'lat' => -24.76700,
                'lng' => -65.39200,
                'bidon_balance' => 4,
                'sifon_balance' => 2,
                'price_agua' => 6000.00,
                'initial_debt' => 4200.00,
            ],
            [
                'name' => 'Diego Ramírez',
                'phone' => '387 413-4455',
                'rep' => $repCarlos,
                'street' => 'Pasaje Los Tarcos 630',
                'ref' => null,
                'neighborhood' => 'Castañares Grupo 108',
                'lat' => -24.74500,
                'lng' => -65.40500,
                'bidon_balance' => 2,
                'sifon_balance' => 2,
                'price_agua' => 6000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Silvia Morales',
                'phone' => '387 414-5566',
                'rep' => $repMarcos,
                'street' => 'Los Gladiolos 420',
                'ref' => null,
                'neighborhood' => 'Santa Ana II',
                'lat' => -24.83300,
                'lng' => -65.39500,
                'bidon_balance' => 3,
                'sifon_balance' => 0,
                'price_agua' => 6000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Ferretería Santa Ana',
                'phone' => '387 427-5510',
                'rep' => $repMarcos,
                'street' => 'Av. Tavella 3480',
                'ref' => 'Persiana gris, al lado del corralón',
                'neighborhood' => 'Santa Ana I',
                'lat' => -24.82870,
                'lng' => -65.39720,
                'bidon_balance' => 2,
                'sifon_balance' => 0,
                'price_agua' => 5800.00,
                'initial_debt' => 0.00,
            ],

            // E. Limache: Esteban's day, planned and waiting for the truck load and the dispatch
            [
                'name' => 'Supermercado El Tunal',
                'phone' => '387 431-2200',
                'rep' => $repEsteban,
                'street' => 'Av. Paraguay 3520',
                'ref' => 'Portón de carga lateral',
                'neighborhood' => 'Limache',
                'lat' => -24.83500,
                'lng' => -65.42500,
                'bidon_balance' => 10,
                'sifon_balance' => 6,
                'price_agua' => 5000.00,
                'price_soda' => 1300.00,
                'initial_debt' => 21000.00,
            ],
            [
                'name' => 'Escuela N° 4032 Limache',
                'phone' => '387 431-4320',
                'rep' => $repEsteban,
                'street' => 'Av. Chile 1800',
                'ref' => 'Entrada por portería',
                'neighborhood' => 'Limache Etapa 2',
                'lat' => -24.83800,
                'lng' => -65.42200,
                'bidon_balance' => 8,
                'sifon_balance' => 0,
                'price_agua' => 5200.00,
                'expired_price_agua' => 5000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Elena Quispe',
                'phone' => '387 415-6677',
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
                'price_agua' => 6000.00,
                'initial_debt' => 4200.00,
            ],
            [
                'name' => 'Jorge Mamaní',
                'phone' => '387 416-7788',
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
                'price_agua' => 6000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Consultorio Odontológico Sonrisas',
                'phone' => '387 432-1100',
                'rep' => $repEsteban,
                'street' => 'Av. Paraguay 3800',
                'ref' => 'Galería Limache',
                'floor' => 'Local 2',
                'neighborhood' => 'Limache',
                'lat' => -24.83650,
                'lng' => -65.42650,
                'bidon_balance' => 3,
                'sifon_balance' => 0,
                'price_agua' => 5800.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Verdulería Los Andes',
                'phone' => '387 433-5544',
                'rep' => $repEsteban,
                'street' => 'Calle 12 de Octubre 310',
                'ref' => 'Frente a la feria',
                'neighborhood' => 'Limache Etapa 1',
                'lat' => -24.83200,
                'lng' => -65.42400,
                'bidon_balance' => 4,
                'sifon_balance' => 4,
                'price_agua' => 5700.00,
                'initial_debt' => 7800.00,
            ],
            [
                'name' => 'Kiosco Don Ramón',
                'phone' => '387 434-9900',
                'rep' => $repEsteban,
                'street' => 'Av. Las Palmeras 2400',
                'ref' => null,
                'neighborhood' => 'Limache Etapa 5',
                'lat' => -24.84400,
                'lng' => -65.41900,
                'bidon_balance' => 0,
                'sifon_balance' => 0,
                'price_agua' => 5800.00,
                'initial_debt' => 0.00,
                'enabled' => false,
            ],
        ];

        // E. Lucía's customers: her route waits for the truck load and the dispatch.
        $customersConfig = [
            ...$customersConfig,
            [
                'name' => 'Guada Guaymás',
                'phone' => '387 433-7781',
                'rep' => $repLucia,
                'street' => 'Avenida Bolivia 3120',
                'ref' => 'Portón verde',
                'neighborhood' => 'Tres Cerritos',
                'lat' => -24.76800,
                'lng' => -65.40200,
                'bidon_balance' => 2,
                'sifon_balance' => 0,
                'price_agua' => 6000.00,
                'initial_debt' => 0.00,
            ],
            [
                'name' => 'Almacén Doña Rosa',
                'phone' => '387 433-7782',
                'rep' => $repLucia,
                'street' => 'Urquiza 820',
                'ref' => 'Esquina, cartel rojo',
                'neighborhood' => 'Centro',
                'lat' => -24.79100,
                'lng' => -65.41300,
                'bidon_balance' => 6,
                'sifon_balance' => 3,
                'price_agua' => 5600.00,
                'initial_debt' => 12000.00,
            ],
            [
                'name' => 'Norma Cruz',
                'phone' => '387 433-7783',
                'rep' => $repLucia,
                'street' => 'Pasaje Las Rosas 45',
                'ref' => null,
                'neighborhood' => 'El Huaico',
                'lat' => -24.73800,
                'lng' => -65.40300,
                'bidon_balance' => 1,
                'sifon_balance' => 0,
                'price_agua' => 6000.00,
                'initial_debt' => 0.00,
            ],
        ];

        // F. The rest of Esteban's and Lucía's portfolios: neighbors, almacenes, comedores and kioscos.
        // [name, street, visual reference, neighborhood, lat, lng, bidones, sifones, agua price, opening debt, extra address fields]
        $estebanNewCustomers = [
            ['Despensa Los Hermanos', 'Av. Paraguay 3650', 'Persiana verde, al lado de la carnicería', 'Limache', -24.83580, -65.42620, 6, 4, 5600.00, 9500.00],
            ['Carmen Chocobar', 'Pasaje Los Lapachos 112', null, 'Limache', -24.83420, -65.42710, 2, 0, 6000.00, 0.00],
            ['Raúl Vilte', 'Los Jacarandás 230', 'Casa de dos pisos frente a la plaza', 'Limache', -24.83710, -65.42480, 3, 2, 6000.00, 0.00],
            ['Comedor Pancitas Llenas', 'Av. Chile 1520', 'Salón al lado de la capilla', 'Limache', -24.83630, -65.42350, 10, 0, 5200.00, 15600.00],
            ['Valeria Sajama', 'Pasaje Las Tipas 78', null, 'Limache', -24.83490, -65.42830, 2, 0, 6000.00, 0.00],
            ['Kiosco El Pato', 'Calle 12 de Octubre 450', 'Ventanita con rejas amarillas', 'Limache Etapa 1', -24.83150, -65.42330, 3, 4, 5800.00, 0.00],
            ['Hugo Cardozo', 'Los Cardones 315', null, 'Limache Etapa 1', -24.83080, -65.42460, 2, 0, 6000.00, 6000.00],
            ['Patricia Liendro', 'Los Cardones 340', 'Portón verde, al lado de la verdulería', 'Limache Etapa 1', -24.83110, -65.42510, 2, 2, 6000.00, 0.00],
            ['Sergio Copa', 'Los Cardones 402', null, 'Limache Etapa 1', -24.83020, -65.42590, 1, 0, 6000.00, 0.00],
            ['Andrea Aramayo', 'Calle 12 de Octubre 515', null, 'Limache Etapa 1', -24.83240, -65.42280, 2, 0, 6000.00, 0.00],
            ['Comedor Los Peques', 'Av. Chile 1950', 'Entrada por el costado de la escuela', 'Limache Etapa 2', -24.83860, -65.42130, 8, 0, 5200.00, 0.00],
            ['Miguel Gutiérrez', 'Las Achiras 40', null, 'Limache Etapa 2', -24.83920, -65.42250, 2, 0, 6000.00, 0.00],
            ['Lorena Farfán', 'Las Achiras 88', 'Casa celeste con rejas negras', 'Limache Etapa 2', -24.83950, -65.42300, 3, 2, 6000.00, 12000.00],
            ['Ramón Choque', 'Las Achiras 140', null, 'Limache Etapa 2', -24.83990, -65.42370, 2, 0, 6000.00, 0.00],
            ['Almacén Don Pedro', 'Pasaje Los Molles 120', 'Esquina, toldo azul', 'Limache Etapa 3', -24.84060, -65.42760, 6, 4, 5600.00, 8400.00],
            ['Gabriela Tapia', 'Pasaje Los Molles 88', null, 'Limache Etapa 3', -24.84030, -65.42790, 2, 0, 6000.00, 0.00],
            ['Daniel Saravia', 'Pasaje Los Talas 210', 'Casa con quincho al fondo, tocar timbre', 'Limache Etapa 3', -24.84120, -65.42880, 3, 0, 6000.00, 0.00],
            ['Mónica Ríos', 'Pasaje Los Talas 245', null, 'Limache Etapa 3', -24.84150, -65.42910, 2, 2, 6000.00, 0.00],
            ['Javier Condorí', 'Pasaje Los Algarrobos 33', null, 'Limache Etapa 3', -24.84210, -65.42840, 1, 0, 6000.00, 6000.00],
            ['Kiosco La Esquina', 'Av. Las Palmeras 2250', 'Frente a la parada de colectivos', 'Limache Etapa 5', -24.84350, -65.41950, 3, 4, 5800.00, 0.00],
            ['Silvina Cáceres', 'Av. Las Palmeras 2150', null, 'Limache Etapa 5', -24.84320, -65.41990, 2, 0, 6000.00, 0.00, ['floor' => '1', 'apartment' => 'A']],
            ['Oscar Zerpa', 'Los Lirios 315', 'Casa con portón de chapa marrón', 'Limache Etapa 5', -24.84460, -65.42080, 2, 0, 6000.00, 0.00],
            ['Natalia Mendoza', 'Los Lirios 360', null, 'Limache Etapa 5', -24.84490, -65.42120, 2, 0, 6000.00, 0.00],
        ];

        $luciaNewCustomers = [
            ['Ana Lamas', 'Los Arces 156', 'Casa de ladrillo visto con rejas blancas', 'Tres Cerritos', -24.76950, -65.39850, 2, 0, 6000.00, 0.00],
            ['Martín Avendaño', 'Los Nogales 98', null, 'Tres Cerritos', -24.77020, -65.39740, 2, 2, 6000.00, 0.00],
            ['Almacén Tres Cerritos', 'Av. Los Incas 2550', 'Esquina, cartel amarillo', 'Tres Cerritos', -24.76880, -65.40050, 6, 4, 5600.00, 10400.00],
            ['Florencia Ibarra', 'Los Fresnos 210', null, 'Tres Cerritos', -24.77150, -65.39920, 2, 0, 6000.00, 0.00],
            ['Ricardo Yapura', 'Los Paraísos 77', null, 'Tres Cerritos', -24.77080, -65.40110, 1, 0, 6000.00, 6000.00],
            ['Kiosco Doña Pocha', 'Urquiza 650', 'Al lado de la farmacia', 'Centro', -24.79060, -65.41150, 3, 4, 5800.00, 0.00],
            ['Claudia Herrera', 'Lerma 370', 'Edificio con portero eléctrico', 'Centro', -24.79210, -65.40980, 2, 0, 6000.00, 0.00, ['floor' => '3', 'apartment' => 'D']],
            ['Pablo Sosa', 'Santiago del Estero 920', null, 'Centro', -24.78720, -65.41420, 2, 2, 6000.00, 0.00],
            ['Despensa El Ahorro', 'Rivadavia 1010', null, 'Centro', -24.78560, -65.41580, 5, 0, 5600.00, 7200.00],
            ['Comedor San Cayetano', 'Pasaje Las Rosas 130', 'Salón al lado de la capilla', 'El Huaico', -24.73750, -65.40250, 10, 0, 5200.00, 20800.00],
            // A few of her customers live where Esteban delivers, so "Repartir ruta" can suggest him.
            ['Beatriz Guanuco', 'Los Cardones 280', null, 'Limache Etapa 1', -24.83060, -65.42420, 2, 0, 6000.00, 0.00],
            ['Luis Abán', 'Calle 12 de Octubre 380', 'Casa con reja verde y perro grande', 'Limache Etapa 1', -24.83190, -65.42360, 2, 2, 6000.00, 0.00],
            ['Kiosco Los Pibes', 'Balcarce 2210', null, 'El Huaico', -24.73520, -65.40140, 3, 4, 5800.00, 0.00],
            ['Verónica Paz', 'Av. Bolivia 4100', 'Portón blanco, timbre a la izquierda', 'Grand Bourg', -24.76420, -65.42180, 2, 0, 6000.00, 0.00],
            ['Gustavo Medina', 'Los Cebiles 220', null, 'Grand Bourg', -24.76580, -65.42410, 2, 2, 6000.00, 6000.00],
            ['Almacén La Abuela', 'Av. Paraguay 3720', null, 'Limache', -24.83620, -65.42580, 6, 4, 5600.00, 0.00],
            ['Mirta Ortiz', 'Pasaje Los Lapachos 150', null, 'Limache', -24.83450, -65.42740, 2, 0, 6000.00, 0.00],
        ];

        // Juan's long day: 42 more customers around Centro, Tres Cerritos, Grand Bourg and San Bernardo, 50 stops in all.
        $juanNewCustomers = [
            ['Rosa Arias', 'Caseros 820', 'Puerta de madera con aldaba', 'Centro', -24.78920, -65.40870, 2, 0, 6000.00, 0.00],
            ['Héctor Gareca', 'Mitre 560', null, 'Centro', -24.78510, -65.41090, 2, 2, 6000.00, 0.00, ['floor' => '2', 'apartment' => 'A']],
            ['Kiosco La Catedral', 'España 520', 'Al lado del cajero automático', 'Centro', -24.78800, -65.40990, 3, 4, 5800.00, 0.00],
            ['Susana Lera', 'Buenos Aires 310', null, 'Centro', -24.78980, -65.41190, 2, 0, 6000.00, 0.00],
            ['Fernando Ruiz', 'Córdoba 245', 'Edificio gris, portero eléctrico', 'Centro', -24.79120, -65.40750, 2, 0, 6000.00, 0.00, ['floor' => '4', 'apartment' => 'C']],
            ['Almacén El Cabildo', 'Zuviría 210', null, 'Centro', -24.78670, -65.41250, 6, 4, 5600.00, 8400.00],
            ['Liliana Romero', 'Belgrano 1020', null, 'Centro', -24.78590, -65.41420, 2, 0, 6000.00, 0.00],
            ['Walter Cuello', 'Alvarado 760', 'Frente a la escuela, reja negra', 'Centro', -24.79040, -65.40630, 2, 2, 6000.00, 0.00],
            ['Comedor Santa Rita', 'Ituzaingó 430', 'Salón parroquial, entrada lateral', 'Centro', -24.79210, -65.40560, 10, 0, 5200.00, 15600.00],
            ['Elsa Burgos', 'Lerma 540', null, 'Centro', -24.79300, -65.41030, 1, 0, 6000.00, 0.00],
            ['Marcelo Tejerina', 'Los Ceibos 310', 'Casa esquina con rejas blancas', 'Tres Cerritos', -24.77060, -65.39680, 2, 0, 6000.00, 0.00],
            ['Adriana Juárez', 'Los Robles 145', null, 'Tres Cerritos', -24.77180, -65.39810, 2, 2, 6000.00, 0.00],
            ['Kiosco Los Ceibos', 'Av. Los Incas 2410', 'Ventanita al lado del lavadero', 'Tres Cerritos', -24.76920, -65.39980, 3, 4, 5800.00, 0.00],
            ['Gerardo Molina', 'Los Aromos 88', null, 'Tres Cerritos', -24.77240, -65.39720, 2, 0, 6000.00, 6000.00],
            ['Paula Escudero', 'Los Jazmines 230', 'Portón gris, timbre arriba', 'Tres Cerritos', -24.76990, -65.39560, 2, 0, 6000.00, 0.00],
            ['Ramiro Paz', 'Los Tilos 67', null, 'Tres Cerritos', -24.77120, -65.40060, 1, 0, 6000.00, 0.00],
            ['Almacén Los Cerritos', 'Av. Reyes Católicos 1550', 'Esquina, toldo verde', 'Tres Cerritos', -24.77300, -65.40150, 6, 4, 5600.00, 0.00],
            ['Cecilia Alancay', 'Los Olmos 190', null, 'Tres Cerritos', -24.77010, -65.39890, 2, 0, 6000.00, 0.00],
            ['Julio Vargas', 'Los Arces 260', null, 'Tres Cerritos', -24.76970, -65.39790, 2, 2, 6000.00, 0.00],
            ['Inés Barrionuevo', 'Los Álamos 260', 'Casa con galería y macetas', 'Lomas De Tres Cerritos', -24.76640, -65.39130, 2, 0, 6000.00, 0.00],
            ['Kiosco La Loma', 'Los Pinos 75', null, 'Lomas De Tres Cerritos', -24.76580, -65.39260, 3, 4, 5800.00, 0.00],
            ['Ernesto Saavedra', 'Los Pinos 140', null, 'Lomas De Tres Cerritos', -24.76530, -65.39190, 2, 0, 6000.00, 0.00],
            ['Gladys Corimayo', 'Las Magnolias 33', 'Portón de rejas verdes', 'Lomas De Tres Cerritos', -24.76760, -65.39050, 2, 2, 6000.00, 0.00],
            ['Nicolás Aguirre', 'Las Magnolias 90', null, 'Lomas De Tres Cerritos', -24.76810, -65.38990, 1, 0, 6000.00, 0.00],
            ['Carolina Ayala', 'Av. Bolivia 4450', 'Dúplex del fondo, tocar timbre B', 'Grand Bourg', -24.76450, -65.42390, 2, 0, 6000.00, 0.00],
            ['Despensa Grand Bourg', 'Los Cebiles 140', 'Persiana azul', 'Grand Bourg', -24.76540, -65.42290, 6, 4, 5600.00, 10200.00],
            ['Alberto Nieva', 'Los Cebiles 310', null, 'Grand Bourg', -24.76620, -65.42470, 2, 0, 6000.00, 0.00],
            ['Mariela Soria', 'Las Acacias 210', null, 'Grand Bourg', -24.76760, -65.42410, 2, 2, 6000.00, 0.00],
            ['Comedor Los Gurises', 'Las Tipas 55', 'Salón con mural de colores', 'Grand Bourg', -24.76690, -65.42140, 8, 0, 5200.00, 0.00],
            ['Roberto Cruz', 'Los Lapachos 420', null, 'Grand Bourg', -24.76380, -65.42230, 2, 0, 6000.00, 6000.00],
            ['Daniela Funes', 'Los Lapachos 470', 'Casa amarilla con quincho', 'Grand Bourg', -24.76350, -65.42290, 2, 0, 6000.00, 0.00],
            ['Kiosco El Bourg', 'Av. Bolivia 4180', null, 'Grand Bourg', -24.76480, -65.42110, 3, 4, 5800.00, 0.00],
            ['Ignacio Salva', 'Las Palmas 66', null, 'Grand Bourg', -24.76830, -65.42200, 1, 0, 6000.00, 0.00],
            ['Teresa Cañizares', 'Av. Virrey Toledo 420', 'Casa de piedra al pie del cerro', 'San Bernardo', -24.79250, -65.40140, 2, 0, 6000.00, 0.00],
            ['Mario Gallardo', 'Av. Virrey Toledo 510', null, 'San Bernardo', -24.79280, -65.40080, 2, 2, 6000.00, 0.00],
            ['Almacén San Bernardo', 'Bolívar 1240', 'Esquina, cartel azul', 'San Bernardo', -24.79360, -65.40210, 6, 4, 5600.00, 0.00],
            ['Lucas Villagra', 'Bolívar 1310', null, 'San Bernardo', -24.79400, -65.40170, 2, 0, 6000.00, 0.00],
            ['Viviana Díaz', 'Pasaje Mollinedo 45', 'Casa con portón de hierro forjado', 'San Bernardo', -24.79330, -65.39960, 2, 0, 6000.00, 0.00],
            ['Ezequiel Lamas', 'Pasaje Mollinedo 82', null, 'San Bernardo', -24.79370, -65.39920, 1, 0, 6000.00, 6000.00],
            ['Kiosco El Teleférico', 'Av. Hipólito Yrigoyen 50', 'Frente a la base del teleférico', 'San Bernardo', -24.79190, -65.40020, 3, 4, 5800.00, 0.00],
            ['Norberto Ibáñez', 'Paseo Güemes 410', null, 'San Bernardo', -24.79140, -65.39900, 2, 0, 6000.00, 0.00, ['floor' => '1', 'apartment' => 'B']],
            ['Graciela Tula', 'Paseo Güemes 455', null, 'San Bernardo', -24.79170, -65.39850, 2, 2, 6000.00, 0.00],
        ];

        foreach ([[$repEsteban, '387 435-', $estebanNewCustomers], [$repLucia, '387 436-', $luciaNewCustomers], [$repJuan, '387 437-', $juanNewCustomers]] as [$rep, $phonePrefix, $rows]) {
            foreach ($rows as $index => [$name, $street, $ref, $neighborhood, $lat, $lng, $bidones, $sifones, $priceAgua, $debt]) {
                $customersConfig[] = [
                    'name' => $name,
                    'phone' => $phonePrefix.(1001 + $index),
                    'rep' => $rep,
                    'street' => $street,
                    'ref' => $ref,
                    'neighborhood' => $neighborhood,
                    'lat' => $lat,
                    'lng' => $lng,
                    'bidon_balance' => $bidones,
                    'sifon_balance' => $sifones,
                    'price_agua' => $priceAgua,
                    'initial_debt' => $debt,
                    ...($rows[$index][10] ?? []),
                ];
            }
        }

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
                ['customer' => 'Marta Tolaba', 'time' => '12:50', 'absent' => true],
            ]],
            [$repCarlos, '07:45', '13:15', [
                ['customer' => 'Panadería y Confitería La Unión', 'time' => '08:10', 'qty_agua' => 4, 'qty_soda' => 6, 'method' => $cashMethod],
                ['customer' => 'Gimnasio Go Fitness Salta', 'time' => '09:05', 'qty_agua' => 8, 'qty_soda' => 0, 'method' => $transferMethod],
                ['customer' => 'Kiosco El Sol', 'time' => '10:20', 'qty_agua' => 3, 'qty_soda' => 4, 'method' => $cashMethod],
                ['customer' => 'Diego Ramírez', 'time' => '11:40', 'qty_agua' => 2, 'qty_soda' => 0, 'method' => null],
            ]],
            [$repMarcos, '08:30', '12:30', [
                ['customer' => 'Crossfit Sur Box', 'time' => '09:00', 'qty_agua' => 6, 'qty_soda' => 0, 'method' => $transferMethod],
                ['customer' => 'Taller Mecánico Tavella Motors', 'time' => '10:10', 'qty_agua' => 5, 'qty_soda' => 0, 'method' => null],
                ['customer' => 'Silvia Morales', 'time' => '11:30', 'qty_agua' => 3, 'qty_soda' => 0, 'method' => $cashMethod],
            ]],
            [$repEsteban, '08:00', '13:00', [
                ['customer' => 'Supermercado El Tunal', 'time' => '08:25', 'qty_agua' => 10, 'qty_soda' => 8, 'method' => $transferMethod],
                ['customer' => 'Verdulería Los Andes', 'time' => '09:10', 'qty_agua' => 3, 'qty_soda' => 2, 'method' => $cashMethod],
                ['customer' => 'Escuela N° 4032 Limache', 'time' => '10:00', 'qty_agua' => 6, 'qty_soda' => 0, 'method' => null],
                ['customer' => 'Elena Quispe', 'time' => '11:15', 'qty_agua' => 2, 'qty_soda' => 0, 'method' => $cashMethod],
                ['customer' => 'Jorge Mamaní', 'time' => '12:20', 'qty_agua' => 2, 'qty_soda' => 0, 'method' => $cashMethod],
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

        // Today's sales charge what each customer pays: their agreement or, without one, the list price.
        $priceOf = fn (Customer $customer, Product $product): float => (float) app(PriceResolver::class)->priceFor($customer, $product, $today)->unitPrice;

        // --- ROUTE 1: Juan Pérez (IN_PROGRESS - Centro, Tres Cerritos, Grand Bourg & San Bernardo, 50 stops) ---
        $routeJuan = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repJuan->id, 'date' => $today->toDateString()],
            [
                'status' => 'IN_PROGRESS',
                'started_at' => $today->copy()->setHour(8)->setMinute(15),
                'dispatched_at' => $today->copy()->setHour(8),
                'dispatched_by' => $admin->id,
                'enabled' => true,
            ]
        );

        // A long day of 50 stops: loaded minus sold below, agua 160 - 22, soda 70 - 10.
        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productAgua20L->id],
            ['quantity' => 160.00, 'loaded_at' => $routeJuan->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
        );
        RouteInventoryLoad::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productSoda2L->id],
            ['quantity' => 70.00, 'loaded_at' => $routeJuan->started_at->copy()->subMinutes(30), 'created_by' => $admin->id]
        );

        RouteInventory::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productAgua20L->id],
            ['current_quantity' => 138.00, 'created_by' => $admin->id, 'enabled' => true]
        );
        RouteInventory::firstOrCreate(
            ['route_id' => $routeJuan->id, 'product_id' => $productSoda2L->id],
            ['current_quantity' => 60.00, 'created_by' => $admin->id, 'enabled' => true]
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
                // The app only pushes finished visits, so the next stop is pending, never a server visit in progress.
                'customer' => 'Franco Calisaya',
                'status' => 'PENDING',
                'time' => null,
                'scheduled' => '12:00',
                'qty_agua' => 2,
                'qty_soda' => 0,
                'method' => null,
                'is_credit' => false,
            ],
            [
                'customer' => 'Marta Tolaba',
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
                    $totalSale = ($s['qty_agua'] * $priceOf($cust, $productAgua20L)) + ($s['qty_soda'] * $priceOf($cust, $productSoda2L));
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
                            ['quantity' => $s['qty_agua'], 'unit_price' => $priceOf($cust, $productAgua20L)]
                        );
                    }
                    if ($s['qty_soda'] > 0) {
                        SaleDetail::firstOrCreate(
                            ['sale_id' => $sale->id, 'product_id' => $productSoda2L->id],
                            ['quantity' => $s['qty_soda'], 'unit_price' => $priceOf($cust, $productSoda2L)]
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

        // The rest of Juan's 50 stops, still to be made, every 5 minutes from 13:05: enough to page through them in the app.
        foreach (array_column($juanNewCustomers, 0) as $position => $customerName) {
            $cust = $createdCustomers[$customerName];
            $scheduledTime = $today->copy()->setTime(13, 5)->addMinutes(5 * $position)->format('H:i');

            RouteStop::firstOrCreate(
                ['route_id' => $routeJuan->id, 'customer_id' => $cust->id],
                ['address_id' => $cust->primaryAddress->id, 'scheduled_time' => $scheduledTime, 'enabled' => true]
            );
        }

        // A customer called asking for a visit today: it rides on Juan's pending stop, so the app shows a requested visit.
        $requestingCustomer = $createdCustomers['Marta Tolaba'];
        if (ScheduledVisit::query()->where('customer_id', $requestingCustomer->id)->whereDate('date', $today)->doesntExist()) {
            app(VisitScheduler::class)->schedule($requestingCustomer, $today, '13:00', 'Llamó, quiere 2 bidones', $admin);
        }

        // --- ROUTE 2: Carlos Gómez (COMPLETED - Castañares & El Huaico) ---
        $routeCarlos = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repCarlos->id, 'date' => $today->toDateString()],
            [
                'status' => 'COMPLETED',
                'started_at' => $today->copy()->setHour(7)->setMinute(45),
                'dispatched_at' => $today->copy()->setHour(7)->setMinute(30),
                'dispatched_by' => $admin->id,
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
                'customer' => 'Diego Ramírez',
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

            $totalSale = ($s['qty_agua'] * $priceOf($cust, $productAgua20L)) + ($s['qty_soda'] * $priceOf($cust, $productSoda2L));
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
                    ['quantity' => $s['qty_agua'], 'unit_price' => $priceOf($cust, $productAgua20L)]
                );
            }
            if ($s['qty_soda'] > 0) {
                SaleDetail::firstOrCreate(
                    ['sale_id' => $sale->id, 'product_id' => $productSoda2L->id],
                    ['quantity' => $s['qty_soda'], 'unit_price' => $priceOf($cust, $productSoda2L)]
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
                'dispatched_at' => $today->copy()->setHour(8)->setMinute(15),
                'dispatched_by' => $admin->id,
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
                'customer' => 'Silvia Morales',
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

            $totalSale = $s['qty_agua'] * $priceOf($cust, $productAgua20L);
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
                ['quantity' => $s['qty_agua'], 'unit_price' => $priceOf($cust, $productAgua20L)]
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

        // The hardware store was closed at 11:30: Marcos rescheduled the visit for 16:00.
        $absentCustomer = $createdCustomers['Ferretería Santa Ana'];
        $absentStop = RouteStop::firstOrCreate(
            ['route_id' => $routeMarcos->id, 'customer_id' => $absentCustomer->id, 'is_rescheduled_from' => null],
            ['address_id' => $absentCustomer->primaryAddress->id, 'scheduled_time' => '11:30', 'enabled' => true]
        );

        Visit::firstOrCreate(
            ['route_stop_id' => $absentStop->id],
            [
                'customer_id' => $absentCustomer->id,
                'driver_id' => $repMarcos->id,
                'outcome_id' => $outcomeReschedule->id,
                'arrived_at' => $today->copy()->setHour(11)->setMinute(35),
                'latitude' => $absentCustomer->primaryAddress->latitude,
                'longitude' => $absentCustomer->primaryAddress->longitude,
                'status' => 'FINISHED',
                'notes' => 'Local cerrado, vuelvo a la tarde',
                'created_by' => $repMarcos->user_id,
                'enabled' => true,
            ]
        );

        RouteStop::firstOrCreate(
            ['route_id' => $routeMarcos->id, 'customer_id' => $absentCustomer->id, 'is_rescheduled_from' => $absentStop->id],
            ['address_id' => $absentStop->address_id, 'scheduled_time' => '16:00', 'enabled' => true]
        );

        // --- ROUTE 4: Esteban Benítez (PLANNED, not dispatched - Limache) ---
        // Left as routes:generate leaves it, with no truck load yet: "Repartir ruta" on Lucía's route suggests him in
        // the Limache neighborhoods they share, and the admin can still load and dispatch it from /routes.
        $routeEsteban = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repEsteban->id, 'date' => $today->toDateString()],
            ['status' => 'PLANNED', 'enabled' => true]
        );

        // 29 visits every 10 minutes from 08:30 (the greengrocer first).
        $estebanStops = [
            'Verdulería Los Andes',
            'Supermercado El Tunal',
            'Escuela N° 4032 Limache',
            'Consultorio Odontológico Sonrisas',
            'Elena Quispe',
            'Jorge Mamaní',
            ...array_column($estebanNewCustomers, 0),
        ];

        foreach ($estebanStops as $position => $customerName) {
            $cust = $createdCustomers[$customerName];
            $scheduledTime = $today->copy()->setTime(8, 30)->addMinutes(10 * $position)->format('H:i');

            RouteStop::firstOrCreate(
                ['route_id' => $routeEsteban->id, 'customer_id' => $cust->id, 'is_rescheduled_from' => null],
                ['address_id' => $cust->primaryAddress->id, 'scheduled_time' => $scheduledTime, 'enabled' => true]
            );
        }

        // --- ROUTE 5: Lucía Fernández (PLANNED, not dispatched) ---
        // As routes:generate leaves it at 00:30: the admin loads the truck and dispatches it from /routes, then the app gets it.
        $routeLucia = DeliveryRoute::firstOrCreate(
            ['assigned_driver_id' => $repLucia->id, 'date' => $today->toDateString()],
            ['status' => 'PLANNED', 'enabled' => true]
        );

        // 20 stops every 15 minutes from 08:30, over Tres Cerritos, Centro and Grand Bourg (where Juan delivers), El Huaico,
        // and Limache and Limache Etapa 1 (where Esteban delivers).
        $luciaStops = ['Guada Guaymás', 'Almacén Doña Rosa', 'Norma Cruz', ...array_column($luciaNewCustomers, 0)];

        foreach ($luciaStops as $position => $customerName) {
            $cust = $createdCustomers[$customerName];
            $scheduledTime = $today->copy()->setTime(8, 30)->addMinutes(15 * $position)->format('H:i');

            RouteStop::firstOrCreate(
                ['route_id' => $routeLucia->id, 'customer_id' => $cust->id, 'is_rescheduled_from' => null],
                ['address_id' => $cust->primaryAddress->id, 'scheduled_time' => $scheduledTime, 'enabled' => true]
            );
        }
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
            ['status' => 'COMPLETED', 'started_at' => $at($startedAt), 'completed_at' => $at($completedAt), 'dispatched_at' => $at($startedAt)->subMinutes(15), 'enabled' => true]
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
                $movement = ContainerMovement::firstOrCreate(
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

                // Moved on that day's visit, not when the seeder ran: a past day must not show up in today's figures.
                if ($movement->wasRecentlyCreated) {
                    $movement->created_at = $visit->arrived_at;
                    $movement->updated_at = $visit->arrived_at;
                    $movement->save();
                }
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
