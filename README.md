# 💧 Marilin Web - Panel de Administración y Backend Central

> Panel web administrativo y backend central para la gestión integral del ecosistema **Marilin** (logística, distribución de agua y soda, ruteo, inventario, facturación y cuentas corrientes).

**Marilin Web** actúa como el núcleo de control y servidor central para la aplicación móvil [Marilin App (`app/`)](../app/README.md) desarrollada en Flutter. Permite a los administradores planificar recorridos, auditar las ventas y cobros en tiempo real, supervisar saldos de clientes, controlar envases retornables y gestionar la sincronización de los repartidores.

---

## 🎯 Propósito y Relación con la App Móvil (Flutter)

El ecosistema **Marilin** se compone de dos piezas fundamentales:

1. **[Marilin Mobile (`app/`)](../app/README.md):** Aplicación móvil *offline-first* en Flutter para los repartidores en calle (toma de pedidos, registro de cobros, geolocalización de paradas y control de envases).
2. **Marilin Web (`marilin-web/`):** Plataforma web administrativa en Laravel + Inertia.js (React + TypeScript) que centraliza la base de datos PostgreSQL, ofrece APIs de sincronización bidireccional y dashboards de control en tiempo real.

```
                      ┌─────────────────────────────────────────┐
                      │        ADMINISTRADOR / OFICINA          │
                      │       Marilin Web (Inertia/React)       │
                      └────────────────────┬────────────────────┘
                                           │
                                  REST APIs / Sync
                                           │
                      ┌────────────────────┴────────────────────┐
                      │    SERVIDOR CENTRAL (Laravel 12 Sail)   │
                      │  PostgreSQL 16 · Redis · Mailpit · Pest │
                      └────────────────────┬────────────────────┘
                                           │
                                  Sincronización
                                  (Offline-First)
                                           │
                      ┌────────────────────┴────────────────────┐
                      │         REPARTIDORES EN CALLE           │
                      │       Marilin App (Flutter/Drift)       │
                      └─────────────────────────────────────────┘
```

---

## ✨ Módulos y Funcionalidades Principales

- **👥 Clientes y Zonas:** Ficha completa de clientes, geolocalización satelital, múltiples direcciones, horarios preferidos de entrega y asignación de repartidor.
- **🚚 Planificación de Rutas:** Generación automática de la ruta diaria de cada repartidor a partir de los días y horarios de visita de sus clientes (paradas ordenadas por horario), reprogramación de visitas y monitoreo del estado de las entregas.
- **📦 Inventario y Envases Retornables:** Control de stock en depósito y carga en camión (`route_inventory`), seguimiento de envases entregados/devueltos por cliente (`customer_container_balances`).
- **💰 Facturación y Acuerdos de Precios:** Configuración de listas de precios especiales por cliente (`customer_agreements`), registro de ventas y emisión de comprobantes.
- **💳 Cuentas Corrientes y Tesorería:** Registro de pagos (efectivo, transferencias, tarjetas), libro de movimientos contables (`customer_account_entries`) y saldo histórico de clientes.
- **🔐 Usuarios:** El administrador crea las cuentas de los repartidores (no hay registro público), edita sus datos, les cambia la contraseña y las habilita o deshabilita. Deshabilitar corta sus sesiones en la app y en el panel.
- **🔄 APIs de Sincronización Móvil:** Endpoints optimizados para recibir las visitas, ventas y cobranzas offline de la app Flutter al recuperar conectividad.

---

## 🛠️ Stack Tecnológico

