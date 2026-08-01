#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/laravel-app"

echo "[1/7] Checking Laravel app directory..."
if [ ! -d "$APP_DIR" ]; then
  echo "laravel-app directory not found."
  exit 1
fi

cd "$APP_DIR"

echo "[2/7] Installing production PHP dependencies only..."
composer install --no-dev --prefer-dist --classmap-authoritative --optimize-autoloader

echo "[3/7] Preparing low-resource environment file (if missing)..."
if [ ! -f .env ]; then
  cp .env.low-resource.example .env
  echo "Created .env from .env.low-resource.example"
fi

echo "[4/7] Generating app key if missing..."
php artisan key:generate --force

echo "[5/7] Running migrations..."
php artisan migrate --force

echo "[6/7] Caching framework metadata for faster requests..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[7/7] Final reminders"
echo "- Ensure document root points to laravel-app/public"
echo "- Keep QUEUE_CONNECTION=sync for 1GB shared hosting"
echo "- Keep CACHE_STORE=file and SESSION_DRIVER=file"

echo "Low-resource deployment prep complete."
