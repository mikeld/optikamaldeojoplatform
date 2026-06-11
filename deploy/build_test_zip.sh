#!/usr/bin/env bash
# Script para empaquetar la versión de prueba (Test) excluyendo db_config.php
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT_DIR/deploy/dist"
PREP_DIR="$OUT_DIR/test-zip-prep"

echo "==> Limpiando directorios anteriores..."
rm -rf "$PREP_DIR"
mkdir -p "$PREP_DIR"

echo "==> Compilando el frontend React de Facturas Check..."
(cd "$ROOT_DIR/facturas-src" && npm run build)

echo "==> Preparando archivos del portal..."
mkdir -p "$PREP_DIR"
rsync -a \
  --exclude 'db_config.php' \
  --exclude 'db_config*.example.php' \
  --exclude '.DS_Store' \
  --exclude 'php_errorlog' \
  --exclude 'database.db' \
  "$ROOT_DIR/assets" \
  "$ROOT_DIR/includes" \
  "$ROOT_DIR/pedidos" \
  "$ROOT_DIR/home.php" \
  "$ROOT_DIR/index.php" \
  "$ROOT_DIR/logout.php" \
  "$ROOT_DIR/debug.php" \
  "$ROOT_DIR/manifest.json" \
  "$ROOT_DIR/offline.html" \
  "$ROOT_DIR/sw.js" \
  "$PREP_DIR/"

echo "==> Copiando frontend de Facturas Check compilado..."
mkdir -p "$PREP_DIR/facturas"
rsync -a "$ROOT_DIR/facturas-src/dist/" "$PREP_DIR/facturas/"

echo "==> Creando paquete ZIP (optikamaldeojo-test-upload.zip)..."
(cd "$PREP_DIR" && zip -qr "$OUT_DIR/optikamaldeojo-test-upload.zip" .)

echo ""
echo "¡Paquete listo para subir y descomprimir en Hostinger (/test)!"
echo "Ubicación del archivo: $OUT_DIR/optikamaldeojo-test-upload.zip"
echo "Nota: Este paquete NO contiene 'includes/db_config.php', por lo que no sobrescribirá tus contraseñas del servidor."