| Área | Tecnología |
|---|---|
| **Backend** | [Laravel 12](https://laravel.com) · PHP 8.5 |
| **Entorno & Contenedores** | [Laravel Sail](https://laravel.com/docs/sail) (Docker & Docker Compose) |
| **Frontend** | [Inertia.js v2](https://inertiajs.com) · [React 18](https://react.dev) · [TypeScript](https://www.typescriptlang.org) |
| **Estilos & UI** | [Tailwind CSS v3](https://tailwindcss.com) · Headless UI · Lucide Icons |
| **Base de Datos** | [PostgreSQL 16](https://www.postgresql.org) |
| **Caché y Colas** | [Redis](https://redis.io) |
| **Testing** | [Pest PHP 5](https://pestphp.com) (41 tests unitarios y de integración) |
| **Linter & Formato** | Laravel Pint |

---

## 📋 Requisitos Previos

- **Docker Desktop** o **Docker Engine** + **Docker Compose** instalados y activos.
- **Git**.
- *(No requiere tener instalado PHP ni PostgreSQL en tu máquina host; Sail gestiona todo el entorno en Docker)*.

---

## 🚀 Instalación y Puesta en Marcha

### 1. Iniciar los contenedores con Laravel Sail

Desde la carpeta `marilin-web`:

```bash
cd marilin-web

# Iniciar los servicios en segundo plano
./vendor/bin/sail up -d
```

### 2. Ejecutar las migraciones y seeders de prueba

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

> **Nota:** El seeder carga usuarios de prueba (Admin y Repartidores), catálogo de productos (bidones 20L, sifones 2L), clientes con ubicaciones reales, rutas, acuerdos de precios y registros contables.

### 3. Compilar los assets del frontend

```bash
# Modo desarrollo con recarga en vivo (HMR)
./vendor/bin/sail npm run dev

# O compilar para producción
./vendor/bin/sail npm run build
```

### 4. Tareas programadas

El scheduler de Laravel tiene que estar corriendo para que existan las rutas del día:

- **Local (Sail):** el servicio `scheduler` de `compose.yaml` corre `php artisan schedule:work` y arranca con `./vendor/bin/sail up -d`. Para ver qué corrió: `./vendor/bin/sail logs -f scheduler`.
- **Producción:** una línea de cron que llama a `schedule:run` cada minuto, con el usuario que corre la aplicación:

  ```cron
  * * * * * cd /path/to/marilin-web && php artisan schedule:run >> /dev/null 2>&1
  ```

Tareas que corre (definidas en `routes/console.php`):

- `routes:generate` (00:30): crea la ruta del día de cada repartidor habilitado con sus clientes que tienen ese día de visita, usando la dirección principal y ordenando las paradas por el inicio de la franja horaria. Si el repartidor ya tiene ruta ese día y sigue planificada (por ejemplo, la creó una visita agendada para hoy), le agrega las paradas por preferencia que falten sin duplicar clientes; una ruta ya iniciada, completada o cancelada no se toca. Se puede correr a mano para otra fecha con `--date=YYYY-MM-DD`.
- `review:flag-rescheduled-stops` (01:00): manda a revisión las paradas reprogramadas que terminaron el día sin visita.
- `review:flag-unvisited-stops` (01:00): manda a revisión las paradas de días anteriores que quedaron sin visita (la app solo muestra la ruta de hoy).
- `routes:close-past` (01:00): completa las rutas de días anteriores que quedaron planificadas o en curso.

Los horarios son de Argentina (`APP_TIMEZONE`, por defecto `America/Argentina/Buenos_Aires`).

### 5. Acceso a las aplicaciones locales

- **Panel Web Marilin:** [http://localhost](http://localhost)
- **Buzón de Correos de Prueba (Mailpit):** [http://localhost:8025](http://localhost:8025)
- **Base de Datos PostgreSQL:** Puerto `5432` (`host: localhost`, `user: sail`, `password: password`, `database: marilin_web`)

---

## 🧪 Ejecución de Tests

El proyecto cuenta con una suite completa de pruebas unitarias y de integración desarrolladas con **Pest PHP**:

```bash
# Correr todos los tests
./vendor/bin/sail test

# Correr tests con cobertura
./vendor/bin/sail test --coverage
```

---

## 📂 Estructura del Código Backend

```
marilin-web/
├── app/                          # Un módulo por tema del dominio (ADR 0003)
│   ├── Accounts/                 # Usuarios, repartidores, login, tokens de la API
│   ├── Catalog/                  # Productos, tipos de envase, medios de pago
│   ├── Customers/
│   │   ├── Customers/            # Cliente, dirección, alta de cliente
│   │   └── Agreements/           # Acuerdos y precio del acuerdo
│   ├── Routes/
│   │   ├── Routes/               # Ruta, parada, generación diaria
│   │   └── Visits/               # Visita y resultados de visita
│   ├── Street/                   # StreetOperation: venta, cobro, envases, inventario de ruta
│   │   ├── Sales/  Payments/  Containers/  RouteStock/  Ledger/
│   ├── CashDesk/                 # Cifras del día y cierre de caja
│   ├── Review/                   # Para revisión: bandeja y marcas nocturnas
│   ├── Sync/                     # Bajada y Subida de la app
│   ├── Dashboard/                # Pantalla de inicio
│   ├── Shared/                   # Lo que no es de ningún tema
│   ├── Http/                     # Controller base y middleware de Inertia
│   └── Providers/
│   (cada módulo: Models/ Http/ Enums/ Console/ Database/Factories/ routes.php)
├── database/
│   ├── migrations/               # Una migración por tabla, en su forma final
│   └── seeders/                  # DatabaseSeeder con datos realistas
├── docs/adr/                     # Decisiones de arquitectura
├── resources/
│   └── js/
│       ├── modules/              # Espejo de app/: páginas, components/ y types.ts por tema
│       ├── ui/                   # Design system (Button, Field, DataTable, Badge, Modal…)
│       ├── lib/                  # format (plata, fechas, estados), useListFilters
│       ├── layouts/              # AdminLayout y GuestLayout
│       └── types/                # Tipos globales (PageProps, PaginatedData)
└── tests/
    ├── <Módulo>/                 # Tests de cada módulo
    ├── Feature/                  # Tests transversales (seeder, API retirada)
    └── Unit/                     # Tests de modelos que cruzan módulos
```

---

## 👥 Equipo de Desarrollo

- **Franco Calisaya**
- **Facundo Ramirez**
- **Christian Tolaba**

---

## 📄 Licencia

Este proyecto es software privado y confidencial para el ecosistema de distribución **Marilin**.
