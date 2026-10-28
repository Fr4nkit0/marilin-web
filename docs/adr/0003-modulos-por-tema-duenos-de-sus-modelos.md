---
status: accepted
---

# Módulos por tema, dueños de sus modelos

El código del servidor se organiza por tema del dominio y no por tipo de clase: la raíz de `app/` muestra Rutas, Calle, Clientes, Caja, Revisión y Sincronización, no Controllers, Models y Services. Cada módulo es dueño de sus modelos, su HTTP, su lógica, sus enums, sus factories y sus tests, porque así entender o cambiar un tema es abrir una carpeta. Estamos en desarrollo y con un solo desarrollador, así que el costo de mover los modelos (la base se recrea) es bajo.

## Reglas

- **Módulos**: `Accounts`, `Catalog`, `Customers`, `Routes`, `Street`, `CashDesk`, `Review`, `Sync`, `Dashboard` y `Shared` (solo lo que no es de ningún tema).
- **Subtemas**: un módulo se divide en subcarpetas por tema solo si tiene más de un concepto del glosario con modelos propios y cada subtema junta al menos 3 o 4 archivos. Nunca más de un nivel. La interfaz de escritura del módulo queda en su raíz (por ejemplo `Street/StreetOperation` orquesta en una transacción venta, cuenta corriente, inventario de ruta y envases).
- **Entre módulos**: se pueden importar los modelos de otro módulo para leer. Escribir pasa siempre por el módulo dueño.
- **Dentro de un módulo**: `Models/`, `Http/`, `Enums/`, `Console/`, `Database/Factories/` y `routes.php` si tiene rutas; sus tests en `tests/<Módulo>/`. `routes/web.php` y `routes/api.php` solo incluyen los `routes.php` de los módulos.
- **Fuera de los módulos**: las migraciones (un solo esquema, en `database/migrations`, una por tabla) y el seeder.
- **Frontend**: `resources/js/modules/<Módulo>[/<Subtema>]/` espeja el backend: las páginas en su raíz (el nombre de Inertia es su ruta, `Inertia::render('Street/Sales/Index')` → `modules/Street/Sales/Index.tsx`), sus componentes en `components/` y sus tipos en `types.ts`. Lo que no es de ningún tema va a `ui/` (design system) y `lib/` (formato, filtros); los módulos dependen de `ui/` y `lib/`, nunca al revés, y entre módulos solo se importan tipos.
- **Morph types**: se guardan alias (`user`), nunca nombres de clase (`Relation::enforceMorphMap`), para que mover un modelo no deje filas huérfanas.

## Considered Options

- **Por tipo de clase** (lo que había): Laravel por defecto, pero un tema queda repartido en cinco carpetas.
- **Dominio por tema, con modelos y HTTP compartidos en `app/Models` y `app/Http`**: evita configurar factories y morph map, pero los modelos, que son el vocabulario del tema, quedan lejos de su lógica.
- **Monolito modular estricto** (nadie lee tablas ajenas, solo por la interfaz del dueño): más aislado, pero obliga a escribir consultas de exportación que hoy son una línea de Eloquent.
- **Por tema, dueños de sus modelos, lectura libre entre módulos** (elegida).

## Consequences

- Los factories se resuelven por convención: `App\<Módulo>\[<Subtema>\]Models\X` usa `...\Database\Factories\XFactory`.
- Pest y PHPUnit descubren `tests/<Módulo>` además de `tests/Feature` y `tests/Unit`.
- Las páginas se buscan en `resources/js/modules` (resolver de Inertia, `config/inertia.php` y `app.blade.php`), así que el test de Inertia sigue avisando si falta una página.
- Un tema nuevo es una carpeta nueva; un modelo nuevo de un tema existente va a su módulo.
- Como se puede leer entre módulos, la dependencia sigue existiendo: el límite que se respeta es el de escritura.
