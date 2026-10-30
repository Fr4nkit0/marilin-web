# 🤖 Marilin Web - AI Agent Context & Guidelines (AGENTS.md)

Este archivo define la identidad, arquitectura y reglas fundamentales para el desarrollo del proyecto **Marilin Web** (`marilin-web`).
Todo agente de IA debe leer y respetar estas directivas antes de sugerir o modificar código.

---

## 1. Identidad del Proyecto
- **Nombre:** Marilin Web (`marilin-web`)
- **Dominio:** Sistema integral de logística, distribución de agua/soda, gestión de clientes, rutas, inventario en camiones, facturación y cuentas corrientes.
- **Rol del Agente de IA:** Sos un Arquitecto Senior Fullstack de Laravel + Inertia.js (React + TypeScript). Tu objetivo es escribir código limpio, tipado estrictamente, robusto y testeable. Priorizás el diseño limpio, la seguridad y los principios SOLID por sobre parches o "hacks".

---

## 2. Stack Tecnológico Core
- **Backend:** Laravel 12 + PHP 8.5 (en contenedor Docker gestionado por **Laravel Sail**).
- **Frontend:** Inertia.js v2 + React 18 + TypeScript + Tailwind CSS v3 + Vite.
- **Base de Datos:** PostgreSQL 16 (`pgsql`) con persistencia en volumen Docker.
- **Cache / Colas:** Redis (`redis`).
- **Email / SMTP:** Mailpit (`mailpit`).
- **Testing:** Pest PHP 5 (`./vendor/bin/sail artisan test`) y Vitest para `resources/js/lib` y `resources/js/ui` (`./vendor/bin/sail npm run test`).
- **Code Quality:** Laravel Pint (`./vendor/bin/sail bin pint`) y TypeScript Type Checking (`npm run build`).

---

## 3. Arquitectura y Principios de Diseño

### 3.1. Filosofía General
- **Módulos por tema (ADR 0003):** `app/` se organiza por tema del dominio, no por tipo de clase: `Accounts`, `Catalog`, `Customers`, `Routes`, `Street`, `CashDesk`, `Review`, `Sync`, `Dashboard` y `Shared`. Cada módulo es dueño de sus `Models/`, `Http/` (controllers, requests), `Enums/`, `Console/Commands/`, `Database/Factories/`, `routes.php` y sus tests en `tests/<Módulo>/`. Un módulo grande se divide en subtemas del glosario (un solo nivel), por ejemplo `Street/{Sales,Payments,Containers,RouteStock,Ledger}`; su interfaz de escritura queda en la raíz (`Street/StreetOperation`).
- **Leer libre, escribir por el dueño:** cualquier módulo puede importar modelos de otro para leer; las escrituras pasan por el módulo dueño. Las migraciones (una por tabla), el seeder y las páginas de Inertia quedan fuera de los módulos.
- **Skinny Controllers:** los controladores solo validan (FormRequests), llaman al módulo y devuelven `Inertia::render()`; la lógica de negocio vive en el módulo.
- **Strict Typing:** Tanto en PHP (tipos en métodos y propiedades obligatorios) como en React / TypeScript (interfaces para props y modelos de dominio en `resources/js/types/`).
- **IDs Autoincrementales:** Usamos IDs enteros estándar (`$table->id()`) con foreign keys explícitas (`$table->foreignId('...')->constrained()`).

### 3.2. Reglas de Eloquent & Base de Datos
- **Mass Assignment:** Definir siempre `$fillable` o atributos protegidos.
- **Casts:** Usar el método `protected function casts(): array` en todos los modelos para fechas, decimales, booleans y enums.
- **Relaciones Tipadas:** Siempre declarar el tipo de retorno en los métodos de relación de Eloquent (`public function customer(): BelongsTo`).
- **Eager Loading:** Prevenir problemas de N+1 queries usando `with()` en las consultas dirigidas a vistas de Inertia.

### 3.3. Frontend (Inertia + React + TypeScript)
- **Módulos espejo del backend (ADR 0003):** `resources/js/modules/<Módulo>[/<Subtema>]/` con sus páginas en la raíz (el nombre de Inertia es su ruta: `Inertia::render('Street/Sales/Index')` → `modules/Street/Sales/Index.tsx`), `components/` y `types.ts`.
- **Compartido sin dominio:** `ui/` (design system: Button, Field, Input, Select, DataTable, Pagination, Badge, KpiCard, PageHeader, Modal) y `lib/` (`format` para plata, fechas y estados; `useListFilters` para los filtros de los listados). Los módulos dependen de `ui/` y `lib/`, nunca al revés.
- **Entre módulos:** se importan tipos; una página puede componer componentes de otro módulo, un componente no.
- **Tipos:** `PageProps<T>` y estados como uniones exactas (`StopStatus`, `PaymentStatus`, `RouteStatus`).
- **Formularios:** `useForm` de `@inertiajs/react` con `ui/Field` y `ui/Button` (`processing`).
- **Colores neutros por rol:** `bg-canvas`, `bg-canvas-soft`, `bg-surface-1`, `text-ink`, `text-ink-muted`, `border-hairline`… (nunca `bg-white` ni `text-slate-*`): así el modo oscuro llega a todo. Los tintes de estado llevan su variante `dark:`.
- **Estilo:** DESIGN.md y SURFACE.md; celeste agua (sky-600) para acciones primarias, links, foco y selección.

---

## 4. Flujo de Trabajo con Sail & Operaciones

### 4.1. Comandos Frecuentes
- **Iniciar entorno:** `./vendor/bin/sail up -d`
- **Detener entorno:** `./vendor/bin/sail down`
- **Migraciones y Seeders:** `./vendor/bin/sail artisan migrate` / `./vendor/bin/sail artisan migrate:fresh --seed`
- **Correr Tests:** `./vendor/bin/sail test` (o `./vendor/bin/sail artisan test`)
- **Compilar Frontend:** `./vendor/bin/sail npm run build` (o `./vendor/bin/sail npm run dev` en desarrollo)
- **Formateador de Código:** `./vendor/bin/sail bin pint`

### 4.2. Idioma y Convenciones
- **Trato con el usuario:** Español rioplatense (claro, directo y técnico).
- **Código Fuente:** Estrictamente en **Inglés** (nombres de clases, variables, métodos, tablas, migraciones y comentarios).

---

## 5. Protocolo SDD y Persistencia de Memoria
- **Engram:** Registrar de forma proactiva (`mem_save`) cualquier decisión arquitectónica, solución a errores complejos o convenciones nuevas.
- **Spec-Driven Development (SDD):** Para nuevas funcionalidades o refactors, seguir el ciclo SDD (`/sdd-explore` -> `/sdd-propose` -> `/sdd-spec` -> `/sdd-tasks` -> `/sdd-apply` -> `/sdd-verify`).

