#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/laravel-app"

echo "[1/5] Checking Laravel app directory..."
if [ ! -d "$APP_DIR" ]; then
  echo "laravel-app directory not found."
  exit 1
fi

cd "$APP_DIR"

echo "[2/5] Installing production PHP dependencies only..."
composer install --no-dev --prefer-dist --classmap-authoritative --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev

echo "[3/5] Installing frontend dependencies..."
npm ci

echo "[4/5] Building frontend assets..."
npm run build
test -f public/build/manifest.json

echo "[5/5] Package preparation complete"
echo "- Ensure document root points to laravel-app/public"
echo "- This command does not create .env, generate APP_KEY, seed, or migrate"
echo "- Follow deployment/DEPLOYMENT_RUNBOOK.md for first installation or routine deployment"
