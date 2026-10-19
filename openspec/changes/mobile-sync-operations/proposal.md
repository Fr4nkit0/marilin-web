# Proposal: mobile-sync-operations

## 1. Intent
Ensure transactional integrity by adapting Flutter's sync payloads to match Laravel's nested `route-visits` schema, and allow drivers to edit customer details on the go.

## 2. Scope
- **In Scope:**
  1. Update Flutter's `SyncService` to group unsynced Sales, Payments, and ContainerMovements by `visit_id`.
  2. Implement logic in Flutter to create a dummy/ad-hoc `Visit` for payments collected outside a route stop.
  3. Update Laravel's `SyncController@syncCustomers` to process `UPDATE` operations (using `client_uuid` or `server_id` to match existing records) and update coordinates/details.
- **Out of Scope:**
  1. Flattening the Laravel API (we are keeping it nested for transactional safety).

## 3. Capabilities
- **New Capabilities:** (none)
- **Modified Capabilities:**
  - `mobile-offline-sync`: Modify `SyncService` to group Sales, Payments, and ContainerMovements by `visit_id` before pushing to backend. Handle ad-hoc visits.
  - `backend-sync-api`: Update `syncCustomers` to handle updates (edit customer details/coordinates).

## 4. Rollback Plan
- **Flutter:** Revert the `SyncService` changes to the previous payload generation logic.
- **Laravel:** Revert `SyncController@syncCustomers` changes to reject update payloads. No database structure changes are planned, making a codebase revert sufficient.
