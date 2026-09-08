#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 7 ]; then
  echo "Usage: deploy-laravel.sh SOURCE_ROOT TARGET_ROOT BACKUP_ROOT HEALTH_URL QUIESCE_HOOK RESUME_HOOK RELEASE_SHA" >&2
  exit 64
fi

SOURCE_ROOT="${1%/}"
TARGET_ROOT="${2%/}"
BACKUP_ROOT="${3%/}"
HEALTH_URL="$4"
QUIESCE_HOOK="$5"
RESUME_HOOK="$6"
RELEASE_SHA="$7"
SOURCE_APP="$SOURCE_ROOT/laravel-app"
TARGET_APP="$TARGET_ROOT/laravel-app"
PHP_BIN="${DEPLOY_PHP_BIN:-php}"
COMPOSER_BIN="${DEPLOY_COMPOSER_BIN:-composer}"
CURL_BIN="${DEPLOY_CURL_BIN:-curl}"
TAR_BIN="${DEPLOY_TAR_BIN:-tar}"
PHASE="initial validation"

cleanup() {
  status=$?

  if [ -L "$SOURCE_APP/.env" ] && [ "$(readlink "$SOURCE_APP/.env")" = "$TARGET_APP/.env" ]; then
    unlink "$SOURCE_APP/.env"
  fi

  if [ "$status" -ne 0 ]; then
    echo "Deployment failed during: $PHASE" >&2
  fi
}
trap cleanup EXIT

