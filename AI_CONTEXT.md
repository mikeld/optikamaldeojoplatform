# AI_CONTEXT.md - OptikamaldeojoPlatform

Este archivo es el briefing rapido para cualquier IA o desarrollador que entre al proyecto. Debe actualizarse cuando cambien arquitectura, deploy, errores importantes o decisiones de producto.

## Objetivo del Proyecto

Portal interno para la optica Mal de Ojo / Optikamaldeojo. El sistema se usa en tienda, principalmente escritorio/tablet.

Modulos actuales:

- Portal de acceso por roles.
- Pedidos: operativa diaria de encargos a proveedores.
- Facturas Check: auditoria de facturas, control de precios/proveedores y asistente IA.

Prioridad de producto:

- Fiabilidad operativa antes que automatizacion agresiva.
- Test primero en Hostinger `/test`; produccion solo cuando este validado.
- La IA ayuda a revisar, pero no debe aceptar cambios de precio automaticamente.

## Tecnologias

### Portal y Pedidos

- PHP sobre Hostinger compartido.
- MySQL con PDO.
- Sesiones PHP nativas para autenticacion.
- Bootstrap, FontAwesome/Bootstrap Icons, CSS propio.
- JavaScript vanilla.

Rutas clave:

- Portal: `home.php`, `index.php`, `logout.php`
- Pedidos: `pedidos/views/*`
- Header/navegacion de pedidos: `pedidos/views/header.php`
- Estilos pedidos: `pedidos/assets/css/style.css`
- API Facturas compartida: `pedidos/api/facturas.php`
- Conexion/config: `includes/*`, con `includes/db_config.php` generado en deploy.

Docs largas:

- `docs/index.md` (Índice de documentación técnica y funcional detallada)
- `pedidos/DOCUMENTACION.md`
- `pedidos/GUIA_INSTALACION.md`

### Facturas Check

- Frontend React + Vite en `facturas-src/`.
- Build compilado se despliega en `facturas/`.
- Backend en PHP: `pedidos/api/facturas.php`.
- MySQL como fuente central.
- Gemini se llama desde backend PHP, nunca desde frontend.
- PDF.js se carga bajo demanda para renderizar PDFs antes de mandar imagenes optimizadas a Gemini.
- El parser de texto comun vive en `pedidos/includes/invoice_text_parser.php`.
- Las pruebas del parser viven en `pedidos/tests/invoice_text_parser_test.php`.

Rutas:

- Test: `https://sienna-woodpecker-278479.hostingersite.com/test/facturas/index.html`
- Produccion: `https://sienna-woodpecker-278479.hostingersite.com/facturas/index.html`
- API test desde Facturas: `../pedidos/api/facturas.php`

Tablas principales:

- `facturas_product_families`
- `facturas_products`
- `facturas_audits`
- `facturas_pages`
- `facturas_alerts`
- `facturas_price_history`

Schema base:

- `pedidos/sql/facturas_schema.sql`

## Roles

Idea funcional:

- Empleado: usa Pedidos.
- Encargado/admin: puede acceder a Facturas Check y control.
- Admin: backups, mensajes, configuracion sensible y administracion.

No romper el aislamiento por roles al anadir enlaces o nuevas apps.

## Pedidos - Reglas de Calidad

- `recibido = 0`: pendiente.
- `recibido = 1`: recibido completo / finalizado.
- `recibido = 2`: recepcion parcial.
- `recibido = 3`: cancelado.
- `deleted_at IS NOT NULL`: eliminado logicamente. No debe entrar en listados operativos, busqueda, calendario, estadisticas, acciones AJAX ni sugerencias de ultimos pedidos.
- Solo `restaurar_pedido.php` debe modificar pedidos eliminados.
- Las acciones que reciben estado por POST deben limitarlo a valores conocidos, no aceptar enteros arbitrarios.
- Checklist manual especifico: `pedidos/QA_PEDIDOS.md`.

## Deploy y Entornos

Repo GitHub:

- `mikeld/optikamaldeojoplatform`

Ramas:

- `test`: despliega a Hostinger `/test`.
- `prod`: despliega a Hostinger raiz.
- Rama de trabajo habitual actual: `codex/portal-roles-facturas`.

Bases de datos Hostinger:

- Test: `u373487989_maldeojotest`
- Produccion: `u373487989_maldeojo`

Workflows:

- `.github/workflows/deploy-test.yml`
- `.github/workflows/deploy-prod.yml`

Script de release:

- `deploy/prepare_hostinger_release.sh`

Documentacion deploy:

- `deploy/HOSTINGER.md`

Secretos GitHub Actions relevantes:

