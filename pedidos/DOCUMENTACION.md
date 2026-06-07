# Sistema de Gestión de Pedidos — Optikamaldeojo
## Documentación técnica completa

---

## 1. Qué es y para qué sirve

El **gestor de pedidos** es una aplicación web interna para la óptica Optikamaldeojo (Estella). Permite al personal gestionar el ciclo de vida completo de los pedidos a proveedores: desde que un cliente encarga un producto hasta que lo recoge en tienda.

**Acceso:** uso exclusivo en tienda, principalmente desde una tablet.  
**Entorno:** PHP sobre hosting compartido Hostinger, base de datos MySQL.  
**URL interna:** `https://[dominio]/pedidos/views/listado_pedidos.php`

---

## 2. Tecnologías

| Capa | Tecnología |
|------|-----------|
| Servidor | PHP 8+ (hosting compartido Hostinger) |
| Base de datos | MySQL via PDO con prepared statements |
| Frontend | Bootstrap 5.3 + FontAwesome 6 + Bootstrap Icons |
| Gráficas | Chart.js 4 |
| Calendario | FullCalendar 6 |
| JavaScript | Vanilla JS (sin frameworks) |
| Autenticación | Sesiones PHP nativas |

---

## 3. Estructura de archivos

```
pedidos/
├── views/                    # Páginas HTML (lo que ve el usuario)
│   ├── listado_pedidos.php   # Vista principal — listado por estados
│   ├── formulario_pedidos.php # Nuevo pedido / editar pedido
│   ├── formulario_usuarios.php# Nuevo cliente / editar cliente
│   ├── ficha_cliente.php     # Perfil completo de un cliente
│   ├── listado_usuarios.php  # Listado de todos los clientes
│   ├── listado_proveedores.php# Listado de proveedores
│   ├── carrito_pedidos.php   # Vista de pedidos listos para pedir al proveedor
│   ├── busqueda.php          # Búsqueda global en toda la base de datos
│   ├── estadisticas.php      # Dashboard de KPIs y gráficas
│   ├── calendario.php        # Calendario de llegadas previstas
│   ├── gestionar_mensajes.php # Editar mensajes WhatsApp predefinidos
│   ├── copias_seguridad.php  # Backups de la base de datos
│   ├── header.php            # Cabecera HTML común (navbar + breadcrumb)
│   └── footer.php            # Pie HTML común (scripts + sistema de toasts)
│
├── controllers/              # Lógica de negocio (reciben POST/GET, responden JSON o redirigen)
│   ├── insertar_pedido.php   # Crear nuevo pedido
│   ├── editar_pedido.php     # Mostrar formulario de edición
│   ├── procesar_editar_pedido.php # Guardar cambios de un pedido
│   ├── eliminar_pedido.php   # Soft delete de un pedido
│   ├── restaurar_pedido.php  # Restaurar pedido borrado
│   ├── marcar_recibido.php   # Marcar pedido como recibido (completo o parcial)
│   ├── cambiar_estado_pedido.php # Cambio genérico de estado
│   ├── cancelar_pedido.php   # Cancelar pedido (recibido = 3)
│   ├── toggle_carrito.php    # Añadir/quitar pedido del carrito de compra
│   ├── toggle_avisado.php    # Marcar/desmarcar "cliente avisado"
│   ├── marcar_pedidos_batch.php # Marcar lote de pedidos como pedidos al proveedor
│   ├── guardar_usuario.php   # Crear/actualizar cliente
│   ├── eliminar_usuario.php  # Eliminar cliente
│   ├── crear_cliente_ajax.php # Crear cliente vía AJAX (desde formulario pedido)
│   ├── guardar_proveedor.php # Crear/actualizar proveedor
│   ├── eliminar_proveedor.php# Eliminar proveedor
│   ├── crear_proveedor_ajax.php # Crear proveedor vía AJAX
│   ├── exportar_pedidos.php  # Exportar pedidos a CSV
│   ├── exportar_clientes.php # Exportar clientes a CSV
│   └── exportar_proveedores.php # Exportar proveedores a CSV
│
├── includes/                 # Utilidades compartidas
│   ├── auth.php              # Verificación de sesión (protege todas las páginas)
│   ├── conexion.php          # Clase Conexion — instancia PDO
│   ├── db_config.php         # Credenciales BD (NO está en git)
│   ├── db_config.example.php # Plantilla de credenciales
│   ├── funciones.php         # Funciones auxiliares: mostrarTabla(), formatearRX(), parsearVia()...
│   ├── ClienteHelper.php     # Helpers específicos de clientes
│   ├── whatsapp_redirect.php # Redireccionador de links WhatsApp
│   └── backup.php            # Clase para copias de seguridad
│
├── assets/
│   └── css/
│       └── style.css         # Sistema de diseño propio (Design System v3)
│
└── sql/                      # Migraciones de base de datos
    ├── soft_delete_pedidos.sql
    ├── add_en_carrito.sql
    └── avisado_cliente.sql
```

