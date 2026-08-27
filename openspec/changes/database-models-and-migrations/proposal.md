# Proposal: Database Models and Migrations

## Intent

Migrate the legacy Java database schema (`V1__database_schema.sql`) to Laravel 12 Eloquent models, migrations, and factories using PostgreSQL 16 standard idiomatic Laravel conventions (auto-incrementing integer IDs `$table->id()` and `$table->foreignId()`).

## Scope

### In Scope
- Database migrations with foreign key constraints, indexes, and soft deletes where applicable.
- Eloquent models with typed properties, relationships, query scopes, and casts across four domain modules:
  - **Core**: `User`, `Repartidor`, `Customer`, `Address`, `CustomerPreference`.
  - **Logistics**: `Route`, `RouteStop`, `VisitOutcome`, `CustomerVisit`, `VisitHistory`.
  - **Inventory**: `ContainerType`, `ContainerMovement`, `ContainerBalance`, `Product`, `RouteInventory`, `RouteInventoryLoad`.
  - **Billing**: `PriceAgreement`, `Sale`, `SaleDetail`, `PaymentMethod`, `Transaction`, `Payment`, `AccountEntry`, `AccountBalance`.
- Model factories and database seeders for all domain entities.
- Pest unit tests verifying model relationships, scopes, and casts.

### Out of Scope
- HTTP controllers, Inertia pages, or API endpoints.
- Authentication/Authorization business logic (beyond base User/Repartidor models).
- Legacy data ETL / live database migration scripts.

## Capabilities

### New Capabilities
- `core-domain-models`: Core customer, user, driver, address, and preference entities and relationships.
- `logistics-domain-models`: Delivery routes, stops, visits, outcomes, and execution tracking.
- `inventory-domain-models`: Container tracking, balances, movements, catalog products, and truck stock loads.
- `billing-domain-models`: Sales, pricing agreements, transactions, payments, account entries, and financial balances.

### Modified Capabilities
None

## Approach

1. Create migration files in chronological/dependency order matching PostgreSQL foreign key constraints.
2. Build Eloquent models organized within `App\Models` (or module sub-namespaces `Core`, `Logistics`, `Inventory`, `Billing`).
3. Define explicit `$fillable`, `$casts`, and relationship methods (`hasMany`, `belongsTo`, `belongsToMany`).
4. Generate corresponding Model Factories in `database/factories` supporting test states.
5. Validate full schema creation via `./vendor/bin/sail artisan migrate:fresh` and comprehensive Pest model relationship tests.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/` | New | Schema migration files for all 4 domains |
| `app/Models/` | New | Eloquent models, relationships, and casts |
| `database/factories/` | New | Model factories for test fixtures |
| `database/seeders/` | Modified | Database seeders for local dev data |
| `tests/Unit/Models/` | New | Pest tests validating schema and relationships |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Circular foreign key dependencies in migrations | Med | Order migrations by dependency tree; use deferred foreign keys if needed |
| Subtle type mismatches from legacy Java schema | Low | Map SQL types directly to Laravel migration primitives (`decimal(12,2)`, `timestamp`, etc.) |

## Rollback Plan

Roll back migrations via `./vendor/bin/sail artisan migrate:rollback` or reset database via `./vendor/bin/sail artisan migrate:fresh`.

## Dependencies

- PostgreSQL 16 database running via Laravel Sail.

## Success Criteria

- [ ] All migrations run cleanly with `artisan migrate:fresh`.
- [ ] Eloquent models and relationships pass 100% of Pest Unit tests.
- [ ] Database factories generate valid data with no integrity violations.
