# Specification: backend-sync-api

## 1. Description
This capability defines the backend API for synchronizing data from the mobile clients. It focuses on updating the `SyncController@syncCustomers` endpoint in Laravel to properly handle `UPDATE` operations for customers, matching them via `client_uuid` or `server_id` to allow drivers to edit customer details (such as coordinates) on the go.

## 2. Requirements

- **REQ-BSA-1:** The `SyncController@syncCustomers` endpoint MUST process `UPDATE` operations within the customer sync payload.
- **REQ-BSA-2:** The system MUST attempt to match an existing customer using the provided `client_uuid`.
- **REQ-BSA-3:** If `client_uuid` is not provided or not found, the system MUST attempt to match the existing customer using the `server_id`.
- **REQ-BSA-4:** Upon a successful match, the system MUST update the customer's details and coordinates according to the payload data.
- **REQ-BSA-5:** If an `UPDATE` operation cannot be matched to any existing customer, the system SHALL return an appropriate error response or handle the failure gracefully.

## 3. Scenarios

### Scenario 1: Handling Customer UPDATE when matched via `client_uuid`
**GIVEN** the mobile client sends a customer `UPDATE` operation payload containing a `client_uuid`
**AND** a customer with that `client_uuid` already exists in the database
**WHEN** the `syncCustomers` endpoint processes the payload
**THEN** it SHALL match the existing customer using the `client_uuid`
**AND** it SHALL update the customer's details and coordinates based on the provided payload.

### Scenario 2: Handling Customer UPDATE when matched via `server_id`
**GIVEN** the mobile client sends a customer `UPDATE` operation payload containing a `server_id` (and either no `client_uuid` or a non-matching `client_uuid`)
**AND** a customer with that `server_id` already exists in the database
**WHEN** the `syncCustomers` endpoint processes the payload
**THEN** it SHALL match the existing customer using the `server_id`
**AND** it SHALL update the customer's details and coordinates based on the provided payload.
