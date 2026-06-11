# Despliegue en Hostinger

Antes de cambiar deploy o entornos, leer tambien `../AI_CONTEXT.md`.

## Flujo recomendado

Usar dos ramas:

- `test`: cada push despliega en `public_html/test`
- `prod`: cada push despliega en `public_html`

Asi el flujo normal es:

```bash
git checkout test
git merge codex/portal-roles-facturas
git push origin test
```

Pruebas en `/test`. Si todo va bien:

```bash
git checkout prod
git merge test
git push origin prod
```

## Estructura en Hostinger

Produccion:

```text
public_html/
  assets/
  facturas/
  includes/
  pedidos/
  home.php
  index.php
  logout.php
```

Test:

```text
public_html/test/
  assets/
  facturas/
  includes/
  pedidos/
  home.php
  index.php
  logout.php
```

## Secretos de GitHub

En GitHub:

```text
Settings > Secrets and variables > Actions > New repository secret
```

Secretos necesarios:

```text
TEST_DB_USER
TEST_DB_PASS
PROD_DB_USER
PROD_DB_PASS
GEMINI_API_KEY
DEPLOY_UPLOAD_TOKEN   ← nuevo, ver sección "Deploy via HTTP" abajo
```

Los secretos FTP ya no son necesarios (deploy via HTTP).

## Deploy via HTTP (sin FTP)

El deploy usa HTTP en vez de FTP porque GitHub Actions tiene el puerto 21
bloqueado hacia Hostinger de forma sistemática.

### Paso 1 — subir upload_receiver.php al servidor (una sola vez)

Sube manualmente `deploy/upload_receiver.php` via el gestor de archivos de Hostinger (hPanel):

```
public_html/upload_receiver.php       ← producción
public_html/test/upload_receiver.php  ← test
```

Edita cada copia y reemplaza la línea:

```php
$secret = getenv('DEPLOY_UPLOAD_TOKEN') ?: 'REPLACE_WITH_STATIC_TOKEN';
```

por el token que generaste (ver paso 2). Hostinger shared hosting no pasa
variables de entorno a PHP, así que el token va hardcodeado en el archivo del servidor.

### Paso 2 — crear secreto DEPLOY_UPLOAD_TOKEN (una sola vez)

```bash
openssl rand -hex 32
```

Guarda ese valor como secreto de GitHub `DEPLOY_UPLOAD_TOKEN` Y ponlo en
`upload_receiver.php` en el servidor (paso 1).

### Seguridad

- Solo acepta POST con token correcto y extensiones `.zip` / `.php`
- `unzip.php` usa token de un solo uso y se auto-elimina tras extraer
- `upload_receiver.php` permanece en el servidor entre deploys (no se auto-elimina)

Valores esperados:

```text
HOSTINGER_TEST_DIR=/public_html/test
HOSTINGER_PROD_DIR=/public_html
```

Si tu FTP entra ya directamente en `public_html`, usa:

```text
HOSTINGER_TEST_DIR=/test
HOSTINGER_PROD_DIR=/
```

## Que hace GitHub Actions

Los workflows estan en:

```text
.github/workflows/deploy-test.yml
.github/workflows/deploy-prod.yml
```

Cada despliegue:

1. Descarga el codigo.
2. Instala dependencias de `facturas-src`.
3. Compila Facturas Check.
4. Prepara una carpeta final sin archivos de desarrollo.
5. Crea `includes/db_config.php` con secretos del entorno.
6. Sube todo a Hostinger por FTP.

La subida FTP usa pocos paralelos y reintentos amplios porque Hostinger puede cortar conexiones de forma puntual. Si un deploy falla solo en el paso FTP y el build ha pasado, normalmente basta con relanzar el workflow.

El servidor no recibe:

- `facturas-src`
- `node_modules`
- `deploy`
- `.github`
- archivos `*.example.php`
- `database.db`
- `php_errorlog`

## Bases de datos

Test:

```text
DB_NAME=u373487989_maldeojotest
```

Produccion:

```text
DB_NAME=u373487989_maldeojo
```

El usuario y password salen de secretos:

```text
TEST_DB_USER / TEST_DB_PASS
PROD_DB_USER / PROD_DB_PASS
```

## Gemini

Facturas Check usa Gemini para extraer facturas y para el Asistente IA. La clave se escribe en
`includes/db_config.php` durante el deploy y la usa el backend PHP; no debe ir en el frontend.

Si quieres activar esas funciones en test/prod, crea este secreto de GitHub:

```text
GEMINI_API_KEY
```

Si no existe, el despliegue seguira funcionando, pero las acciones de IA mostraran un aviso de configuracion pendiente.

## Despliegue manual de respaldo

Si algun dia quieres generar zips manuales:

```bash
chmod +x deploy/*.sh
./deploy/build_hostinger_packages.sh
```

Genera:

```text
deploy/dist/optikamaldeojo-test-upload.zip
deploy/dist/optikamaldeojo-prod-upload.zip
```

Estos zips ya incluyen `includes/db_config.php` con la BBDD correcta y placeholders de usuario/password.

## SQL

El despliegue sube archivos, pero no ejecuta SQL automaticamente.

Cuando haya cambios de base de datos:

1. Ejecuta primero el SQL en `u373487989_maldeojotest`.
2. Prueba `/test`.
3. Si va bien, ejecuta el SQL en `u373487989_maldeojo`.

Para facturas:

```text
pedidos/sql/facturas_schema.sql
```

## Checklist despues de desplegar en test

- `/test/index.php` carga.
- Empleado ve solo Pedidos.
- Admin/encargado ve Pedidos, Facturas Check y Panel de Direccion.
- Facturas Check carga en `/test/facturas`.
- Facturas Check llama a `/test/pedidos/api/facturas.php`.
- Pedidos usa la BBDD `u373487989_maldeojotest`.
