# Documentación del Proyecto - OptikamaldeojoPlatform

Esta carpeta contiene la documentación técnica y funcional detallada de la plataforma interna de la óptica **Mal de Ojo** (Estella). 

El objetivo es proporcionar un mapa claro de la arquitectura, infraestructura, base de datos y flujos de negocio tanto para desarrolladores humanos como para agentes de Inteligencia Artificial (IA) que colaboren en el proyecto.

## Índice de Documentos

Para entender en detalle cada sección del proyecto, consulta los siguientes archivos:

1. 📂 **[Entornos y Despliegue (environments.md)](file:///Users/mikel/Developer/ProyectosPersonales/OptikamaldeojoPlatform/docs/environments.md)**
   - Configuración de servidores en Hostinger (Compartido).
   - Gestión de ramas Git (`test` y `prod`).
   - Flujo de integración continua (CI/CD) con GitHub Actions.
   - Seguridad y credenciales dinámicas (`db_config.php`).

2. 📂 **[Arquitectura de Base de Datos (db.md)](file:///Users/mikel/Developer/ProyectosPersonales/OptikamaldeojoPlatform/docs/db.md)**
   - Detalles de las bases de datos de Test (`u373487989_maldeojotest`) y Producción (`u373487989_maldeojo`).
   - Estructura detallada de las tablas (Pedidos, Facturas, Clientes, Proveedores, etc.).
   - Relaciones, restricciones de claves foráneas y política de Borrado Lógico (Soft Delete).

3. 📂 **[Módulo de Pedidos (pedidos_features.md)](file:///Users/mikel/Developer/ProyectosPersonales/OptikamaldeojoPlatform/docs/pedidos_features.md)**
   - Reglas de negocio del gestor de pedidos.
   - Ciclo de vida de un pedido (Máquina de Estados de `recibido`).
   - Estructura de graduación RX unificada y Lógica de Packs de Lentillas (Cajas y Blísters).
   - Envío de notificaciones mediante WhatsApp Web (Castellano/Euskera).

4. 📂 **[Módulo de Facturas Check (facturas_check.md)](file:///Users/mikel/Developer/ProyectosPersonales/OptikamaldeojoPlatform/docs/facturas_check.md)**
   - Arquitectura del analizador de facturas (Frontend React/Vite + Backend PHP API).
   - Flujo de extracción de datos: Parser local tabulado vs Gemini 2.5 Flash.
   - Optimización de costes de API, renderizado PDF en el cliente (PDF.js) y validaciones de totales.

4b. 📂 **[Extracción de facturas con IA por proveedor (facturas_extraccion.md)](file:///Users/mikel/Developer/ProyectosPersonales/OptikamaldeojoPlatform/docs/facturas_extraccion.md)**
   - Matching robusto de proveedores (nombre fiscal vs proveedor oficial de pedidos).
   - Estructura por pedidos en facturas (número de pedido, fecha, referencia cliente/paciente).
   - Validación aritmética de la extracción y flujo de trabajo para afinar reglas por proveedor.

5. 📂 **[Asistente RAG e Inteligencia Artificial (rag_ai.md)](file:///Users/mikel/Developer/ProyectosPersonales/OptikamaldeojoPlatform/docs/rag_ai.md)**
   - Arquitectura de búsqueda híbrida actual en MySQL (Texto completo y filtros).
   - Almacenamiento de páginas visuales como evidencia de auditorías.
   - Plan y hoja de ruta para la implementación de embeddings semánticos y bases de datos vectoriales.

---

## Cómo mantener la documentación actualizada

Este sistema de documentación debe mantenerse sincronizado con la evolución del proyecto. Debe actualizarse obligatoriamente cuando:
- Se realicen cambios en los esquemas de base de datos (`.sql`).
- Se introduzcan nuevas variables de entorno o flujos de despliegue en `.github/workflows`.
- Se modifiquen reglas críticas de negocio (ej. estados de pedidos, validaciones de IVA en facturas).
- Se implementen nuevos módulos o integraciones (como el RAG avanzado).

> [!TIP]
> Si eres una IA asistiendo en este proyecto, lee detenidamente esta carpeta de documentación y el archivo `AI_CONTEXT.md` de la raíz antes de escribir código o proponer cambios de base de datos.
