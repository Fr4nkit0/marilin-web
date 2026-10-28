# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 12, PHP 8.5, Inertia.js v2 (React 18 + TypeScript), Tailwind CSS v3, Vite, PostgreSQL 16, Redis.

## Users

1. **Administrators / Dispatchers:** Office staff managing customer databases, delivery routes, truck inventories, and accounts receivable (invoicing, current accounts). They primarily use desktop devices.
2. **Delivery Drivers (Repartidores):** Mobile users who operate from truck cabs under direct sunlight with wet or gloved hands. They confirm deliveries, record bottle exchanges (full vs. empty returns), and collect payments.

## Product Purpose

A comprehensive logistics, delivery route management, and distribution system specifically built for water and soda businesses. Success means efficient truck loading, error-free route execution by drivers, and accurate invoicing and inventory tracking.

## Positioning

A specialized, high-density operational tool tailored specifically for the physical constraints of water/soda distribution (handling returnable bottles and dispensers) rather than a generic delivery or CRM app.

## Operating Context

- **Office:** High-density data entry, route planning, bulk invoicing, and customer support.
- **Truck Cab:** High-glare, vibrating environment where speed and clear tap targets are critical to confirm stops quickly without disrupting the route.

## Capabilities and Constraints

- **Capabilities:** Customer management, route assignment, truck inventory tracking (fulls, empties, siphons), invoicing, current accounts.
- **Constraints:** Must provide an extremely robust, touch-friendly mobile interface for drivers that prevents data entry errors under sub-optimal physical conditions. Must adhere to strict typing (TypeScript/PHP) and Fat Models/Skinny Controllers architecture.

## Brand Commitments

- Spanish (Rioplatense) for the UI copy and user interactions.
- English for the source code (classes, variables, tables).
- Professional, clear, and direct tone.

## Evidence on Hand

- Backend scaffolded with Laravel Sail (PostgreSQL, Redis, Mailpit).
- Existing automated tests (Pest PHP).
- Tailwind design system with slate-900 primary actions, an indigo accent and Emerald/Rose for status.

## Product Principles

1. **Clarity over Decoration:** The UI must prioritize speed of data entry and readability over aesthetic flourishes.
2. **Driver-First Mobile Ergonomics:** The mobile interface must be foolproof, with large touch targets and high contrast.
3. **Strict Structural Integrity:** Backend and frontend must follow strict typing and solid architectural principles to ensure zero data loss.
4. **Monochrome Urgency:** Use neutral colors for normal operations; reserve saturated colors exclusively for critical alerts or exceptions.
