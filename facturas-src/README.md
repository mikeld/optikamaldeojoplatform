# Facturas Check

Frontend React/Vite del modulo de auditoria de facturas de Optikamaldeojo.

Antes de tocar este modulo, leer:

- `../AI_CONTEXT.md`
- `../deploy/HOSTINGER.md`
- `../pedidos/sql/facturas_schema.sql`
- `../pedidos/api/facturas.php`

## Arquitectura

- React + Vite en `facturas-src/`.
- El build se copia a `facturas/` durante el deploy.
- La API vive en `../pedidos/api/facturas.php`.
- Gemini se llama desde PHP, no desde el frontend.
- PDF.js se carga bajo demanda para renderizar PDFs.

## Scripts

```bash
npm install
npm run build
```

## Reglas Importantes

- No usar `VITE_API_KEY` para Gemini en produccion.
- No meter claves IA en el bundle frontend.
- No enviar PDF pesado directamente a Gemini si puede renderizarse a imagen optimizada.
- Mantener el worker PDF servido como `.js`, no `.mjs`, porque Hostinger puede servir `.mjs` como `text/plain`.
- Mantener `pdfjs-dist` con import dinamico para evitar bundles grandes y problemas FTP.

## Flujo De Auditoria

1. Usuario sube PDF o imagen.
2. PDF se renderiza en navegador a JPEG optimizado.
3. Solo la imagen optimizada se manda a Gemini para extraer JSON.
4. El PDF original y paginas renderizadas se guardan en servidor.
5. El frontend compara lineas con catalogo/familias.
6. El usuario revisa y guarda auditoria.

## Coste IA

Solo consume IA:

- Extraccion inicial con Gemini.
- Preguntas del asistente IA.

No consume IA:

- Render PDF.
- Subida del archivo.
- Guardado de paginas.
- Comparacion con catalogo.
- Validacion local de precios/familias.
