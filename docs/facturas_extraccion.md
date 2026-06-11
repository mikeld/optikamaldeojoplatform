# Extracción de facturas con IA — cómo funciona y cómo afinarla por proveedor

Este documento explica cómo Facturas Check lee las facturas con Gemini, por qué
fallaba con proveedores como Alcon, qué se ha cambiado y cuál es el flujo de
trabajo para afinar la extracción de cada proveedor.

## El pipeline de extracción

Cuando subes una factura en Auditar (`/facturas` → Auditar):

1. **Extracción de texto**: si el PDF tiene capa de texto, se extrae página a
   página con pdf.js en el navegador (gratis, sin IA).
2. **Parser local**: el backend intenta primero un parser de texto plano sin IA.
   Si encuentra líneas, no se llama a Gemini.
3. **Gemini**: si el parser no saca líneas (o la factura es imagen escaneada),
   se envía cada página a Gemini con un prompt + esquema JSON estricto.
4. **Matching con catálogo**: las líneas extraídas se cruzan con productos y
   familias para detectar diferencias de precio y productos nuevos.
5. **Guardar auditoría**: al guardar, la factura queda en `facturas_audits`
   enlazada al proveedor oficial de pedidos via `pedidos_provider_id`.

## Problema 1 — Proveedores duplicados (p. ej. "ALCON HEALTHCARE, S.A.")

**Qué pasaba**: la factura lleva el nombre fiscal completo del emisor
("ALCON HEALTHCARE, S.A.") pero el proveedor oficial en pedidos se llama
"Alcon". El enlace solo se hacía si los nombres coincidían exactamente, así
que la auditoría se guardaba sin `pedidos_provider_id` y Control Mensual lo
mostraba como un proveedor nuevo.

**Qué se ha hecho** (en `pedidos/api/facturas.php`):

- Nueva función `resolverProveedorOficial()`: normaliza ambos nombres
  (minúsculas, sin acentos, sin formas societarias tipo S.A./S.L. ni palabras
  genéricas como "healthcare", "iberia", "distribución") y comprueba si el
  nombre corto está contenido en el largo. "Alcon" ⊂ "alcon healthcare" → match.
- Se aplica en **tres puntos**:
  1. Al extraer (`extractInvoiceData`): la respuesta incluye
     `officialProvider: {id, name}` y el frontend usa ese nombre/ID.
  2. Al guardar (`saveAudit`): si llega sin ID, se resuelve en servidor.
  3. Al listar proveedores (`getProviders`): **repara retroactivamente** las
     auditorías históricas sin enlace — la primera vez que abras la pantalla
     de proveedores, "ALCON HEALTHCARE, S.A." se fusionará con "Alcon".

**Regla práctica**: el nombre del proveedor oficial en pedidos debe ser la
palabra clave de la marca ("Alcon", "Menicon", "Prats"), no el nombre fiscal.
El matching hace el resto.

## Problema 2 — Datos de cabecera extraídos como productos

**Qué pasaba**: en facturas multipágina la cabecera se repite en cada página
(nombre y dirección de la óptica, NIF...) y el modelo a veces lo convertía en
"líneas de producto".

**Qué se ha hecho**: el prompt ahora prohíbe explícitamente extraer como
producto el bloque del destinatario, datos bancarios, condiciones de pago,
pies de página y resúmenes de impuestos. Solo filas reales de la tabla de
artículos (normalmente numeradas).

## Problema 3 — Se perdía la estructura de pedidos de la factura

**Qué pasaba**: facturas como las de Alcon agrupan las líneas por
"Número de pedido: 1132943129 · Su pedido 30/04/2026" e incluyen
"Referencia cliente" (el nombre del paciente). Todo eso se descartaba, y es
justo lo que necesitas para conciliar con el módulo de pedidos.

**Qué se ha hecho**: cada línea extraída ahora puede llevar:

| Campo | Contenido | Ejemplo Alcon |
|-------|-----------|---------------|
| `orderNumber` | Nº de pedido del bloque | 1132943129 |
| `orderDate` | Fecha del pedido (YYYY-MM-DD) | 2026-04-30 |
| `clientRef` | Referencia cliente / paciente | KEIRA MARTIN |

