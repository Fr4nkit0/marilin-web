# Specification: Backend Customer Model (Delta)

## Context
This is a delta specification modifying the Core Domain Models Specification (`openspec/specs/core-domain-models`).

## Purpose
To enhance the customer address schema by adding neighborhood information, better supporting logistics and offline mapping workflows.

## Modifications to Core Domain Models

### Amended Requirement: Customer Profile and Address Management
The system MUST maintain customer profiles and support multiple physical addresses per customer, designating exactly one primary address as default. Each address MUST now support an optional or required `neighborhood` string designation.

#### Added Scenario: Registering customer address with neighborhood
- GIVEN valid customer address data that includes a `neighborhood` value
- WHEN the CustomerAddress is created or updated
- THEN the system SHALL persist the `neighborhood` field in the database
- AND the field SHALL be exposed through the backend API endpoints (including sync and bootstrap endpoints).
