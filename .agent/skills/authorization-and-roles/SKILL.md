---
name: authorization-and-roles
description: >
  Role management and policy-based authorization for Admin and Repartidores.
---

# Authorization & Policies

## 1. Roles
- **Admin (`is_super_user = true`):** Acceso total al sistema, reportes, configuración global de precios y gestión de usuarios.
- **Repartidor:** Acceso acotado a sus rutas asignadas, clientes de su zona, registro de visitas y carga de envases.

## 2. Policies
Todas las entidades clave (`Customer`, `Route`, `Visit`, `Sale`) deben tener su Policy correspondiente en `app/Policies/`.
