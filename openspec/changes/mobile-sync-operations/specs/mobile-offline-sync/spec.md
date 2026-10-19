# Specification: mobile-offline-sync

## 1. Description
This capability enables the mobile application (Flutter) to synchronize offline operations with the backend, specifically grouping operations like Sales, Payments, and ContainerMovements by their corresponding `visit_id` to match the backend's expected nested `route-visits` schema. It also covers the generation of ad-hoc visits for operations that occur outside of a scheduled route stop.

## 2. Requirements

- **REQ-MOS-1:** The `SyncService` MUST group all unsynced Sales, Payments, and ContainerMovements by their `visit_id` when preparing the sync payload.
- **REQ-MOS-2:** The `SyncService` MUST generate a dummy/ad-hoc `Visit` for any Payment, Sale, or ContainerMovement that does not have an associated `visit_id` (e.g., operations collected outside a standard route stop).
- **REQ-MOS-3:** The generated ad-hoc `Visit` MUST be included in the sync payload alongside its nested operations.
- **REQ-MOS-4:** The grouped payload MUST be structured according to the backend's nested `route-visits` schema requirements.

## 3. Scenarios

### Scenario 1: Grouping unsynced operations by `visit_id`
**GIVEN** there are unsynced Sales, Payments, and ContainerMovements in the local database that are associated with a specific `visit_id`
**WHEN** the `SyncService` prepares the sync payload for the backend
**THEN** it SHALL group these operations under their corresponding `visit_id`
**AND** it SHALL nest the operations correctly within the `route-visits` schema.

### Scenario 2: Generating ad-hoc visits for operations without a `visit_id`
**GIVEN** an unsynced Payment (or Sale/ContainerMovement) is recorded outside of a scheduled route stop (has no `visit_id`)
**WHEN** the `SyncService` prepares the sync payload
**THEN** it SHALL generate a new ad-hoc `Visit` record
**AND** it SHALL associate the unsynced Payment with this ad-hoc `Visit`
**AND** the payload SHALL include the ad-hoc `Visit` and the nested Payment operation.
