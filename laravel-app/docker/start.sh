#!/usr/bin/env sh

set -eu

: "${PORT:=8000}"

php artisan deployment:preflight --no-interaction

exec php artisan serve --host=0.0.0.0 --port="$PORT"
