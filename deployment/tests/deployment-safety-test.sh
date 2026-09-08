#!/usr/bin/env bash

set -euo pipefail

REPOSITORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(mktemp -d)"

cleanup() {
  case "$TEMP_ROOT" in
    /tmp/*) rm -rf -- "$TEMP_ROOT" ;;
  esac
}
trap cleanup EXIT

fail() {
  echo "FAIL: $1" >&2
  exit 1
}

make_fixture() {
  fixture="$1"
  SOURCE_ROOT="$TEMP_ROOT/$fixture/source"
  TARGET_ROOT="$TEMP_ROOT/$fixture/target"
  BACKUP_ROOT="$TEMP_ROOT/$fixture/backups"
  BIN_ROOT="$TEMP_ROOT/$fixture/bin"
  TEST_LOG="$TEMP_ROOT/$fixture/commands.log"
  TEST_STATE="$TEMP_ROOT/$fixture/state"
  mkdir -p "$SOURCE_ROOT/laravel-app/vendor" "$SOURCE_ROOT/laravel-app/public/build" "$SOURCE_ROOT/laravel-app/database" "$SOURCE_ROOT/laravel-app/bootstrap/cache" "$SOURCE_ROOT/deployment"
  mkdir -p "$TARGET_ROOT/laravel-app/storage/app/public" "$TARGET_ROOT/laravel-app/storage/framework" "$TARGET_ROOT/laravel-app/storage/logs"
  mkdir -p "$TARGET_ROOT/laravel-app/database" "$TARGET_ROOT/laravel-app/public" "$TARGET_ROOT/laravel-app/bootstrap/cache"
  mkdir -p "$BIN_ROOT" "$TEST_STATE"
  cp "$REPOSITORY_ROOT/deployment/sync-laravel-release.sh" "$SOURCE_ROOT/deployment/sync-laravel-release.sh"
  printf '%s\n' '<?php' > "$SOURCE_ROOT/laravel-app/artisan"
  printf '%s\n' '<?php' > "$SOURCE_ROOT/laravel-app/vendor/autoload.php"
  printf '%s\n' '*' > "$SOURCE_ROOT/laravel-app/bootstrap/cache/.gitignore"
  printf '%s\n' '{"release":"new"}' > "$SOURCE_ROOT/laravel-app/public/build/manifest.json"
  printf '%s\n' 'lock-new' > "$SOURCE_ROOT/laravel-app/composer.lock"
  printf '%s\n' 'new-code' > "$SOURCE_ROOT/laravel-app/application.txt"
  printf '%s\n' 'APP_KEY=stable-test-key' > "$TARGET_ROOT/laravel-app/.env"
  printf '%s\n' 'upload-data' > "$TARGET_ROOT/laravel-app/storage/app/public/upload.bin"
  printf '%s\n' 'database-data' > "$TARGET_ROOT/laravel-app/database/production.sqlite"
  printf '%s\n' 'wal-data' > "$TARGET_ROOT/laravel-app/database/production.sqlite-wal"
  printf '%s\n' 'shm-data' > "$TARGET_ROOT/laravel-app/database/production.sqlite-shm"
  printf '%s\n' 'journal-data' > "$TARGET_ROOT/laravel-app/database/production.sqlite-journal"
  printf '%s\n' 'old-code' > "$TARGET_ROOT/laravel-app/application.txt"
  printf '%s\n' 'remove-me' > "$TARGET_ROOT/obsolete.txt"
  ln -s ../storage/app/public "$TARGET_ROOT/laravel-app/public/storage"
  cp "$SOURCE_ROOT/laravel-app/artisan" "$TARGET_ROOT/laravel-app/artisan"

  cat > "$BIN_ROOT/php" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$DEPLOY_TEST_LOG"
case "$*" in
  *"deployment:preflight"*)
    if [ "${FAIL_PREFLIGHT:-0}" = 1 ]; then exit 1; fi
    ;;
  *"deployment:backup"*)
    if [ "${FAIL_BACKUP:-0}" = 1 ]; then exit 1; fi
    previous=""
    for argument in "$@"; do
      if [ "$previous" = "deployment:backup" ]; then
        mkdir -p "$argument"
        printf '%s\n' 'verified-backup' > "$argument/database.mock"
        break
      fi
      previous="$argument"
    done
    ;;
  *"migrate --force"*)
    if [ "${FAIL_MIGRATION:-0}" = 1 ]; then exit 1; fi
    ;;
  *"artisan down"*) touch "$DEPLOY_TEST_STATE/maintenance" ;;
  *"artisan up"*) rm -f "$DEPLOY_TEST_STATE/maintenance" ;;
esac
MOCK
  cat > "$BIN_ROOT/composer" <<'MOCK'
#!/usr/bin/env sh
exit 0
MOCK
  cat > "$BIN_ROOT/curl" <<'MOCK'
#!/usr/bin/env sh
[ "${FAIL_HEALTH:-0}" = 1 ] && exit 22
exit 0
MOCK
  cat > "$BIN_ROOT/quiesce" <<'MOCK'
#!/usr/bin/env sh
printf '%s\n' quiesce >> "$DEPLOY_TEST_LOG"
exit 0
MOCK
  cat > "$BIN_ROOT/resume" <<'MOCK'
#!/usr/bin/env sh
printf '%s\n' resume >> "$DEPLOY_TEST_LOG"
exit 0
MOCK
  chmod 0755 "$BIN_ROOT/php" "$BIN_ROOT/composer" "$BIN_ROOT/curl" "$BIN_ROOT/quiesce" "$BIN_ROOT/resume"
  export DEPLOY_TEST_LOG="$TEST_LOG" DEPLOY_TEST_STATE="$TEST_STATE"
  export DEPLOY_PHP_BIN="$BIN_ROOT/php" DEPLOY_COMPOSER_BIN="$BIN_ROOT/composer" DEPLOY_CURL_BIN="$BIN_ROOT/curl"
}

run_deploy() {
  "$REPOSITORY_ROOT/deployment/deploy-laravel.sh" \
    "$SOURCE_ROOT" "$TARGET_ROOT" "$BACKUP_ROOT" "https://health.example.test/up" \
    "$BIN_ROOT/quiesce" "$BIN_ROOT/resume" "$1"
}

assert_persistent_data() {
  [ "$(cat "$TARGET_ROOT/laravel-app/.env")" = 'APP_KEY=stable-test-key' ] || fail '.env or APP_KEY changed'
  [ "$(cat "$TARGET_ROOT/laravel-app/storage/app/public/upload.bin")" = 'upload-data' ] || fail 'upload changed'
  [ "$(cat "$TARGET_ROOT/laravel-app/database/production.sqlite")" = 'database-data' ] || fail 'SQLite database changed'
  [ "$(cat "$TARGET_ROOT/laravel-app/database/production.sqlite-wal")" = 'wal-data' ] || fail 'SQLite WAL changed'
  [ "$(cat "$TARGET_ROOT/laravel-app/database/production.sqlite-shm")" = 'shm-data' ] || fail 'SQLite SHM changed'
  [ "$(cat "$TARGET_ROOT/laravel-app/database/production.sqlite-journal")" = 'journal-data' ] || fail 'SQLite journal changed'
}

make_fixture success
run_deploy release-one
assert_persistent_data
[ ! -e "$TARGET_ROOT/obsolete.txt" ] || fail 'obsolete application file survived --delete'
[ "$(cat "$TARGET_ROOT/laravel-app/application.txt")" = 'new-code' ] || fail 'application code was not updated'
printf '%s\n' 'newer-code' > "$SOURCE_ROOT/laravel-app/application.txt"
run_deploy release-two
assert_persistent_data
[ "$(cat "$TARGET_ROOT/laravel-app/application.txt")" = 'newer-code' ] || fail 'repeat deployment did not update code'

make_fixture missing-config
rm "$TARGET_ROOT/laravel-app/.env"
if run_deploy missing-config; then fail 'missing production configuration was accepted'; fi

make_fixture backup-failure
if FAIL_BACKUP=1 run_deploy backup-failure; then fail 'failed backup was accepted'; fi
[ ! -e "$TEST_STATE/maintenance" ] || fail 'backup failure left unchanged application in maintenance'
[ "$(cat "$TARGET_ROOT/laravel-app/application.txt")" = 'old-code' ] || fail 'backup failure replaced application code'

make_fixture migration-failure
if FAIL_MIGRATION=1 run_deploy migration-failure; then fail 'failed migration was accepted'; fi
[ -e "$TEST_STATE/maintenance" ] || fail 'migration failure did not retain maintenance mode'

make_fixture health-failure
if FAIL_HEALTH=1 run_deploy health-failure; then fail 'failed health check was accepted'; fi
[ -e "$TEST_STATE/maintenance" ] || fail 'health failure did not restore maintenance mode'

echo "Deployment safety fixture tests passed."
