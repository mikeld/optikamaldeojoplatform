# Despliegue en Hostinger

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

Crea estos secretos:

```text
HOSTINGER_FTP_HOST
HOSTINGER_FTP_USER
HOSTINGER_FTP_PASSWORD
HOSTINGER_TEST_DIR
HOSTINGER_PROD_DIR
TEST_DB_USER
TEST_DB_PASS
PROD_DB_USER
PROD_DB_PASS
```

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
