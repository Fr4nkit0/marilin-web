---
name: eloquent-patterns
description: >
  Best practices for Eloquent models, relationships, query scopes, and eager loading.
---

# Eloquent Models & Query Patterns

## 1. Local Scopes
Usar scopes para consultas repetitivas:
```php
// En el modelo Customer
public function scopeActive(Builder $query): void
{
    $query->where('enabled', true);
}

public function scopeForRepartidor(Builder $query, int $repartidorId): void
{
    $query->where('repartidor_id', $repartidorId);
}
```

## 2. Relaciones Tipadas y Eager Loading
- Siempre tipar el retorno de las relaciones (`BelongsTo`, `HasMany`, `HasOne`).
- Usar `with()` en controladores para evitar problemas de N+1 queries.

## 3. Casts Modernos
- Definir tipos nativos en `casts(): array`:
```php
protected function casts(): array
{
    return [
        'enabled' => 'boolean',
        'unit_price' => 'decimal:2',
        'valid_from' => 'date',
    ];
}
```
