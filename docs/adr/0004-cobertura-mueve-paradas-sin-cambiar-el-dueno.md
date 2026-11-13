---
status: accepted
---

# La cobertura mueve paradas sin cambiar el dueño del cliente

Cuando un repartidor falta un día, el admin reparte parte de su ruta entre otros repartidores para que la carga no caiga sobre uno solo. Decidimos que cubrir sea **mover paradas** de la ruta del ausente a la ruta del mismo día del que cubre, y que el cliente siga siendo del ausente: la falta es de un día y mañana el cliente vuelve a su repartidor sin que nadie lo reasigne. Reasignar la cartera es otra operación (transferir cartera) y deja rastro en las reasignaciones.

## Reglas

- **La ruta del ausente, antes de despacharse**: está planificada y sin despachar, así su app nunca ve desaparecer paradas.
- **La del que cubre, aunque ya haya salido**: puede estar planificada o en curso, despachada o no; solo se rechaza si está cerrada o deshabilitada, y si no existe se crea planificada. A veces un repartidor ya salió cuando se dan cuenta de que otro faltó, y la ruta se reorganiza en caliente: su app recibe las paradas nuevas en la próxima bajada. Si el camión ya salió, lo que falte de carga se registra como una recarga.
- **Marca explícita**: la parada movida guarda `route_stops.moved_from_route_id` (la ruta del ausente). Solo esas paradas son cobertura. Una parada de un cliente reasignado después de armada la ruta no tiene la marca y la app la sigue ocultando, como siempre.
- **El dueño no cambia**: `customers.driver_id` queda igual y no se registra reasignación. Volver a generar las rutas del día no devuelve el cliente movido a la ruta del ausente.

## Lo que recibe la app del que cubre

- Una vez despachada su ruta, el bootstrap y la bajada le mandan las paradas cubiertas y sus clientes con **solo lo que necesita una visita**: la fila del cliente, la dirección de su parada cubierta y su dirección principal si es otra, su saldo, su saldo de envases y sus acuerdos. Nunca sus preferencias ni sus otras direcciones: ni en el bootstrap, ni en la bajada (tampoco cuando el despacho ocurre después del cursor y el cliente llega con todas sus filas, ni cuando la parada se mueve a una ruta ya despachada y llega igual que con el despacho, porque mover la parada la pone en la ventana, ni cuando una de esas filas cambia dentro de la ventana). El que cubre visita un día, no administra la cartera del ausente.
- Un cliente que es del repartidor y además está en una parada movida es propio: viaja entero, como siempre.
- El cliente cubierto viaja con su `driver_id` real (el del ausente). Para que la app no tenga que deducirlo, dos marcas aditivas:
  - cada parada del bootstrap y de la bajada lleva `is_cover`: `true` si fue movida a la ruta de este repartidor para cubrir a otro (`moved_from_route_id` no es nulo), `false` si no;
  - cada parada del bootstrap y de la bajada lleva `covered_driver_name`: en una parada cubierta, el nombre del repartidor de la ruta de la que vino (el ausente, aunque el cliente haya cambiado de dueño después), para que la app muestre a quién se cubre; `null` en una parada propia. Si el ausente cambia de nombre durante la cobertura, la parada vuelve a viajar con el nombre nuevo;
  - cada fila de cliente del bootstrap y de la bajada (también la que viaja sola porque cambió dentro de la ventana, y la que llega entera por una reasignación) lleva `is_cover`: `true` si el repartidor lo recibe solo por cobertura, `false` si es suyo, aunque además esté en una parada movida. La fila del cliente que devuelve la subida ante un conflicto (`current`) tiene la misma forma.
- En la app el cliente cubierto aparece solo dentro de la ruta, nunca en la lista de clientes, y es de solo lectura; la parada muestra la dirección de la parada.
- Cuando termina el día de la cobertura y todo lo que se registró sobre esos clientes ya subió y fue aceptado, la app borra esos clientes y sus filas **solo de su base local**, nunca por su cola de sincronización: el servidor nunca borra nada y no se entera. Como respaldo, la bajada lo informa una sola vez en `customers_removed`, como uno reasignado, con `removed_at` en la medianoche en que terminó la cobertura. No se informa si el repartidor es su dueño ahora o lo sigue cubriendo otro día. El bootstrap simplemente deja de incluirlo.

## Excepción a ADR 0001 para los hechos de la calle

Una venta, un cobro, una visita o un movimiento de envases sobre un cliente cubierto no se marcan "cliente asignado a otro repartidor" cuando el repartidor lo cubre **el día del registro** (la fecha de la venta, del cobro o de la llegada; el movimiento de envases toma el día de su visita o de su venta, o el día en que llega si va suelto) en una ruta despachada. Se toma el día y no la parada porque una venta o un cobro pueden ir sin visita. Los cambios a los *datos* del cliente cubierto (nombre, direcciones, preferencias, baja) siguen la excepción de ADR 0001: quedan pendientes en Revisión, porque el cliente sigue siendo de otro.

## Considered Options

- **Reasignar los clientes por el día**: reusa la sincronización existente, pero obliga a devolverlos a mano y llena el historial de reasignaciones que no son cambios de cartera.
- **Cambiar el repartidor de toda la ruta**: no reparte la carga entre varios.
- **Inferir la cobertura de una parada de un cliente ajeno**: no distingue una parada movida de una de un cliente reasignado después de armada la ruta, que la app debe seguir ocultando.
- **Mover paradas marcadas, dueño intacto, desde una ruta sin despachar** (elegida). Al principio también exigía que la ruta del que cubre no estuviera despachada; se levantó para reorganizar rutas en caliente.

## Consequences

- La bajada y el bootstrap deciden qué clientes manda por dueño o por cobertura vigente, y cuánto de cada uno; las reasignaciones y el fin de una cobertura alimentan las mismas bajas.
- Si cambia la dirección principal de un cliente cubierto durante la cobertura, la anterior deja de viajar y la app puede conservarla hasta la limpieza local del fin del día.
- Se puede cubrir con un repartidor que ya está en la calle; no con la ruta del ausente ya despachada.
- Una parada movida a una ruta en curso conserva su número de parada original: no se reordena al final.
