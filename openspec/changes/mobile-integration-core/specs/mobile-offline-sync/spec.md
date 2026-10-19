# Specification: Mobile Offline Sync

## Purpose
To enable offline-first capabilities in the Flutter mobile app by establishing local database schemas (Drift) that align with backend models, and implementing hydration logic to bootstrap local data from the backend.

## Requirements

### Requirement: Local Database ID Mapping
The mobile application's local database (Drift) MUST support dual identifiers for entities: a local primary key for offline creation, and a `server_id` integer mapping to link records with backend canonical IDs.

#### Scenario: Storing synced records with server IDs
- GIVEN a record is received from the backend
- WHEN the record is saved to the local Drift database
- THEN the local database SHALL persist the backend ID into the `server_id` field
- AND the record SHALL remain queryable via the `server_id` for future synchronization updates.

### Requirement: Database Hydration (Bootstrap)
The mobile application MUST perform an initial sync or "bootstrap" by requesting foundational data from the backend (`/api/v1/sync/bootstrap`) and hydrating the local Drift database.

#### Scenario: App bootstrapping on first launch
- GIVEN the app is freshly installed and the user has logged in
- WHEN the app invokes the `/api/v1/sync/bootstrap` endpoint
- THEN the app SHALL parse the incoming data payloads
- AND hydrate the local Drift database with the necessary baseline records (e.g., customers, routes).
