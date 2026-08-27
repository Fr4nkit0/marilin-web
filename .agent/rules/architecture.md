# Architecture & Core Rules

## 1. Language & Comments Policy
- **Conversations with User:** Español (Rioplatense).
- **Git Commit Messages:** Strictly in **Spanish** (e.g. `feat: agregar modelo y migración de clientes`).
- **Codebase Identifiers:** Strictly in **English** (class names, methods, functions, variables, database tables, migrations, routes, file names, API payloads).
- **Code Comments & Docstrings:** **Spanish is allowed** (and encouraged if it helps team understanding), English is also acceptable.

## 2. Request Flow & Skinny Controllers
- **Strict Flow:** `Route` -> `FormRequest` (Validation) -> `Controller` -> `Action / Service` (Business Logic & DB Transactions) -> `Inertia Response / Resource`.
- Controllers MUST be skinny. They only handle input binding, call an Action or Service, and return an Inertia response or Redirect.
- Heavy business logic, financial calculations, and stock deductions MUST NEVER live directly in controllers.

## 3. Database Transactions
- Any business operation that modifies multiple database tables (e.g. creating a Sale + decrementing Route Inventory + creating Container Movement + updating Customer Account Balance) MUST be wrapped in a `DB::transaction(...)`.

## 4. Strict Type-Safety
- PHP 8.5: Explicit return types on all methods, typed properties, and modern `casts(): array` in Eloquent models.
- TypeScript: Explicit interfaces for all Inertia page props, domain entities, and form states in `resources/js/types/`.

## 5. Testing Integrity
- Every new feature, Action, and critical endpoint must be backed by automated **Pest tests** (`tests/Feature` or `tests/Unit`).
