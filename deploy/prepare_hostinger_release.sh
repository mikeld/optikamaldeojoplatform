#!/usr/bin/env bash
set -euo pipefail

APP_ENV="${1:-}"
RELEASE_DIR="${2:-}"

if [[ "$APP_ENV" != "test" && "$APP_ENV" != "prod" ]]; then
  echo "Usage: $0 <test|prod> <release_dir>" >&2
  exit 1
fi

if [[ -z "$RELEASE_DIR" ]]; then
  echo "Missing release_dir" >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

case "$APP_ENV" in
  test)
    DEFAULT_DB_NAME="u373487989_maldeojotest"
    DEFAULT_DB_USER="TU_USUARIO_TEST"
    DEFAULT_DB_PASS="TU_PASSWORD_TEST"
    ;;
  prod)
    DEFAULT_DB_NAME="u373487989_maldeojo"
    DEFAULT_DB_USER="TU_USUARIO_PROD"
    DEFAULT_DB_PASS="TU_PASSWORD_PROD"
    ;;
esac

DB_HOST_VALUE="${DB_HOST:-localhost}"
DB_NAME_VALUE="${DB_NAME:-$DEFAULT_DB_NAME}"
DB_USER_VALUE="${DB_USER:-$DEFAULT_DB_USER}"
DB_PASS_VALUE="${DB_PASS:-$DEFAULT_DB_PASS}"
DB_CHARSET_VALUE="${DB_CHARSET:-utf8mb4}"
GEMINI_API_KEY_VALUE="${GEMINI_API_KEY:-}"
GEMINI_MODEL_VALUE="${GEMINI_MODEL:-gemini-2.5-flash}"

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

echo "==> Building Facturas Check"
if [[ "${CI:-}" == "true" ]]; then
  (cd "$ROOT_DIR/facturas-src" && npm ci --cache "$ROOT_DIR/.npm-cache" && npm run build)
elif [[ -d "$ROOT_DIR/facturas-src/node_modules" ]]; then
  (cd "$ROOT_DIR/facturas-src" && npm run build)
else
  (cd "$ROOT_DIR/facturas-src" && npm install --cache "$ROOT_DIR/.npm-cache" && npm run build)
fi

echo "==> Copying portal files"
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
  "$RELEASE_DIR/"

echo "==> Copying compiled Facturas Check"
rm -rf "$RELEASE_DIR/facturas"
mkdir -p "$RELEASE_DIR/facturas"
rsync -a "$ROOT_DIR/facturas-src/dist/" "$RELEASE_DIR/facturas/"

echo "==> Writing includes/db_config.php for $APP_ENV"
cat > "$RELEASE_DIR/includes/db_config.php" <<EOF
<?php
define('DB_HOST',    '$DB_HOST_VALUE');
define('DB_NAME',    '$DB_NAME_VALUE');
define('DB_USER',    '$DB_USER_VALUE');
define('DB_PASS',    '$DB_PASS_VALUE');
define('DB_CHARSET', '$DB_CHARSET_VALUE');
define('GEMINI_API_KEY', '$GEMINI_API_KEY_VALUE');
define('GEMINI_MODEL', '$GEMINI_MODEL_VALUE');
EOF

echo "Release ready at $RELEASE_DIR"
