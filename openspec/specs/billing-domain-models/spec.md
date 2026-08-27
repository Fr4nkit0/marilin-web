# Billing Domain Models Specification

## Purpose

Defines billing, customer price agreements, sales orders, line item details, payment transactions, and customer current account ledger balances.

## Requirements

### Requirement: Customer Price Agreements

The system MUST allow negotiating customized pricing agreements per customer and product, overriding default catalog prices during sale generation.

#### Scenario: Applying agreed custom price to a customer order

- GIVEN a CustomerAgreement establishing a unit price of $800.00 for 20L bottles (catalog price $1,000.00)
- WHEN a Sale is created for that Customer for 2 units
- THEN the SaleDetail unit price MUST resolve to $800.00
- AND the calculated subtotal SHALL be $1,600.00.

### Requirement: Sales and Detailed Line Items

The system MUST record sales orders with line items, quantity, unit pricing, discounts, taxes, and total amounts.

#### Scenario: Creating a sale with multiple product line items

- GIVEN an active customer visit
- WHEN a Sale is recorded with multiple SaleDetail items
- THEN each SaleDetail SHALL store the product ID, quantity, unit price, and subtotal
- AND the parent Sale record MUST compute total amount as the sum of all item subtotals.

### Requirement: Payment Processing and Current Account Ledger

The system MUST support multi-method payments and maintain an accurate double-entry account ledger reflecting running monetary balances for each customer.

#### Scenario: Processing immediate cash payment for a sale

- GIVEN a Sale totaling $3,000.00
- WHEN a Payment is recorded with PaymentMethod `cash` and Transaction reference for $3,000.00
- THEN the Sale payment status MUST transition to `paid`
- AND a CustomerAccountEntry of type `payment` SHALL be appended.

#### Scenario: Registering credit sale to customer current account

- GIVEN a Sale totaling $2,500.00 with payment method `current_account`
- WHEN the sale is finalized without immediate cash payment
- THEN a CustomerAccountEntry of type `charge` for $2,500.00 MUST be posted
- AND the CustomerBalance debt amount SHALL increment by $2,500.00.