- `HOSTINGER_FTP_HOST`
- `HOSTINGER_FTP_USER`
- `HOSTINGER_FTP_PASSWORD`
- `HOSTINGER_TEST_DIR`
- `HOSTINGER_PROD_DIR`
- `TEST_DB_USER`
- `TEST_DB_PASS`
- `PROD_DB_USER`
- `PROD_DB_PASS`
- `GEMINI_API_KEY`

`includes/db_config.php` no debe commitearse como configuracion real. El deploy lo genera con los secretos del entorno.

## Facturas Check - Flujo Actual

1. Usuario sube PDF/imagen.
2. Si es PDF, frontend intenta leer texto seleccionable por pagina con PDF.js.
3. Si no hay texto suficiente, renderiza las primeras paginas como imagen JPEG optimizada.
4. Frontend muestra progreso por fases:
   - preparar archivo
   - leer texto de PDF o renderizar paginas
   - extraer con Gemini
   - subir/guardar
   - comparar con catalogo
5. Solo la fase Gemini consume IA.
6. Backend recibe texto de pagina o imagen optimizada.
   - Si el texto trae lineas tabulares reconocibles, intenta extraerlas por reglas sin IA.
   - Si no hay texto suficiente o no se reconocen lineas, pide JSON estricto a Gemini.
7. Frontend compara lineas extraidas con productos/familias.
8. Se guarda auditoria con PDF original y paginas visuales opcionales.
9. Asistente IA usa contexto de auditorias, alertas y precios.

Para lentillas:

- La graduacion importa y debe leerse bien.
- El precio suele pertenecer a la familia/base del producto, no a cada graduacion.
- `baseProductName` debe ser igual aunque cambie `graduation`.

## RAG / Asistente IA

Estado actual:

- RAG ligero por contexto MySQL y busqueda por terminos.
- Se guardan paginas renderizadas (`facturas_pages`) para fuentes visuales.
- El asistente puede mostrar fuentes visuales asociadas a facturas.
- El backend permite guardar hasta 20 paginas visuales por factura. Si el frontend guarda menos, debe ser por decision de coste/espacio, no por limite API oculto.

Siguiente salto recomendado:

- Guardar texto por pagina cuando sea posible.
- Crear embeddings por pagina o por linea de factura.
- Buscar semanticamente antes de llamar al asistente.
- Devolver respuesta + paginas fuente.

Opcion barata:

- MySQL + FULLTEXT/keywords + embeddings opcionales solo cuando haya muchas facturas.

Opcion pro:

- Microservicio Python/FastAPI estilo Policharger Brain + Pinecone/Gemini embeddings.
- No hacer esto hasta que el modulo actual este validado en tienda.

## Costes IA

Reglas actuales:

- Gemini solo se usa para extraccion inicial y asistente.
- Render PDF, subida, comparacion con catalogo y guardado no gastan tokens.
- PDF se convierte a imagen optimizada para evitar mandar archivos enormes.
- Cuando el PDF tiene texto seleccionable, se envia texto por pagina antes que imagen. Es mas barato, rapido y estable.
- En proveedores con texto tabular tipo Visionis, intentar parseo por reglas antes de Gemini. Coste IA: 0 para la extraccion de lineas.
- La auditoria usa por defecto `0 paginas / Sin IA`. Gemini queda como respaldo manual para PDFs escaneados, imagenes o formatos que el parser local no reconozca.
- Guardar fuentes visuales es independiente de Gemini: renderiza paginas localmente para aportar evidencia visual al asistente. Consume almacenamiento y tiempo, no creditos IA.
- En facturas con columnas `PRECIO`, `DESC. (%)` e `IMPORTE`, el total de factura debe cuadrarse con `IMPORTE`, no con `PRECIO`. `PRECIO` sirve para comparar tarifa/catalogo; `IMPORTE` es lo realmente facturado tras descuentos y bonos.
- La conciliacion fiscal separa subtotal, cuotas de IVA y total. Solo acepta un total final automatico cuando existe un resumen fiscal coherente; no confundir subtotales de albaran con total de factura.
- La auditoria agrupa lineas sin catalogo por producto base/precio y permite crear familias sugeridas en bloque. No crear automaticamente bonos, envios ni importes cero/negativos.
- Se usan primeras paginas para controlar coste y tiempo.
- `generationConfig.temperature = 0` en extraccion.
- `maxOutputTokens` limitado para extraccion/asistente.

Buenas practicas:

- No llamar a Gemini dos veces para la misma factura si ya hay resultado revisable.
- Guardar siempre el resultado y permitir edicion humana.
- No meter la clave Gemini en React/Vite.
- No aceptar automaticamente cambios de precio.

## Errores Ya Cometidos / No Repetir

