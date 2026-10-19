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

**Visita**:
Lo que ocurre cuando el repartidor llega a una parada: un resultado y, opcionalmente, ventas, pagos y movimientos de envases.
_Avoid_: Entrega, atención

**Inventario de ruta**:
Los productos cargados para una ruta y lo que queda de ellos. No existe la noción de camión como entidad.
_Avoid_: Inventario del camión, stock del camión, carga

**Envase**:
Recipiente retornable (bidón o sifón) que el cliente recibe y devuelve; se lleva un saldo de envases por cliente.
_Avoid_: Container, botella, retornable

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
Un registro que el admin debe mirar en la web: una venta o pago sobre un cliente dado de baja o reasignado a otro repartidor, o una parada reprogramada que terminó el día sin visitarse.
_Avoid_: Conflicto, inconsistente, flag

**Acuerdo**:
Un precio pactado con un cliente para un producto, vigente entre dos fechas. Si existe, manda sobre el precio de lista.
_Avoid_: Descuento, precio especial, convenio

**Resultado de visita**:
Lo que pasó en la parada, elegido de un catálogo que define el servidor (entregado, ausente, etc.). Algunos resultados disparan una reprogramación.
_Avoid_: Outcome, estado de visita, motivo

**Reprogramación**:
Una nueva parada para el mismo cliente, más tarde el mismo día y en la misma ruta, a la hora que elige el repartidor en la app cuando el resultado de la visita lo dispara. Queda vinculada a la parada original.
_Avoid_: Reagendar, reintento, segunda visita
