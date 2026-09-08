#!/usr/bin/env sh

set -eu

: "${PORT:=8000}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT must be an integer between 1024 and 65535." >&2
        exit 64
        ;;
esac

if [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT must be an integer between 1024 and 65535." >&2
    exit 64
fi
export PORT

php artisan deployment:preflight --no-interaction
apache2ctl configtest

exec apache2-foreground