for path in "$SOURCE_ROOT" "$TARGET_ROOT" "$BACKUP_ROOT"; do
  case "$path" in
    /*) ;;
    *) echo "Deployment paths must be absolute." >&2; exit 1 ;;
  esac
done

for hook in "$QUIESCE_HOOK" "$RESUME_HOOK"; do
  case "$hook" in
    /*) ;;
    *) echo "Deployment hook paths must be absolute." >&2; exit 1 ;;
  esac
done

case "$HEALTH_URL" in
  http://*|https://*) ;;
  *) echo "Health check URL must use HTTP or HTTPS." >&2; exit 1 ;;
esac

case "$RELEASE_SHA" in
  ''|*[!A-Za-z0-9._-]*) echo "Release identifier contains unsafe characters." >&2; exit 1 ;;
esac

if [ "$SOURCE_ROOT" = "$TARGET_ROOT" ] || [ "$TARGET_ROOT" = "/" ]; then
  echo "Refusing unsafe deployment paths." >&2
  exit 1
fi

case "$BACKUP_ROOT/" in
  "$SOURCE_ROOT/"*|"$TARGET_ROOT/"*) echo "Backup root must be outside source and target trees." >&2; exit 1 ;;
esac

if [ ! -f "$TARGET_APP/.env" ]; then
  echo "Existing production configuration is required; follow first-install initialization." >&2
  exit 1
fi

if [ ! -f "$SOURCE_APP/artisan" ] || [ ! -f "$SOURCE_APP/vendor/autoload.php" ] || [ ! -f "$SOURCE_APP/public/build/manifest.json" ]; then
  echo "Prepared release is missing Laravel dependencies or built frontend assets." >&2
  exit 1
fi

for runtime_path in "$TARGET_APP/storage/app/public" "$TARGET_APP/storage/framework" "$TARGET_APP/storage/logs" "$TARGET_APP/bootstrap/cache"; do
  if [ ! -d "$runtime_path" ] || [ ! -w "$runtime_path" ]; then
    echo "Live runtime directories must exist and be writable before deployment." >&2
    exit 1
  fi
done

if [ ! -L "$TARGET_APP/public/storage" ] || [ ! -d "$TARGET_APP/public/storage" ]; then
  echo "The live public storage link is missing or invalid." >&2
  exit 1
fi

if [ ! -x "$QUIESCE_HOOK" ] || [ ! -x "$RESUME_HOOK" ]; then
  echo "Configured queue quiesce and resume hooks must be executable." >&2
  exit 1
fi

for command in "$PHP_BIN" "$COMPOSER_BIN" "$CURL_BIN" "$TAR_BIN" flock rsync; do
  command -v "$command" >/dev/null || { echo "Required deployment tool is unavailable." >&2; exit 1; }
done

mkdir -p "$BACKUP_ROOT"
chmod 0700 "$BACKUP_ROOT"

exec 9>"${TARGET_ROOT}.deploy.lock"
if ! flock -n 9; then
  echo "Another deployment holds the environment lock." >&2
  exit 1
fi

ln -s "$TARGET_APP/.env" "$SOURCE_APP/.env"

PHASE="release and production preflight"
(cd "$SOURCE_APP" && "$COMPOSER_BIN" check-platform-reqs --no-dev)
(cd "$SOURCE_APP" && "$PHP_BIN" artisan deployment:preflight --no-interaction)

PHASE="maintenance mode"
(cd "$TARGET_APP" && "$PHP_BIN" artisan down --retry=60 --no-interaction)

PHASE="queue quiescence"
if ! "$QUIESCE_HOOK"; then
  (cd "$TARGET_APP" && "$PHP_BIN" artisan up --no-interaction) || true
  exit 1
fi

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DEPLOY_BACKUP="$BACKUP_ROOT/$STAMP-$RELEASE_SHA"

PHASE="database backup"
if ! (cd "$SOURCE_APP" && "$PHP_BIN" artisan deployment:backup "$DEPLOY_BACKUP/database" --no-interaction); then
  "$RESUME_HOOK" || true
  (cd "$TARGET_APP" && "$PHP_BIN" artisan up --no-interaction) || true
  exit 1
fi

PHASE="application code snapshot"
if ! "$TAR_BIN" -C "$TARGET_ROOT" \
  --exclude='./.git' \
  --exclude='./laravel-app/.env' \
  --exclude='./laravel-app/storage' \
  --exclude='./laravel-app/database/*.sqlite*' \
  --exclude='./laravel-app/public/storage' \
  -czf "$DEPLOY_BACKUP/application-code.tar.gz" .; then
  "$RESUME_HOOK" || true
  (cd "$TARGET_APP" && "$PHP_BIN" artisan up --no-interaction) || true
  exit 1
fi
"$TAR_BIN" -tzf "$DEPLOY_BACKUP/application-code.tar.gz" >/dev/null

PHASE="application synchronization"
"$SOURCE_ROOT/deployment/sync-laravel-release.sh" "$SOURCE_ROOT" "$TARGET_ROOT"
cmp "$SOURCE_APP/composer.lock" "$TARGET_APP/composer.lock"
cmp "$SOURCE_APP/public/build/manifest.json" "$TARGET_APP/public/build/manifest.json"

PHASE="database migration"
(cd "$TARGET_APP" && "$PHP_BIN" artisan migrate --force --no-interaction)

PHASE="application cache rebuild"
(cd "$TARGET_APP" && "$PHP_BIN" artisan optimize:clear --no-interaction)
(cd "$TARGET_APP" && "$PHP_BIN" artisan config:cache --no-interaction)
(cd "$TARGET_APP" && "$PHP_BIN" artisan route:cache --no-interaction)
(cd "$TARGET_APP" && "$PHP_BIN" artisan view:cache --no-interaction)
(cd "$TARGET_APP" && "$PHP_BIN" artisan queue:restart --no-interaction)
(cd "$TARGET_APP" && "$PHP_BIN" artisan deployment:preflight --no-interaction)

PHASE="bounded health check"
(cd "$TARGET_APP" && "$PHP_BIN" artisan up --no-interaction)
if ! "$CURL_BIN" --silent --show-error --fail --max-time 10 --retry 4 --retry-delay 2 --retry-all-errors "$HEALTH_URL" >/dev/null; then
  (cd "$TARGET_APP" && "$PHP_BIN" artisan down --retry=60 --no-interaction) || true
  exit 1
fi

PHASE="queue resume"
if ! "$RESUME_HOOK"; then
  (cd "$TARGET_APP" && "$PHP_BIN" artisan down --retry=60 --no-interaction) || true
  exit 1
fi

PHASE="complete"
echo "Deployment completed after verified backup and health check."
