<!-- workload_forecast_start -->
## Review Workload Forecast

- Estimated lines of code: < 400 lines (Low risk)
- Chained PRs recommended: No
- Decision needed before apply: No

**Rationale:** The changes are focused on modifying the payload building logic in `SyncService` (Flutter) and adjusting the customer update processing in `SyncController` (Laravel). These are localized adjustments to existing sync logic and are expected to be under 400 lines of code.
<!-- workload_forecast_end -->

## Phase 1: Backend - Update `syncCustomers` logic

- **File:** `app/Http/Controllers/Api/V1/SyncController.php`
- **Tasks:**
  - [x] Update `syncCustomers` method to process `UPDATE` operations within the customer sync payload.
  - [x] Implement lookup logic to match existing customers using `client_uuid`.
  - [x] Fall back to matching by `server_id` if `client_uuid` is missing or not found.
  - [x] Update customer details (e.g., coordinates) upon a successful match.
  - [x] Handle failure gracefully if no existing customer can be matched for an `UPDATE` operation.

## Phase 2: Flutter - Update Sync Payload Generation

- **File:** `lib/features/sync/sync_service.dart` (or equivalent location of `SyncService`)
- **Tasks:**
  - [x] Modify `SyncService` payload generation to group unsynced Sales, Payments, and ContainerMovements by their `visit_id`.
  - [x] Implement logic to generate an ad-hoc/dummy `Visit` for operations (Sales, Payments, ContainerMovements) lacking a `visit_id` (recorded outside standard route stops).
  - [x] Nest the orphaned operations inside the newly generated ad-hoc `Visit`.
  - [x] Verify that the generated payload accurately matches Laravel's expected nested `route-visits` schema.

## Phase 3: Testing

- **Backend Tests:** 
  - [x] Add feature tests for `SyncController` verifying customer updates correctly match by `client_uuid` or `server_id`.
- **Flutter Tests:** 
  - [x] Add unit tests for `SyncService` verifying operations are correctly grouped by `visit_id`.
  - [x] Verify that ad-hoc visits are properly generated and nested for orphaned operations in the payload.
