---
status: accepted
---

# La app crea las paradas reprogramadas del mismo día

El servidor es dueño de las rutas y sus paradas; la app solo las baja. La única excepción es la reprogramación: cuando el cliente está ausente, el repartidor elige en la app una hora posterior del mismo día y la app crea una nueva parada en la misma ruta, vinculada a la original, que sube junto con la visita. Lo decide el repartidor porque ocurre en la calle sin señal, y es el mismo día porque la reprogramación es "vuelvo más tarde", no "otra ruta otro día".

## Considered Options

- **Que el servidor cree la parada al recibir la visita**: sin señal el repartidor no vería la nueva parada hasta sincronizar, que puede ser al final del día.
- **Franjas fijas o la ventana de preferencia del cliente**: estructuran la hora pero obligan a modelar franjas que hoy no existen; se eligió hora libre.

## Consequences

- La parada del servidor necesita una hora programada y el vínculo a la parada original.
- Una parada reprogramada que termina el día sin visitarse queda para revisión; ni la app ni el servidor la pasan a otro día.