---

## 4. Base de datos

### Tablas principales

#### `pedidos` — tabla central
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `fecha_cliente` | DATE | Fecha en que el cliente realizó el encargo |
| `referencia_cliente` | VARCHAR | Referencia del cliente (FK lógica a `clientes.referencia`) |
| `lc_gafa_recambio` | VARCHAR | Descripción del producto (lente, gafa, recambio…) |
| `rx` | TEXT | Graduación en formato texto libre o legacy |
| `rx_lineas` | JSON | Graduación estructurada (OD/OI con esf/cil/eje/add) |
| `pack_tipo` | ENUM('cajas','blisters','ambos') NULL | Si el pedido incluye pack de lentillas |
| `pack_estado` | JSON NULL | Estado del pack: `{"cajas":{"pedidas":4,"recibidas":2},"blisters":{...}}` |
| `fecha_pedido` | DATE NULL | Fecha en que se hizo el pedido al proveedor |
| `fecha_llegada` | DATE NULL | Fecha prevista de llegada del proveedor |
| `via` | VARCHAR NULL | Canal por el que llegó el encargo (WhatsApp, Teléfono, Web…) |
| `observaciones` | TEXT NULL | Notas internas del pedido |
| `proveedor_id` | INT NULL | FK a `proveedores.id` |
| `recibido` | TINYINT | **Estado del pedido** (ver sección 5) |
| `notas_recepcion` | TEXT NULL | Notas de recepción parcial o motivo de cancelación |
| `en_carrito` | TINYINT(1) DEFAULT 0 | Si está marcado para pedir al proveedor |
| `avisado_cliente` | TINYINT(1) DEFAULT 0 | Si se ha avisado al cliente de que llegó |
| `deleted_at` | TIMESTAMP NULL | Soft delete — NULL = activo |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Última modificación |

#### `clientes`
| Campo | Descripción |
|-------|-------------|
| `id` | Clave primaria |
| `referencia` | Identificador único del cliente (nombre corto o código) |
| `telefono` | Teléfono de contacto |
| `email` | Correo electrónico |
| `direccion` | Dirección postal |

#### `proveedores`
| Campo | Descripción |
|-------|-------------|
| `id` | Clave primaria |
| `nombre` | Nombre del proveedor (Essilor, Hoya, Indo…) |
| `contacto` | Persona de contacto |
| `telefono` | Teléfono |
| `email` | Email |

#### `mensajes_whatsapp`
| Campo | Descripción |
|-------|-------------|
| `id` | Clave primaria |
| `tipo` | Tipo de mensaje (`recibido`, etc.) |
| `idioma` | Idioma (`es`, `eu`) |
| `mensaje` | Texto del mensaje con variables `{cliente}` y `{producto}` |

#### `usuarios`
Tabla de acceso al sistema (empleados/admin). Gestionada separadamente.

---

