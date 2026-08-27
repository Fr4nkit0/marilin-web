# Implementation Tasks: Database Models and Migrations

## Phase 1: Core Domain Models & Migrations
- [x] 1.1 Create core domain migrations for users, repartidores, customers, addresses, and preferences
  - Files: `database/migrations/*_create_repartidors_table.php`, `database/migrations/*_create_customers_table.php`, `database/migrations/*_create_customer_addresses_table.php`, `database/migrations/*_create_customer_preferences_table.php`
- [x] 1.2 Implement Core Eloquent models, casts, scopes, and relationships
  - Files: `app/Models/User.php`, `app/Models/Repartidor.php`, `app/Models/Customer.php`, `app/Models/CustomerAddress.php`, `app/Models/CustomerPreference.php`

## Phase 2: Inventory & Product Models & Migrations
- [x] 2.1 Create inventory migrations for container types, balances, movements, products, and truck stock
  - Files: `database/migrations/*_create_container_types_table.php`, `database/migrations/*_create_products_table.php`, `database/migrations/*_create_container_movements_table.php`, `database/migrations/*_create_customer_container_balances_table.php`, `database/migrations/*_create_route_inventories_table.php`, `database/migrations/*_create_route_inventory_loads_table.php`
- [x] 2.2 Implement Inventory Eloquent models, casts, scopes, and relationships
  - Files: `app/Models/ContainerType.php`, `app/Models/Product.php`, `app/Models/ContainerMovement.php`, `app/Models/CustomerContainerBalance.php`, `app/Models/RouteInventory.php`, `app/Models/RouteInventoryLoad.php`

## Phase 3: Logistics & Billing Models & Migrations
- [x] 3.1 Create logistics migrations for routes, stops, outcomes, visits, and history audit
  - Files: `database/migrations/*_create_routes_table.php`, `database/migrations/*_create_route_stops_table.php`, `database/migrations/*_create_visit_outcomes_table.php`, `database/migrations/*_create_visits_table.php`, `database/migrations/*_create_visit_status_histories_table.php`
- [x] 3.2 Create billing migrations for agreements, sales, details, payment methods, transactions, payments, and account ledger
  - Files: `database/migrations/*_create_customer_agreements_table.php`, `database/migrations/*_create_payment_methods_table.php`, `database/migrations/*_create_transactions_table.php`, `database/migrations/*_create_sales_table.php`, `database/migrations/*_create_sale_details_table.php`, `database/migrations/*_create_payments_table.php`, `database/migrations/*_create_customer_account_entries_table.php`, `database/migrations/*_create_customer_balances_table.php`
- [x] 3.3 Implement Logistics and Billing Eloquent models, casts, scopes, and relationships
  - Files: `app/Models/Route.php`, `app/Models/RouteStop.php`, `app/Models/VisitOutcome.php`, `app/Models/Visit.php`, `app/Models/VisitStatusHistory.php`, `app/Models/CustomerAgreement.php`, `app/Models/PaymentMethod.php`, `app/Models/Transaction.php`, `app/Models/Sale.php`, `app/Models/SaleDetail.php`, `app/Models/Payment.php`, `app/Models/CustomerAccountEntry.php`, `app/Models/CustomerBalance.php`

## Phase 4: Factories, Seeders & Pest Test Verification
- [x] 4.1 Create model factories for all Core, Inventory, Logistics, and Billing domain models
  - Files: `database/factories/*Factory.php`
- [x] 4.2 Build comprehensive DatabaseSeeder with realistic demo dataset
  - Files: `database/seeders/DatabaseSeeder.php`, `database/seeders/*Seeder.php`
- [x] 4.3 Implement Pest unit and feature tests for schema integrity, model relationships, scopes, and casts
  - Files: `tests/Unit/Models/CoreModelsTest.php`, `tests/Unit/Models/InventoryModelsTest.php`, `tests/Unit/Models/LogisticsModelsTest.php`, `tests/Unit/Models/BillingModelsTest.php`
- [x] 4.4 Run `php artisan migrate:fresh --seed` and verify all Pest tests pass
