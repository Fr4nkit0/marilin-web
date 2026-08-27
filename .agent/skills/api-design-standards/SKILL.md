---
name: api-design-standards
description: >
  REST API conventions for mobile app synchronization (Flutter) and web JSON responses.
---

# REST API & Sync Standards (for Flutter App & Web)

## 1. Formato de Respuestas JSON
- Usar siempre **API Resources** (`JsonResource`) de Laravel (`app/Http/Resources/`).
- Estructura uniforme de respuesta:
```json
{
  "data": { ... },
  "message": "Operación exitosa"
}
```

## 2. Endpoints de Sincronización Móvil (Offline-First)
- Endpoints idempotentes (`idempotency_records` o UUID en transacciones).
- Soporte para filtros por fecha (`updated_since=2026-08-26T00:00:00Z`).
- Carga masiva de visitas y movimientos de envases en una sola transacción (`POST /api/v1/sync/route-visits`).
