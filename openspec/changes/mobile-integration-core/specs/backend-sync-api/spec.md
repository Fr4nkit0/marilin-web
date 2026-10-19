# Specification: Backend Sync API

## Purpose
To expand the backend synchronization controllers to properly handle and integrate records created offline on the mobile client.

## Requirements

### Requirement: Accept Offline-Created Customers
The backend `SyncController` MUST accept customer records created offline by the mobile application and persist them into the canonical database.

#### Scenario: Syncing a new offline customer
- GIVEN the mobile app pushes a synchronization payload containing a new customer created offline (with a local identifier)
- WHEN the `SyncController` processes the payload
- THEN the backend SHALL create a canonical record for the customer
- AND the backend SHALL return the new canonical `server_id` to the mobile client so it can update its local mapping.

### Requirement: Bootstrap Payload Generation
The backend MUST provide a `/api/v1/sync/bootstrap` endpoint that outputs the necessary foundational data state for a fresh mobile client database.

#### Scenario: Generating bootstrap data
- GIVEN an authenticated mobile client requests the bootstrap endpoint
- WHEN the backend processes the request
- THEN the backend SHALL aggregate relevant active customers, routes, and operational data
- AND return them in a structured JSON payload for Drift hydration.
