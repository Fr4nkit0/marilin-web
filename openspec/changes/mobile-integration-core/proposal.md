# Proposal: Mobile Integration Core

## 1. Intent
Connect the Flutter driver app to the Laravel backend for real-world usage, discarding the mock architecture. This implements Phase 1 and 2 of the mobile integration.

## 2. Capabilities

### New Capabilities
- **mobile-api-core**: Add `flutter_secure_storage`, Dio network client, and Sanctum Login screen in Flutter.
- **mobile-offline-sync**: Implement Drift hydration from `/api/v1/sync/bootstrap` in Flutter app.
- **mobile-map-proxy**: Repoint Flutter map/geocoding services to backend proxy (`/api/v1/map/*`).

### Modified Capabilities
- **backend-sync-api**: Expand backend `SyncController` to accept offline-created customers.
- **backend-map-api**: Proxies maps and geocoding.
- **backend-customer-model**: Add `neighborhood` to `customer_addresses`.

## 3. Scope

### In Scope
1. Add `flutter_secure_storage`, network client (Dio/http), and Sanctum Login screen in Flutter.
2. Implement backend tweaks (add `neighborhood` to `customer_addresses`).
3. Add `server_id` integer mappings to Drift in Flutter.
4. Implement Drift hydration from `/api/v1/sync/bootstrap` in the Flutter app.
5. Repoint Flutter map/geocoding services to backend proxy (`/api/v1/map/*`).
6. Expand backend `SyncController` to accept offline-created customers.

### Out of Scope
1. Background GPS Tracking, push notifications, printing, AFIP integration, Sentry (deferred).
2. Migrating Laravel IDs to UUIDs (we are using `server_id` mapping instead).

## 4. Affected Modules
- **Flutter App**: `lib/features/auth`, `lib/core/network`, `lib/core/database`, `lib/features/sync`, `lib/features/map`
- **Laravel Backend**: `app/Http/Controllers/Api/V1/SyncController.php`, `database/migrations` (customer_addresses)

## 5. Rollback Plan
- **Backend**: Revert the migration adding `neighborhood` to `customer_addresses`. Roll back `SyncController` changes.
- **Flutter**: Revert `pubspec.yaml` to remove new dependencies if necessary, and use git revert to restore previous mock-based architecture components (reverting auth and drift mapping commits).
