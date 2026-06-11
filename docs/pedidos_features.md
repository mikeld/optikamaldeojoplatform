# Módulo de Pedidos

El gestor de pedidos es el core operativo diario de la óptica para el seguimiento de encargos de clientes (lentes de contacto, gafas, recambios, etc.) desde que se solicitan en tienda hasta que el cliente los retira.

---

## 1. Ciclo de Vida y Estados del Pedido

El estado de un pedido se gestiona mediante la columna `recibido` en la tabla `pedidos`. Los estados posibles son:

| Valor `recibido` | Nombre de Estado | Descripción |
| :--- | :--- | :--- |
| **`0`** | **Pendiente / Activo** | Estado base al crearse el pedido. Dependiendo de si tiene fechas asignadas, el sistema lo renderiza visualmente como:<br>• **Por pedir**: Si `fecha_pedido IS NULL`. Todavía está en el carrito o pendiente de envío.<br>• **En camino**: Si `fecha_llegada > HOY`. Pedido enviado al proveedor.<br>• **Atrasado**: Si `fecha_llegada <= HOY` y no se ha recibido. |
| **`1`** | **Finalizado** | El pedido ha sido recibido por completo en tienda. Se activa el flujo de notificación al cliente y posterior retirada. |
| **`2`** | **Recibido Parcial** | Se ha recibido una parte del pedido (cajas/blísters de lentillas). El pedido permanece activo en el listado hasta que se reciba el total restante. |
| **`3`** | **Cancelado** | El pedido se ha cancelado (ej. por error del proveedor, cambio de opinión del cliente). Se archiva con una explicación escrita del motivo. |

---

## 2. Unificación de Graduación (RX) y Packs

Para los pedidos de lentillas, la graduación (RX) y la información del empaque (Packs) se unifican en líneas estructuradas dentro del campo `rx_lineas` en formato JSON.

### Estructura de RX
Cada línea añadida contiene:
- **Empaque**: Selector entre `Blíster` o `Caja`.
- **Ojo**: Selector entre `Ojo Derecho (OD)`, `Ojo Izquierdo (OI)` u `Otro / Sin Especificar`.
- **Valores Ópticos**: Inputs específicos para:
  - **Esfera (ESF)**: Potencia esférica.
  - **Cilindro (CIL)**: Astigmatismo (si aplica).
  - **Eje (EJE)**: Orientación del cilindro (si aplica).
  - **Adición (ADD)**: Presbicia (si aplica).

### Lógica de Packs y Recepción Parcial
Cuando el pedido tiene asignado un pack (tipo `cajas`, `blisters` o `ambos`), se lleva un control del inventario esperado vs. recibido en `pack_estado` (JSON):

```json
{
  "cajas":    { "pedidas": 4, "recibidas": 2 },
  "blisters": { "pedidas": 12, "recibidas": 0 }
}
```

- **Visualización en Listados**:
  - `0/4` (Pendiente): Badge azul indicando que no ha llegado nada.
  - `2/4` (Parcial): Badge naranja que resalta que la recepción está en proceso.
  - `4/4` (Completado): Texto tachado en verde indicando que esa línea ya está lista.
  
- **Recepción Parcial**: Al marcar un pedido como recibido parcial (botón 📦), se abre un modal interactivo donde el usuario introduce la cantidad exacta de blísters y/o cajas recibidas en ese momento. Si al guardar la suma de blísters y cajas recibidos es igual o mayor a lo pedido, el script `marcar_recibido.php` pasa automáticamente el estado general a **Finalizado (`recibido = 1`)**.

---

## 3. Listado de Pedidos y Secciones

La vista principal `pedidos/views/listado_pedidos.php` organiza el trabajo diario en 5 pestañas o secciones colapsables diferenciadas por colores en el borde superior:

1. 🟡 **Pendientes de Pedir**:
   - Pedidos con `recibido IN (0, 2)` y `fecha_pedido IS NULL`.
   - Se muestra una alerta visual con los días en **Espera** (días transcurridos desde que el cliente lo encargó). Se resalta en rojo si lleva 5 días o más sin pedirse al proveedor.
2. 🔴 **Atrasados**:
   - Pedidos con `recibido IN (0, 2)` y `fecha_llegada <= HOY`.
   - Muestra la columna **Atraso** en días. Esta sección se expande automáticamente si existe algún pedido en este estado para llamar la atención del personal.
3. 🔵 **Pendientes de Recibir**:
   - Pedidos con `recibido IN (0, 2)` y `fecha_llegada > HOY`.
   - Pedidos actualmente en tránsito o fabricación por parte del laboratorio.
4. ⚪ **Cancelados**:
   - Pedidos con `recibido = 3`.
   - Colapsado por defecto. Permite revisar el motivo por el cual no se completaron. Limitado a los últimos 100 registros.
5. 🟢 **Finalizados**:
   - Pedidos con `recibido = 1`.
   - Permite filtrar por rango de fechas (por defecto, los últimos 90 días) y muestra resúmenes históricos de entregas.

---

## 4. Flujo de Notificaciones por WhatsApp

El sistema permite agilizar la comunicación con el cliente al llegar su mercancía:

1. **Plantillas Configurables**: Desde `pedidos/views/gestionar_mensajes.php` (solo administradores), se pueden definir y traducir los mensajes automáticos en **Castellano** y **Euskera**.
2. **Variables Dinámicas**: El texto de las plantillas soporta las etiquetas `{cliente}` y `{producto}`, que se sustituyen en tiempo de ejecución con los datos reales del pedido.
3. **Normalización de Teléfonos**: El archivo `pedidos/includes/whatsapp_redirect.php` procesa el teléfono del cliente para asegurar que tiene el prefijo de país (añade `+34` si son 9 dígitos sin prefijo) y limpia espacios o caracteres extraños para abrir el enlace de WhatsApp Web sin errores.
4. **Auto-Avisado**: Al hacer clic en el botón de WhatsApp dentro del modal de detalle de un pedido, el sistema realiza una llamada AJAX en segundo plano para marcar automáticamente el campo `avisado_cliente = 1`, cambiando el icono del listado a verde (cliente notificado).

---

## 5. Copias de Seguridad (Backups)

El sistema provee herramientas de respaldo accesibles únicamente para usuarios con rol `admin` desde `pedidos/views/copias_seguridad.php`:

- **Descargar SQL**: Genera un archivo con la estructura y datos de todas las tablas en texto plano.
- **Descargar ZIP**: Comprime el volcado SQL en un archivo ZIP para descargas más rápidas.
- **Enviar por Email**: Envía de forma automática el archivo comprimido ZIP adjunto a la dirección de correo configurada del administrador, permitiendo automatizar respaldos externos periódicos.
