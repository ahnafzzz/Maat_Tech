#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT_DIR/dist/cpanel"
STAGE_DIR="$OUT_DIR/staging-static"
STAMP="$(date +%Y%m%d-%H%M%S)"
ZIP_NAME="maat-tech-static-cpanel-$STAMP.zip"

mkdir -p "$STAGE_DIR"
rm -rf "$STAGE_DIR"/*

FILES=(
  "index.html"
  "README.md"
  "USER_GUIDE.md"
  "DOMAIN_HOSTING_GUIDE.md"
)

for file in "${FILES[@]}"; do
  cp "$ROOT_DIR/$file" "$STAGE_DIR/$file"
done

cp -R "$ROOT_DIR/screens" "$STAGE_DIR/screens"

if [ -d "$ROOT_DIR/public" ]; then
  cp -R "$ROOT_DIR/public" "$STAGE_DIR/public"
fi

(
  cd "$STAGE_DIR"
  zip -r "$OUT_DIR/$ZIP_NAME" . >/dev/null
)

echo "cPanel static package created:"
echo "$OUT_DIR/$ZIP_NAME"
echo
echo "Upload this zip in cPanel File Manager, extract in public_html, and verify index.html loads."