## 5. Ciclo de vida de un pedido — máquina de estados

El campo `recibido` de la tabla `pedidos` define en qué estado se encuentra:

```
                    ┌─────────────────────────────────────────┐
                    │                                         │
        [Nuevo]     │                                         ▼
           │        │                              ┌──────────────────┐
           ▼        │                              │  recibido = 1    │
  ┌─────────────────┴─┐   fecha_pedido IS NULL     │  FINALIZADO      │
  │  recibido = 0     │ ◄─────────────────────     │  (recibido total)│
  │  NO RECIBIDO      │                            └──────────────────┘
  │  (estado base)    │
  └─────────┬─────────┘
            │
            │  al pedir al proveedor: se rellena fecha_pedido + fecha_llegada
            │
     ┌──────┴──────────────────────────────┐
     │                                     │
     ▼ fecha_llegada > hoy                 ▼ fecha_llegada <= hoy
┌──────────────┐                      ┌──────────────┐
│ "En camino"  │                      │ "Atrasado"   │
│ (visual)     │                      │ (visual)     │
└──────┬───────┘                      └──────┬───────┘
       │                                     │
       └─────────────┬───────────────────────┘
                     │
          ┌──────────┴───────────┐
          │                      │
          ▼                      ▼
┌──────────────────┐    ┌──────────────────┐
│  recibido = 2    │    │  recibido = 3    │
│  PARCIAL         │    │  CANCELADO       │
│  (llegó parte)   │    │                  │
└──────────────────┘    └──────────────────┘
          │
          │  al recibir el resto
          ▼
┌──────────────────┐
│  recibido = 1    │
│  FINALIZADO      │
└──────────────────┘
```

### Resumen de valores `recibido`
| Valor | Estado | Descripción |
|-------|--------|-------------|
| `0` | No recibido | Estado base. Si `fecha_pedido IS NULL` → "Por pedir". Si tiene fechas → "En camino" o "Atrasado" según `fecha_llegada` |
| `1` | Finalizado | El pedido llegó completo y el cliente fue avisado/retiró |
| `2` | Parcial | Llegó solo parte del pedido. Sigue activo hasta completarse |
| `3` | Cancelado | Pedido anulado. Se guarda el motivo en `notas_recepcion` |

> **Nota sobre "Por pedir" vs "Atrasado":** estas clasificaciones son **visuales**, calculadas en PHP al cargar la página. No son valores de `recibido`.

---

## 6. Secciones del listado principal (`listado_pedidos.php`)

El listado está dividido en 5 secciones colapsables, cada una con borde de color superior:

### 1. Pendientes de Pedir (amarillo)
- Pedidos con `recibido IN (0,2)` y `fecha_pedido IS NULL`
- El personal aún no ha contactado al proveedor
- Muestra columna **Espera** (días desde `fecha_cliente`, en rojo si ≥5 días)
- Botón "Carrito" para seleccionar cuáles enviar en un solo pedido batch

### 2. Pedidos Atrasados (rojo, pulsa si hay alguno)
- Pedidos con `recibido IN (0,2)` y `fecha_llegada <= hoy`
- La fecha prevista de llegada ya pasó y no han llegado
- Muestra columna **Atraso** (días de retraso)

### 3. Pendientes de Recibir (azul)
- Pedidos con `recibido IN (0,2)` y `fecha_llegada > hoy`
- En tránsito, esperando llegada

### 4. Cancelados (gris, colapsado por defecto)
- Pedidos con `recibido = 3`
- Solo visible si existen. Muestra el motivo de cancelación
- Limitado a los últimos 100

### 5. Pedidos Finalizados (verde)
- Pedidos con `recibido = 1`
- Filtro de fechas configurable (por defecto últimos 90 días)
- Muestra el total histórico junto al filtrado

---

## 7. Acciones disponibles en la tabla

### Botones de acción en cada fila (activos)

