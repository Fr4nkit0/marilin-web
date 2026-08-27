---
name: laravel-conventions
description: >
  Coding standards, Eloquent patterns, FormRequest validation, and architecture conventions for Laravel 12.
---

# Laravel 12 Conventions & Patterns

## 1. Controller & Route Conventions
- Usar Resource Controllers o Single-Action Controllers (`__invoke`).
- Validación SIEMPRE mediante FormRequest dedicados (`app/Http/Requests/`).
- Respuestas de vistas mediante Inertia: `return Inertia::render('Customers/Index', [...]);`.

## 2. Eloquent Models & Migrations
- Claves primarias auto-incrementales: `$table->id()`.
- Relaciones siempre con tipos de retorno explícitos: `public function items(): HasMany`.
- Eager Loading obligatorio en endpoints con múltiples registros para evitar N+1: `Customer::with(['addresses', 'containerBalances'])->paginate()`.
- Usar `casts()` method para casting moderno en Laravel 11/12.

## 3. Testing with Pest
- Usar `RefreshDatabase` trait en tests de Feature.
- Estructura AAA: Arrange, Act, Assert.
- Tests descriptivos con `it('should...')`.
