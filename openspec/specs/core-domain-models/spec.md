# Core Domain Models Specification

## Purpose

Defines core business entities and relationships for user authentication, delivery drivers (repartidores), customer profiles, addresses, and delivery preferences.

## Requirements

### Requirement: User and Repartidor Management

The system MUST manage user accounts and delivery driver (repartidor) profiles, maintaining referential integrity between authenticated users and operational drivers.

#### Scenario: Linking Repartidor profile to User

- GIVEN an active User account with driver role credentials
- WHEN a Repartidor profile is created referencing the User ID
- THEN the Repartidor record SHALL be associated with the User
- AND the Repartidor status SHALL default to active.

#### Scenario: Deactivating a Repartidor

- GIVEN an existing active Repartidor
- WHEN the Repartidor is marked as inactive
- THEN the system MUST prevent assigning new delivery routes to this driver
- AND historical route associations SHALL remain preserved.

### Requirement: Customer Profile and Address Management

The system MUST maintain customer profiles and support multiple physical addresses per customer, designating exactly one primary address as default.

#### Scenario: Registering a customer with primary address

- GIVEN valid customer details and address data including latitude and longitude
- WHEN a new Customer and associated CustomerAddress are created
- THEN the Customer record SHALL be persisted with active status
- AND the CustomerAddress SHALL be marked with `is_default = true`.

#### Scenario: Changing default customer address

- GIVEN a Customer with an existing default CustomerAddress
- WHEN a second CustomerAddress is created or updated with `is_default = true`
- THEN the previous default address MUST have `is_default` set to `false`
- AND the newly designated address SHALL become the active default.

### Requirement: Customer Delivery Preferences

The system MUST store customer delivery preferences including preferred delivery time windows, schedule frequencies, and custom drop-off notes.

#### Scenario: Setting custom delivery preferences

- GIVEN an existing Customer record
- WHEN a CustomerPreference is created specifying morning window and weekly frequency
- THEN the preferences SHALL be associated with the Customer
- AND logistics scheduling MUST resolve these preferences during route generation.