| Botón | Icono | Qué hace |
|-------|-------|----------|
| Marcar recibido | ✓ verde | Llama a `marcar_recibido.php` con `recibido_val=1`. Pedido pasa a Finalizado |
| Recepción parcial | 📦 amarillo | Abre modal para indicar qué parte llegó (`recibido=2`) y notas |
| Cancelar | 🚫 rojo | Abre modal de confirmación con campo motivo. Llama a `cancelar_pedido.php` |
| Carrito | 🛒 cian | Toggle `en_carrito`. Marca para pedido batch al proveedor |
| Editar | ✏️ índigo | Navega al formulario de edición completa del pedido |

### Botones en Finalizados

| Botón | Qué hace |
|-------|----------|
| Avisado cliente (teléfono) | Toggle `avisado_cliente`. Verde = avisado, gris = no avisado |
| Deshacer recepción | Vuelve el pedido a `recibido=0` |

### Clic en cualquier fila
Abre un **modal de detalles** con toda la información del pedido: cliente, producto, RX formateada, estado del pack, observaciones completas, fechas, vía, y botones de WhatsApp.

### Clic en el nombre del cliente
Navega directamente a la **ficha del cliente**.

---

## 8. Sistema de Pack (lentes de contacto)

Algunos pedidos incluyen packs de lentillas que se rastrean con granularidad:

**`pack_tipo`**: `cajas` | `blisters` | `ambos`

**`pack_estado`** (JSON):
```json
{
  "cajas":    { "pedidas": 4, "recibidas": 2 },
  "blisters": { "pedidas": 12, "recibidas": 0 }
}
```

- Si `recibidas >= pedidas` → ✅ tachado (completo)
- Si `recibidas > 0` pero incompleto → ⚠️ naranja con ratio `2/4`
- Si `recibidas = 0` → azul pendiente `0/4`

En el modal de recepción parcial se pueden actualizar los números recibidos.

---

## 9. Carrito de pedidos (`carrito_pedidos.php`)

Flujo para hacer un pedido batch a un proveedor:

1. En "Pendientes de Pedir", el personal marca las lentes que quiere pedir con el botón 🛒 → `en_carrito = 1`
2. Se accede a **Carrito** desde el botón de la cabecera (muestra badge con el número)
3. En carrito aparecen agrupados por proveedor
4. Se seleccionan los que se quieren pedir y se introduce:
   - **Fecha de pedido** (hoy normalmente)
   - **Fecha prevista de llegada**
5. Al confirmar, `marcar_pedidos_batch.php` actualiza todos de golpe: `fecha_pedido`, `fecha_llegada`, `en_carrito = 0`
6. Los pedidos pasan a la sección "Pendientes de Recibir"

---

## 10. Búsqueda global (`busqueda.php`)

Busca en todos los pedidos (sin restricción temporal) por:
- `referencia_cliente`
- `lc_gafa_recambio` (producto)
- `rx` (graduación)
- `observaciones`
- `via`

Requisito mínimo: 2 caracteres. Muestra hasta 200 resultados agrupados por estado (los mismos 5 grupos que el listado principal).

---

## 11. Ficha de cliente (`ficha_cliente.php`)

Acceso: clic en el nombre del cliente en cualquier tabla, o desde `listado_usuarios.php`.  
Parámetros aceptados: `?id=N` (por ID numérico) o `?ref=REFERENCIA` (por referencia).

Muestra:
- **Cabecera**: avatar, referencia, teléfono, email, dirección, botones Editar y Nuevo Pedido
- **KPIs**: total pedidos, pedidos activos, tiempo medio de entrega, fecha primera compra
- **Historial completo**: todos sus pedidos con RX formateada, estado, fechas, vía — ordenados del más reciente al más antiguo

---

## 12. WhatsApp

El sistema genera links de WhatsApp para notificar a los clientes:

