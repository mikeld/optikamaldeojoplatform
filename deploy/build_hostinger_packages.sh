#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT_DIR/deploy/dist"
TEST_DIR="$OUT_DIR/test-upload"
PROD_DIR="$OUT_DIR/prod-upload"

rm -rf "$OUT_DIR"
mkdir -p "$OUT_DIR"

"$ROOT_DIR/deploy/prepare_hostinger_release.sh" test "$TEST_DIR"
"$ROOT_DIR/deploy/prepare_hostinger_release.sh" prod "$PROD_DIR"

echo "==> Creating upload packages"
(cd "$TEST_DIR" && zip -qr "$OUT_DIR/optikamaldeojo-test-upload.zip" .)
(cd "$PROD_DIR" && zip -qr "$OUT_DIR/optikamaldeojo-prod-upload.zip" .)

echo
echo "Packages ready:"
echo "  $OUT_DIR/optikamaldeojo-test-upload.zip"
echo "  $OUT_DIR/optikamaldeojo-prod-upload.zip"
