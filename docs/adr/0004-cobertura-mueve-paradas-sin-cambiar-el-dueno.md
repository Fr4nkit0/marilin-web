---
status: accepted
---

# La cobertura mueve paradas sin cambiar el dueño del cliente

Cuando un repartidor falta un día, el admin reparte parte de su ruta entre otros repartidores para que la carga no caiga sobre uno solo. Decidimos que cubrir sea **mover paradas** de la ruta del ausente a la ruta del mismo día del que cubre, y que el cliente siga siendo del ausente: la falta es de un día y mañana el cliente vuelve a su repartidor sin que nadie lo reasigne. Reasignar la cartera es otra operación (transferir cartera) y deja rastro en las reasignaciones.

## Reglas

- **Solo antes de despachar**: la ruta del ausente está planificada y sin despachar, y la del que cubre (si ya existe) tampoco está despachada ni cerrada; si no existe se crea planificada. Así la carga del camión se arma ya con las paradas que recibió, y la app nunca ve aparecer o desaparecer paradas de una ruta en curso.
- **Marca explícita**: la parada movida guarda `route_stops.moved_from_route_id` (la ruta del ausente). Solo esas paradas son cobertura. Una parada de un cliente reasignado después de armada la ruta no tiene la marca y la app la sigue ocultando, como siempre.
- **El dueño no cambia**: `customers.driver_id` queda igual y no se registra reasignación. Volver a generar las rutas del día no devuelve el cliente movido a la ruta del ausente.

## Lo que recibe la app del que cubre

- Una vez despachada su ruta, el bootstrap y la bajada le mandan las paradas cubiertas y sus clientes con todo lo que lleva un cliente propio: direcciones, preferencias, acuerdos, saldo y saldo de envases. Si el despacho ocurre después del cursor, el cliente llega entero, como uno reasignado.
- El cliente cubierto viaja con su `driver_id` real (el del ausente); la app no recibe ningún campo nuevo.
- Cuando termina el día de la ruta que lo cubre, el cliente se va como uno reasignado: la bajada lo informa una sola vez en `customers_removed`, con `removed_at` en la medianoche en que terminó la cobertura, y la app conserva su historial. No se informa si el repartidor es su dueño ahora o lo sigue cubriendo otro día. El bootstrap simplemente deja de incluirlo.

## Excepción a ADR 0001 para los hechos de la calle

Una venta, un cobro, una visita o un movimiento de envases sobre un cliente cubierto no se marcan "cliente asignado a otro repartidor" cuando el repartidor lo cubre **el día del registro** (la fecha de la venta, del cobro o de la llegada; el movimiento de envases toma el día de su visita o de su venta, o el día en que llega si va suelto) en una ruta despachada. Se toma el día y no la parada porque una venta o un cobro pueden ir sin visita. Los cambios a los *datos* del cliente cubierto (nombre, direcciones, preferencias, baja) siguen la excepción de ADR 0001: quedan pendientes en Revisión, porque el cliente sigue siendo de otro.

## Considered Options

- **Reasignar los clientes por el día**: reusa la sincronización existente, pero obliga a devolverlos a mano y llena el historial de reasignaciones que no son cambios de cartera.
- **Cambiar el repartidor de toda la ruta**: no reparte la carga entre varios.
- **Inferir la cobertura de una parada de un cliente ajeno**: no distingue una parada movida de una de un cliente reasignado después de armada la ruta, que la app debe seguir ocultando.
- **Mover paradas marcadas, dueño intacto, solo antes de despachar** (elegida).

## Consequences

- La bajada y el bootstrap deciden qué clientes manda por dueño o por cobertura vigente; las reasignaciones y el fin de una cobertura alimentan las mismas bajas.
- Cubrir después de despachar no es posible: si falta alguien con la ruta ya despachada, hay que resolverlo de otra forma.
