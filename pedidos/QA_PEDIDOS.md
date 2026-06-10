# QA Pedidos

Guia rapida para cerrar cambios del modulo de pedidos sin romper el flujo diario de tienda.

## Estados operativos

- `recibido = 0`: pendiente.
- `recibido = 1`: recibido completo / finalizado.
- `recibido = 2`: recepcion parcial.
- `recibido = 3`: cancelado.
- `deleted_at IS NOT NULL`: eliminado logicamente; no debe aparecer en listados operativos, busqueda, calendario, estadisticas ni sugerencias de ultimos pedidos.

## Checklist manual minimo

1. Crear pedido sin fecha de proveedor y comprobar que aparece en `Pendientes de Pedir`.
2. Anadirlo al carrito y marcar fecha de pedido; comprobar que desaparece del carrito y pasa a `Pendientes de Recibir` o `Sin fecha` segun la fecha prevista.
3. Marcar recepcion parcial y verificar que queda como parcial.
4. Completar recepcion y verificar que pasa a finalizados.
5. Marcar cliente avisado y comprobar que no aparece como pendiente de aviso.
6. Cancelar un pedido y confirmar que aparece como cancelado, no como pendiente.
7. Eliminar logicamente un pedido y confirmar que no aparece en listado, busqueda, calendario, estadisticas ni ultimos pedidos del cliente.
8. Restaurar un pedido eliminado y confirmar que vuelve al flujo correspondiente.
9. Probar busqueda por cliente, producto y RX.
10. Probar calendario y estadisticas despues de cambios de estado.

## Puntos vigilados

- Las acciones directas y AJAX deben cargar `includes/auth.php`.
- Las modificaciones de pedidos deben ignorar filas con `deleted_at IS NOT NULL`, salvo restauracion.
- Los estados recibidos por POST deben estar limitados a `0`, `1`, `2` o `3`.
- Los filtros nuevos deben decidir explicitamente si incluyen cancelados (`recibido = 3`) o eliminados (`deleted_at`).

