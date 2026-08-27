# Inventory Domain Models Specification

## Purpose

Defines inventory tracking entities for returnable container types, customer container balances, movements, catalog products, and route truck stock loading.

## Requirements

### Requirement: Returnable Container Types and Product Catalog

The system MUST manage catalog products and define specifications for returnable containers, linking packaged products to their container types.

#### Scenario: Registering a returnable water product

- GIVEN a ContainerType defining 20L returnable bottles with deposit price
- WHEN a Product is created referencing the ContainerType
- THEN the Product SHALL be flagged as requiring returnable containers
- AND the deposit and volume attributes SHALL be accessible via the ContainerType relation.

### Requirement: Customer Container Balance and Movement Ledger

The system MUST track every dispatch and return of containers through an immutable movement ledger and maintain a running container balance per customer and container type.

#### Scenario: Delivering filled containers to customer

- GIVEN a Customer with an initial balance of 2 units of 20L bottles
- WHEN a delivery visit creates a ContainerMovement of 3 delivered 20L bottles
- THEN the ContainerMovement record SHALL be persisted with movement type `delivered`
- AND the CustomerContainerBalance MUST update to reflect 5 units held.

#### Scenario: Collecting empty containers from customer

- GIVEN a Customer holding 5 units of 20L bottles
- WHEN a driver logs a ContainerMovement of 4 collected empty bottles
- THEN a ContainerMovement record SHALL be inserted with movement type `returned`
- AND the CustomerContainerBalance MUST decrement to 1 unit held.

### Requirement: Route Truck Inventory and Loading Management

The system MUST track truck stock loads prior to route departure and monitor available inventory levels during route execution.

#### Scenario: Loading truck stock for a route

- GIVEN a planned Route and warehouse stock
- WHEN a RouteInventoryLoad is registered with loaded product and empty container quantities
- THEN the RouteInventory records for that route MUST reflect the initial loaded quantities
- AND the RouteInventoryLoad status SHALL become `confirmed`.

#### Scenario: Decrementing truck stock upon customer delivery

- GIVEN a Route with 20 loaded units in RouteInventory
- WHEN a Visit completes a sale/delivery of 5 units to a customer
- THEN the RouteInventory quantity on hand MUST decrement to 15 units.
