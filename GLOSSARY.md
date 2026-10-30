# Marilin

Distribución de agua y soda a domicilio: un repartidor recorre una ruta diaria, entrega productos, gestiona envases y cobra. La administración se hace en la web; la operación en la calle, en la app móvil.

## Language

**Repartidor**:
La persona que recorre la ruta, entrega, cobra y usa la app móvil. Es el único usuario de la app.
_Avoid_: Chofer, conductor, driver

**Ruta**:
El recorrido de un repartidor en un día; existe exactamente una por repartidor y fecha.
_Avoid_: Reparto, recorrido, viaje

**Parada**:
Un cliente con su dirección dentro de una ruta, en un orden determinado.
_Avoid_: Stop, punto, destino

**Parada hecha**:
Una parada con su visita terminada, sea cual sea el resultado de visita. Una parada reprogramada no está hecha: la que queda por hacer es la nueva.
_Avoid_: Parada completada, parada entregada, parada finalizada

**Visita**:
Lo que ocurre cuando el repartidor llega a una parada: un resultado y, opcionalmente, ventas, pagos y movimientos de envases.
_Avoid_: Entrega, atención

**Inventario de ruta**:
Los productos cargados para una ruta y lo que queda de ellos. No existe la noción de camión como entidad.
_Avoid_: Inventario del camión, stock del camión, carga

**Envase**:
Recipiente retornable (bidón o sifón) que el cliente recibe y devuelve; se lleva un saldo de envases por cliente.
_Avoid_: Container, botella, retornable

**Saldo inicial**:
La deuda y los envases con que un cliente entra al sistema. Queda como un ajuste más en su historial, así que todo saldo se explica sumando sus movimientos.
_Avoid_: Saldo de apertura, deuda previa

**Venta en casa**:
Una venta o un cobro que se hace cuando el cliente viene a la casa porque el repartidor no pasó; la registra un administrador, sin visita, y puede incluir envases que se lleva o devuelve. No pertenece a ninguna ruta ni a ningún repartidor y no descuenta inventario de ruta.
_Avoid_: Venta en mostrador, venta de oficina, venta manual

**Cobro en casa**:
El cobro de la misma situación que una venta en casa, sin venta asociada.
_Avoid_: Pago en oficina, cobro manual

**Cierre de caja**:
Lo que un repartidor vendió, cobró y movió de envases en un día: lo hecho en sus visitas y lo que registró él sin visita. Toda cifra por repartidor usa esta misma pertenencia.
_Avoid_: Rendición, caja del día, liquidación

## Sincronización

**Bajada**:
Lo que la app recibe del servidor: ruta del día, clientes asignados con su saldo, catálogo e inventario de ruta, incluidas las bajas.
_Avoid_: Pull, descarga, bootstrap

**Subida**:
Lo que la app envía al servidor: visitas, ventas, pagos, movimientos de envases y clientes creados o editados en la calle.
_Avoid_: Push, upload, envío

**Pendiente**:
Un registro creado o editado en la app que todavía no fue aceptado por el servidor. Vive solo en el teléfono del repartidor.
_Avoid_: Outbox, cola, no sincronizado

**Rechazado**:
Un pendiente que el servidor no aceptó; queda en el teléfono con el motivo y no se reintenta solo.
_Avoid_: Fallido, error de sync

**Para revisión**:
Un registro que el admin debe mirar en la web: una venta o pago sobre un cliente dado de baja o reasignado a otro repartidor, o una parada que terminó el día sin estar hecha, o una venta cuyo precio no coincide con el acuerdo vigente ni con el precio de lista.
_Avoid_: Conflicto, inconsistente, flag

**Acuerdo**:
Un precio pactado con un cliente para un producto, vigente entre dos fechas. Si existe, manda sobre el precio de lista: el precio de una venta es siempre el del acuerdo vigente o, si no hay, el de lista, y nadie lo cambia a mano.
_Avoid_: Descuento, precio especial, convenio

**Resultado de visita**:
Lo que pasó en la parada, elegido de un catálogo que define el servidor (entregado, ausente, etc.). Algunos resultados disparan una reprogramación.
_Avoid_: Outcome, estado de visita, motivo

**Reprogramación**:
Una nueva parada para el mismo cliente, más tarde el mismo día y en la misma ruta, a la hora que elige el repartidor en la app cuando el resultado de la visita lo dispara. Queda vinculada a la parada original.
_Avoid_: Reagendar, reintento, segunda visita