### Gemini en frontend

Problema:

- El frontend esperaba `VITE_API_KEY`.
- Eso rompe despliegues y expone/mezcla secretos.

Decision:

- Gemini se llama desde `pedidos/api/facturas.php`.
- La clave va en `includes/db_config.php` generado por GitHub Actions.

### Modelo Gemini obsoleto

Problema:

- `gemini-1.5-flash` devolvio error de modelo no soportado.

Decision:

- Usar fallback de modelos en backend.
- Modelo por defecto: `gemini-2.5-flash`.

### PDF enviado directamente a Gemini desde PHP

Problema:

- Algunas facturas PDF provocaban 504 Gateway Timeout en Hostinger.

Decision:

- Renderizar PDF en navegador a imagen JPEG optimizada.
- Mandar imagen a Gemini.
- Guardar PDF original aparte.

### PDF worker `.mjs`

Problema:

- Hostinger servia `pdf.worker-*.mjs` como `text/plain`.
- PDF.js fallaba con "Setting up fake worker failed".

Decision:

- Configurar Vite para emitir assets `.mjs` como `.js`.
- Verificar worker con `curl -I`: debe responder JavaScript.

### Bundle demasiado grande

Problema:

- `pdfjs` dentro del bundle principal hacia fallar/subir lentisimo por FTP.

Decision:

- Cargar `pdfjs-dist` dinamicamente solo al procesar PDFs.
- Mantener el bundle principal lo mas pequeno posible.

### FTP Hostinger

Problemas:

- `lftp mirror` puede fallar o agotar reintentos.
- `mkdir -p` puede devolver error si la carpeta ya existe.
- Bundles grandes pueden superar timeouts.

Decisiones:

- Subida directa archivo a archivo.
- Timeouts FTP mas amplios.
- Evitar bundles innecesariamente grandes.
- Si un deploy esta verde pero la web sirve asset viejo, comprobar `index.html` con `curl`.

### Produccion no actualizada aunque workflow verde

Problema:

- Un deploy incremental subio solo workflow/API y produccion seguia sirviendo un asset viejo.

Decision:

- Tras cambios de Facturas, verificar HTML:
  - Test: `curl -sS https://.../test/facturas/index.html`
  - Prod: `curl -sS https://.../facturas/index.html`
- Confirmar que el asset `index-*.js` es el nuevo.

### Extraccion de facturas con imagen puede cortar JSON

Problema:

- En facturas como Visionis, Gemini podia devolver JSON truncado incluso con modelo correcto y salida compacta.
- Pinecone/RAG no soluciona la primera extraccion; sirve despues, cuando ya hay texto/lineas guardadas.

Decision:

- Intentar primero extraccion de texto del PDF por pagina con PDF.js.
- Intentar parsear lineas tabulares del texto sin IA.
- Mandar a Gemini texto por pagina, no imagen, solo cuando el parser no encuentre lineas.
- Usar imagen solo como fallback cuando el PDF no tenga capa de texto.
- Plantear Pinecone/embeddings despues de estabilizar ingesta y guardado.

### `db_config.php`

Problema:

- Confusion entre `config.php`, ejemplos y `db_config.php`.

Decision:

- Los `.example.php` quedan como plantillas.
- `includes/db_config.php` real lo genera deploy.
- No asumir que existe localmente con secretos correctos.

## Checklist Para Una IA Antes De Tocar Codigo

1. Ejecutar `git status --short`.
2. Leer este archivo.
3. Si toca deploy, leer `deploy/HOSTINGER.md`.
4. Si toca pedidos, leer `pedidos/DOCUMENTACION.md` y revisar patrones existentes.
5. Si toca Facturas, revisar:
   - `facturas-src/pages/AuditPage.tsx`
   - `facturas-src/services/pdfPreview.ts`
   - `facturas-src/services/geminiService.ts`
   - `facturas-src/db.ts`
   - `pedidos/api/facturas.php`
   - `pedidos/sql/facturas_schema.sql`
6. Validar:
   - `npm run build` en `facturas-src`
   - `php -l pedidos/api/facturas.php` si se toca API
   - `php pedidos/tests/invoice_text_parser_test.php` si se toca extraccion de texto
   - `git diff --check`
7. Desplegar primero a `test`.
8. Verificar URLs reales con `curl`.

## Como Actualizar Este Archivo

Actualizar cuando:

- Se anada una app nueva al portal.
- Cambie el flujo de deploy.
- Cambie una tabla/schema importante.
- Se descubra un fallo repetible.
- Se tome una decision tecnica relevante.
- Se cambien modelos IA, costes o estrategia RAG.

No meter secretos, passwords, tokens ni datos privados de clientes.
