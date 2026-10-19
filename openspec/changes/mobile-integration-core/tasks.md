# Tasks: Mobile Integration Core

## Review Workload Forecast
Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

## Work Units
- **PR 1**: Laravel Backend Tweaks (Phase 1)
- **PR 2**: Flutter Auth & Network (Phase 2)
- **PR 3**: Flutter Drift Hydration & Sync (Phase 3)
- **PR 4**: Flutter Map Proxy (Phase 4)

## Phase 1: Foundation / Backend (PR 1)
- [x] **Task 1.1: Database Migration**
  - Create a migration to add an optional `neighborhood` string column to the `customer_addresses` table (`database/migrations/*_add_neighborhood_to_customer_addresses_table.php`).
  - Update the `CustomerAddress` model (`app/Models/CustomerAddress.php`) to make `neighborhood` fillable.
- [x] **Task 1.2: SyncController Updates**
  - Modify `SyncController` (`app/Http/Controllers/Api/V1/SyncController.php`) to accept customer records created offline by the mobile app.
  - Ensure the `/api/v1/sync/bootstrap` endpoint generates the necessary baseline payload (customers, routes, operational data) for Drift hydration.

## Phase 2: Flutter Network & Auth (PR 2)
- [x] **Task 2.1: Dependencies**
  - Add `dio` and `flutter_secure_storage` to `pubspec.yaml` in the Flutter app.
- [x] **Task 2.2: Core Network Client**
  - Create `lib/core/network/dio_client.dart` configured with a base URL, Sanctum CSRF handling, and interceptors for the Bearer token.
- [x] **Task 2.3: Authentication UI and Logic**
  - Create `lib/features/auth/auth_controller.dart` for managing login state and secure token storage.
  - Implement the login screen in `lib/features/auth/login_screen.dart` using the network client to authenticate against the backend `/api/login` endpoint.

## Phase 3: Drift & Synchronization (PR 3)
- [x] **Task 3.1: Drift Models Mapping**
  - Update Drift table definitions in `lib/core/database/database.dart` (or related table files) to include `server_id` integer columns for mapping canonical backend IDs.
- [x] **Task 3.2: Bootstrap Sync**
  - Implement `lib/features/sync/sync_service.dart` to fetch foundational data from `/api/v1/sync/bootstrap`.
  - Parse the JSON payload and hydrate the local Drift database with the canonical backend records.
- [x] **Task 3.3: Offline Creation Sync**
  - Implement push logic to send locally created entities (without a `server_id`) to the backend and update them locally with the returned canonical `server_id`.


## Phase 4: Flutter Map Proxy (PR 4)
- [x] **Task 4.1: Repoint Map Services**
  - Update map configuration to use backend proxy endpoints (`/api/v1/map/*`) in `lib/features/map/map_service.dart`.
  - Ensure map tile URLs, routing, and geocoding requests route through the backend, avoiding exposure of third-party API keys in the Flutter app.

## Phase 5: Testing (Across PRs)
- [x] **Task 5.1: Backend Testing**
  - Add or update tests for `SyncController` and the `CustomerAddress` neighborhood modifications in `tests/Feature/SyncControllerTest.php`.
- [x] **Task 5.2: Flutter Testing**
  - Update network, authentication, and synchronization tests to cover the new integration logic.
