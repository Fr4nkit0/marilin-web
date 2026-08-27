# Logistics Domain Models Specification

## Purpose

Defines logistics domain entities and operational workflows for delivery routes, ordered route stops, customer visit outcomes, and visit execution status history.

## Requirements

### Requirement: Delivery Route and Stop Sequencing

The system MUST manage daily delivery routes assigned to drivers (repartidores) and maintain an ordered sequence of customer route stops.

#### Scenario: Creating a planned route with sequenced stops

- GIVEN an active Repartidor and a scheduled delivery date
- WHEN a Route is created with multiple associated RouteStop records
- THEN each RouteStop SHALL have an explicit integer `stop_order` defining sequence
- AND the Route status SHALL default to `planned`.

#### Scenario: Reordering stops within an active route

- GIVEN an existing Route with stops ordered 1 through 5
- WHEN a dispatcher or driver changes the sequence order of the stops
- THEN the system MUST update `stop_order` for affected RouteStop records without duplicate sequence numbers
- AND route travel distance calculations SHALL reflect the updated sequence.

### Requirement: Customer Visit Execution and Outcome Tracking

The system MUST record the operational execution of visits at route stops, capturing actual arrival times and standard outcome classifications.

#### Scenario: Successfully completing a customer visit

- GIVEN a RouteStop in `in_progress` status
- WHEN the Repartidor completes the delivery and records a successful VisitOutcome
- THEN a Visit record SHALL be stored with start time, completion time, and outcome reference
- AND the RouteStop status MUST transition to `completed`.

#### Scenario: Recording an unsuccessful visit due to customer absence

- GIVEN a RouteStop in `in_progress` status
- WHEN the Repartidor records a `customer_absent` VisitOutcome with driver notes
- THEN the Visit record SHALL store the absence outcome and note details
- AND the RouteStop status MUST transition to `failed`.

### Requirement: Visit Status Audit Trail

The system MUST record an immutable audit history of all status transitions for every customer visit.

#### Scenario: Logging status history on visit progression

- GIVEN a Visit undergoing transitions from `pending` to `in_progress` to `completed`
- WHEN each status update occurs
- THEN a corresponding VisitStatusHistory entry MUST be inserted with timestamp, previous status, new status, and the acting User ID.
