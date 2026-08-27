# Security & Authorization Rules

## 1. Policies & Gates
- All resource modifications (Update, Delete, View sensitive data) MUST be protected by Laravel **Policies** (`app/Policies/`).
- Repartidores can ONLY view and update routes, visits, inventory, and clients assigned to them.
- Admins / Super Users have global management authority.

## 2. Input Validation
- NEVER trust client inputs.
- All requests modifying state MUST have a dedicated `FormRequest` class with strict validation rules, explicit data types, and `authorize()` checks.

## 3. Mass Assignment & Data Integrity
- All Eloquent models must explicitly define `$fillable` or protected attributes.
- Use explicit database foreign key constraints and cascade rules where appropriate.