1. Los mensajes plantilla se editan en `gestionar_mensajes.php`
2. Soporta dos idiomas: **Castellano** y **Euskera**
3. Variables disponibles en los mensajes: `{cliente}` y `{producto}`
4. Los links pasan por `whatsapp_redirect.php` que limpia el número de teléfono (añade `+34` si no tiene prefijo)
5. Al hacer clic en un botón de WhatsApp desde el modal, se marca automáticamente `avisado_cliente = 1`

---

## 13. Autenticación y roles

**Fichero:** `includes/auth.php` — incluido al inicio de cada vista y controller.

Si no hay sesión activa (`$_SESSION['usuario_id']` no existe) → redirige a `index.php`.

| Rol | Acceso |
|-----|--------|
| `empleado` | Gestión completa de pedidos y clientes |
| `admin` | Todo lo anterior + backups + gestión de mensajes WhatsApp + acceso al Portal |

---

## 14. Soft Delete

Los pedidos **nunca se borran físicamente**. Al "eliminar" un pedido:

```sql
UPDATE pedidos SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL;
```

Para restaurarlo: `restaurar_pedido.php` pone `deleted_at = NULL`.

**Todas las consultas** deben incluir `AND p.deleted_at IS NULL` para excluir borrados. Las vistas del sistema ya lo hacen; si se añaden nuevas consultas hay que tenerlo en cuenta.

---

## 15. Estadísticas (`estadisticas.php`)

Dashboard con:

| KPI | Descripción |
|-----|-------------|
| Sin Pedir | Pedidos activos sin `fecha_pedido` |
| Atrasados | Pedidos activos con `fecha_llegada` pasada |
| En Camino | Pedidos en tránsito |
| Finalizados Hoy | Pedidos finalizados con `fecha_llegada = hoy` |
| Entrega Media | Media de días `fecha_llegada - fecha_pedido` (últimos 90 días) |
| Cancelados | Total histórico de cancelados |

Gráficas:
- **Actividad 14 días**: barras de pedidos por día
- **Evolución mensual**: línea de pedidos por mes (6 meses)
- **Canal de pedido**: doughnut de vías normalizadas
- **Top 5 clientes**: barras de progreso relativas
- **Top 5 productos**: barras de progreso relativas
- **Tiempo medio por proveedor**: barras con código de color (verde ≤5d, naranja ≤10d, rojo >10d)

---

## 16. Calendario de llegadas (`calendario.php`)

Usa **FullCalendar 6** con vista mensual por defecto (también semanal y lista).

Eventos:
- 🟦 **Azul** — pendiente de llegar
- 🟥 **Rojo** — atrasado (fecha pasada sin recibir)
- 🟩 **Verde** — finalizado

Al hacer clic en un evento se abre un mini-modal con detalles y enlace a edición.  
Filtros por estado: Todos / Pendientes / Atrasados / Finalizados.

Rango cargado: 3 meses atrás y 3 meses adelante respecto a hoy.

---

## 17. Sistema de toasts (notificaciones)

Definido en `footer.php`. Disponible en todas las páginas como función global:

```javascript
showToast('Texto del mensaje', 'success' | 'error' | 'warning' | 'info', duracionMs);
```

- Aparecen en la esquina inferior derecha
- Animación slide-in desde la derecha
- Auto-cierre configurable (por defecto 3.5s)
- Botón de cierre manual
- Colores semánticos: verde éxito, rojo error, naranja aviso, azul información

---

## 18. Normalización de la vía (`parsearVia`)

La función `parsearVia($via)` en `funciones.php` convierte texto libre a un canal normalizado:

| Texto original | Canal normalizado |
|---------------|-------------------|
| "whatsapp imanol" | WhatsApp |
| "tel 600123456" | Teléfono |
| "web portal" | Web |
| "email" | E-mail |
| "presencial" | Presencial |
| cualquier otra cosa | Otro |

Se usa en: listado (badge de color), búsqueda, estadísticas (doughnut), y ficha cliente.

---

## 19. Formateo de graduación RX (`formatearRX`)

La graduación se puede almacenar en dos formatos:

