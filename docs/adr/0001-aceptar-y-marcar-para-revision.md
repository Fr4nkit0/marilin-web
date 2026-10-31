---
status: accepted
---

# Aceptar y marcar para revisión en vez de rechazar operaciones de la calle

La app del repartidor trabaja offline y sube visitas, ventas y pagos horas después de que ocurrieron. En ese lapso el admin puede dar de baja un cliente o reasignarlo a otro repartidor. Decidimos que el servidor acepte igual esas operaciones y las marque "para revisión", porque el hecho ya ocurrió en la calle (se entregó producto y se cobró) y rechazarlo perdería datos reales.

## Considered Options

- **Rechazar**: coherente con las reglas de pertenencia, pero pierde ventas y pagos reales y obliga al repartidor a resolver conflictos que no causó.
- **Nunca eliminar ni reasignar**: evita el conflicto, pero quita al admin una operación legítima.
- **Aceptar y marcar** (elegida).

## Consequences

- Toda regla de pertenencia en el push es una marca, nunca un rechazo: el rechazo se reserva para datos inválidos.
- La web necesita una bandeja de revisión para que lo marcado no quede invisible.

## Excepción: datos de un cliente ajeno (2026-10-31)

Una venta, un cobro o una visita sobre un cliente de otro repartidor siguen aceptándose y marcándose. Pero un cambio a los *datos* de ese cliente (nombre, teléfono, baja, direcciones, preferencias) no es un hecho de la calle: si se aplica al instante, un repartidor puede pisar el cliente de otro (auditoría: un repartidor renombró, cambió la dirección y dio de baja un cliente ajeno). Esos cambios se aceptan pero **no se aplican**: quedan pendientes en Revisión con los valores propuestos junto a los actuales, y el admin los aprueba (se aplican fechados en el momento de la aprobación, como una edición del admin) o los rechaza (se descartan). La app recibe `accepted` con el motivo "Customer is assigned to another driver." y muestra el cambio como pendiente. Crear un cliente nuevo nunca es ajeno.
