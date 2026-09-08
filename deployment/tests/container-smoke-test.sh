#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "Usage: container-smoke-test.sh IMAGE" >&2
  exit 64
fi

IMAGE="$1"
SUFFIX="${GITHUB_RUN_ID:-local}-$$-${RANDOM}"
STORAGE_VOLUME="maat-ci-storage-$SUFFIX"
DATA_VOLUME="maat-ci-data-$SUFFIX"
UNINITIALIZED_VOLUME="maat-ci-uninitialized-$SUFFIX"
CONTAINER="maat-ci-app-$SUFFIX"
CONTAINER_PORT=8080

cleanup() {
  docker rm --force "$CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$STORAGE_VOLUME" "$DATA_VOLUME" "$UNINITIALIZED_VOLUME" >/dev/null 2>&1 || true
}
trap cleanup EXIT

for command in docker curl openssl timeout; do
  command -v "$command" >/dev/null || {
    echo "Required container smoke-test tool is unavailable: $command" >&2
    exit 2
  }
done

APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"

docker image inspect "$IMAGE" >/dev/null
[ "$(docker image inspect --format '{{.Config.User}}' "$IMAGE")" = "www-data" ]
[ "$(docker image inspect --format '{{.Config.StopSignal}}' "$IMAGE")" = "SIGWINCH" ]
docker image inspect --format '{{json .Config.Healthcheck.Test}}' "$IMAGE" | grep -q '/up'
docker volume create "$STORAGE_VOLUME" >/dev/null
docker volume create "$DATA_VOLUME" >/dev/null
docker volume create "$UNINITIALIZED_VOLUME" >/dev/null

ENVIRONMENT=(
  --env APP_ENV=production
  --env APP_DEBUG=false
  --env APP_URL=https://container.example.test
  --env "APP_KEY=$APP_KEY"
  --env PORT="$CONTAINER_PORT"
  --env DB_CONNECTION=sqlite
  --env DB_DATABASE=/data/application.sqlite
  --env DB_URL=
  --env CACHE_STORE=file
  --env SESSION_DRIVER=file
  --env SESSION_SECURE_COOKIE=true
  --env TRUSTED_PROXIES=
  --env QUEUE_CONNECTION=sync
)

# Startup must reject an uninitialized database without migrating or seeding it.
if docker run --rm "${ENVIRONMENT[@]}" \
  --env DB_DATABASE=/data/uninitialized.sqlite \
  --volume "$STORAGE_VOLUME:/app/storage" \
  --volume "$UNINITIALIZED_VOLUME:/data" \
  "$IMAGE" >/dev/null 2>&1; then
  echo "Container unexpectedly started with an uninitialized database." >&2
  exit 1
fi
docker run --rm --volume "$UNINITIALIZED_VOLUME:/data" --entrypoint php "$IMAGE" -r \
  '$path = "/data/uninitialized.sqlite"; if (! is_file($path)) { exit(0); } $pdo = new PDO("sqlite:$path"); $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = \"table\"")->fetchAll(PDO::FETCH_COLUMN); exit(in_array("migrations", $tables, true) ? 1 : 0);'
VOLUMES=(
  --volume "$STORAGE_VOLUME:/app/storage"
  --volume "$DATA_VOLUME:/data"
)

# Database provisioning is an explicit setup operation, not container startup.
docker run --rm "${ENVIRONMENT[@]}" "${VOLUMES[@]}" --entrypoint php "$IMAGE" artisan migrate --force --no-interaction
docker run --rm "${ENVIRONMENT[@]}" "${VOLUMES[@]}" --entrypoint php "$IMAGE" -r \
  '$pdo = new PDO("sqlite:/data/application.sqlite"); $pdo->exec("CREATE TABLE ci_persistence (value TEXT NOT NULL)"); $pdo->exec("INSERT INTO ci_persistence VALUES (\"database-persists\")");'
docker run --rm "${VOLUMES[@]}" --entrypoint sh "$IMAGE" -c \
  'printf "%s\n" "upload-persists" > /app/storage/app/public/ci-persistence.txt'
docker run --rm "${VOLUMES[@]}" --entrypoint sh "$IMAGE" -c \
  'printf "%s\n" "must-not-execute-or-download" > /app/storage/app/public/ci-sensitive.php'

start_container() {
  docker run --detach --name "$CONTAINER" --stop-timeout 10 "${ENVIRONMENT[@]}" "${VOLUMES[@]}" --publish "127.0.0.1::$CONTAINER_PORT" "$IMAGE" >/dev/null
}

wait_for_health() {
  port="$(docker port "$CONTAINER" "$CONTAINER_PORT/tcp" | sed -n 's/.*://p' | head -n 1)"
  for attempt in $(seq 1 30); do
    if response="$(curl --silent --show-error --fail --max-time 2 "http://127.0.0.1:$port/up" 2>/dev/null)" && grep -q 'Application up' <<<"$response"; then
      return 0
    fi
    sleep 1
  done

  docker logs "$CONTAINER" >&2 || true
  echo "Container health endpoint did not become ready." >&2
  return 1
}

assert_persistence() {
  port="$(docker port "$CONTAINER" "$CONTAINER_PORT/tcp" | sed -n 's/.*://p' | head -n 1)"
  [ "$(curl --silent --show-error --fail --max-time 2 "http://127.0.0.1:$port/storage/ci-persistence.txt")" = "upload-persists" ]
  docker exec "$CONTAINER" php -r \
    '$pdo = new PDO("sqlite:/data/application.sqlite"); exit($pdo->query("SELECT value FROM ci_persistence")->fetchColumn() === "database-persists" ? 0 : 1);'
  docker exec "$CONTAINER" php -r \
    '$pdo = new PDO("sqlite:/data/application.sqlite"); exit(((int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0 && (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() === 0) ? 0 : 1);'
}

assert_http_surface() {
  port="$(docker port "$CONTAINER" "$CONTAINER_PORT/tcp" | sed -n 's/.*://p' | head -n 1)"
  asset_path="$(docker exec "$CONTAINER" sh -c 'find /app/public/build/assets -type f | head -n 1')"
  test -n "$asset_path"
  curl --silent --show-error --fail --max-time 2 "http://127.0.0.1:$port${asset_path#/app/public}" >/dev/null

  for path in '/.env' '/composer.json' '/app/Providers/AppServiceProvider.php' '/vendor/autoload.php' '/application.sqlite' '/../composer.json' '/storage/ci-sensitive.php'; do
    status="$(curl --path-as-is --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 2 "http://127.0.0.1:$port$path")"
    if [ "$status" = "200" ]; then
      echo "Sensitive path was retrievable: $path" >&2
      return 1
    fi
  done
}

stop_container() {
  if ! timeout 15 docker stop --time 10 "$CONTAINER" >/dev/null; then
    docker logs "$CONTAINER" >&2 || true
    echo "Container did not shut down within the bounded interval." >&2
    return 1
  fi
}

start_container
wait_for_health
assert_persistence
assert_http_surface
docker top "$CONTAINER" -eo pid,comm | grep -q apache2
stop_container
docker start "$CONTAINER" >/dev/null
wait_for_health
assert_persistence
assert_http_surface
stop_container

echo "Production HTTP runtime, shutdown, sensitive-path, no-seed, and persistent-volume smoke checks passed."