Estos campos se guardan dentro de `lines` en la auditoría y se muestran en la
tabla de revisión bajo cada línea ("Pedido 1132943129 (2026-04-30) · Cliente:
KEIRA MARTIN"). En el futuro permitirán cruzar automáticamente cada línea de
factura con el pedido correspondiente en el módulo de pedidos.

## Problema 4 — Importes: qué número es el bueno

La regla ahora es explícita en el prompt:

- `lt` (total de línea) = **importe NETO** tras descuentos (columna
  "Importe neto" en Alcon), no el precio bruto.
- Las líneas con neto **0,00 €** (reposiciones sin cargo, garantías) **se
  extraen igualmente** con su cantidad real. Antes a veces se perdían, y para
  conciliar unidades con pedidos son imprescindibles.
- Los **portes** ("Portes y servicios") se extraen como línea propia, asociada
  a su bloque de pedido.

## Validación aritmética (anti-alucinaciones)

Tras cada extracción se comprueba: `suma de líneas ≈ total declarado`
(aceptando que la suma sea la base imponible, hasta un 25% por debajo del
total por el IVA). Si no cuadra, en la pantalla de auditoría aparece un aviso
rojo "Aviso de extracción IA" indicando los dos importes. Eso significa que
faltan líneas o hay importes mal leídos: revisa antes de guardar.

## Flujo de trabajo por proveedor (pantalla Proveedores)

La pantalla `/facturas → Proveedores` es el centro de control. Para cada
proveedor puedes guardar **reglas de extracción** en lenguaje natural que se
inyectan en el prompt cuando se detecta ese proveedor (por el selector manual
al subir, o por autodetección del nombre en el texto).

**Cuándo escribir reglas**: cuando un proveedor falla sistemáticamente en algo
concreto. El ciclo es:

1. Audita una factura del proveedor.
2. Mira qué ha hecho mal (líneas que faltan, importes equivocados, columnas
   confundidas).
3. Escribe/añade una regla concreta en Proveedores → editar → Reglas de
   extracción.
4. Vuelve a auditar la misma factura y comprueba.

**Reglas recomendadas para Alcon** (pégalas en Proveedores → Alcon →
Reglas de extracción):

```
Las facturas de Alcon agrupan las líneas por bloques "Número de pedido: XXXXXXXXXX"
con su fecha "Su pedido DD/MM/YYYY". Cada línea de producto va numerada (1), (2)...
Columnas por línea: Producto (SKU), Descripción (nombre + graduación), Cant., UM,
Precio Unitario, Precio Total, Importe Dto., Importe neto, Impuesto %.
- El importe de línea (lt) es SIEMPRE la columna "Importe neto".
- Muchas líneas tienen Importe neto 0,00 (reposiciones sin cargo): extraerlas igual
  con su cantidad.
- Las líneas de descuento ("-25,99 % Cust/Mat Discount % -7,25") NO son productos:
  ya están reflejadas en el Importe neto de la línea anterior.
- "Referencia cliente NOMBRE" debajo de cada línea es el paciente: va en cr.
- "Referencia producto" y "País de origen" se ignoran.
- "Portes y servicios" al final de cada bloque de pedido es una línea propia.
- El nombre base (b) es la familia de la lente sin graduación: TOTAL30 SPHERE,
  TOTAL30 TOR, PRECISION1, PRECSION1 TOR, DLY TOTAL 1 MFOCAL, AIROPT AQ HG SPH,
  AIROPT ASTG HG, TOTAL30 MULTIFOCAL.
```

## Resumen de archivos tocados

| Archivo | Cambio |
|---------|--------|
| `pedidos/api/facturas.php` | `resolverProveedorOficial()`, prompt y esquema con o/od/cr, validación, reparación retroactiva de enlaces |
| `facturas-src/types.ts` | `InvoiceItem`/`AuditLine` con orderNumber/orderDate/clientRef, `InvoiceData` con officialProvider y validation |
| `facturas-src/pages/AuditPage.tsx` | Usa proveedor oficial, propaga campos de pedido a líneas, banner de validación, recálculo de validación en multipágina |