**Formato legacy** (texto libre en campo `rx`):
```
OD -1.25 -0.50 90 / OI -1.00
```

**Formato estructurado** (JSON en campo `rx_lineas`):
```json
[
  {
    "nota": "Visión lejana",
    "od": { "esf": "-1.25", "cil": "-0.50", "eje": "90", "add": "" },
    "oi": { "esf": "-1.00", "cil": "",       "eje": "",   "add": "" }
  }
]
```

La función detecta el formato automáticamente y genera HTML con badges OD/OI coloreados (azul OD, rojo OI).

---

## 20. Filtros rápidos (listado)

Barra de chips encima de las secciones del listado. Filtran client-side sin recargar la página:

| Filtro | Qué muestra |
|--------|-------------|
| Todos | Sin filtro |
| WhatsApp | Filas que contengan "WhatsApp" en cualquier campo |
| Llega hoy | Filas cuya fecha coincide con hoy |
| [Canal] | Cada canal de vía disponible en los pedidos activos |

Son compatibles con el buscador de texto libre.

---

## 21. Gestión de errores y seguridad

- **Prepared statements PDO** en todas las consultas SQL → protección contra inyección SQL
- **`htmlspecialchars()`** en todos los valores mostrados en HTML → protección XSS
- **`auth.php`** al inicio de cada fichero → sin acceso sin sesión válida
- **Soft delete** → los datos nunca se pierden
- **Credenciales fuera del repositorio** → `includes/db_config.php` en `.gitignore`
- **Validación de IDs**: todos los IDs recibidos por POST/GET se castean a `(int)` antes de usarse

---

## 22. Backups

Desde `copias_seguridad.php` (solo admin):
- **Descargar SQL** — volcado completo de la BD
- **Descargar ZIP** — SQL comprimido
- **Enviar por email** — envía el backup al email del admin

---

## 23. Flujo de trabajo típico de un día

```
1. MAÑANA — Revisar el listado
   → ¿Hay atrasados? → Contactar al proveedor
   → ¿Llegaron pedidos? → Marcar como recibido → Aún no llamar al cliente

2. CUANDO LLEGA EL CLIENTE
   → Marcar como recibido si no estaba → botón ✓
   → O recepción parcial si llegó parte → botón 📦
   → Abrir modal → WhatsApp al cliente → se marca automáticamente "avisado"

3. PEDIDOS NUEVOS
   → Formulario Pedido Nuevo
   → Si aún no se tiene todo → dejar sin fecha_pedido (queda en "Por pedir")
   → Cuando se tiene todo → marcar en carrito 🛒 → ir a Carrito → pedido batch al proveedor

4. CANCELACIONES
   → Botón 🚫 en la fila → modal con motivo → queda en sección Cancelados
```

---

## 24. Añadir una nueva vista (guía rápida)

1. Crear `views/nueva_pagina.php`
2. Incluir al inicio:
   ```php
   require '../includes/auth.php';
   require '../includes/conexion.php';
   require '../includes/funciones.php';
   $breadcrumbs = [['nombre' => 'Título', 'url' => '#']];
   $acciones_navbar = [...];
   include 'header.php';
   ```
3. Contenido HTML
4. Al final: `<?php include 'footer.php'; ?>`
5. Añadir link en el navbar desde las páginas relacionadas

---

## 25. Añadir un nuevo controller (guía rápida)

1. Crear `controllers/nuevo_action.php`
2. Siempre empezar con:
   ```php
   require '../includes/auth.php';
   require '../includes/conexion.php';
   header('Content-Type: application/json');
   ```
3. Si es llamado por AJAX → devolver `json_encode(['success' => true|false, ...])`
4. Si recibe `$_POST['id']` → siempre castear: `$id = (int)$_POST['id']`
5. Usar prepared statements en todas las queries

---

*Documentación generada en junio 2025. Actualizar si se añaden nuevas columnas, estados o flujos.*
