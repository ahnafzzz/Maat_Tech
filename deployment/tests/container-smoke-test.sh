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
CONTAINER="maat-ci-app-$SUFFIX"

cleanup() {
  docker rm --force "$CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$STORAGE_VOLUME" "$DATA_VOLUME" >/dev/null 2>&1 || true
}
trap cleanup EXIT

for command in docker curl openssl; do
  command -v "$command" >/dev/null || {
    echo "Required container smoke-test tool is unavailable: $command" >&2
    exit 2
  }
done

APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"

docker image inspect "$IMAGE" >/dev/null
[ "$(docker image inspect --format '{{.Config.User}}' "$IMAGE")" = "www-data" ]
docker image inspect --format '{{json .Config.Healthcheck.Test}}' "$IMAGE" | grep -q '/up'
docker volume create "$STORAGE_VOLUME" >/dev/null
docker volume create "$DATA_VOLUME" >/dev/null

ENVIRONMENT=(
  --env APP_ENV=production
  --env APP_DEBUG=false
  --env APP_URL=https://container.example.test
  --env "APP_KEY=$APP_KEY"
  --env DB_CONNECTION=sqlite
  --env DB_DATABASE=/data/application.sqlite
  --env DB_URL=
  --env CACHE_STORE=file
  --env SESSION_DRIVER=file
  --env QUEUE_CONNECTION=sync
)
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

start_container() {
  docker run --detach --name "$CONTAINER" "${ENVIRONMENT[@]}" "${VOLUMES[@]}" --publish 127.0.0.1::8000 "$IMAGE" >/dev/null
}

wait_for_health() {
  port="$(docker port "$CONTAINER" 8000/tcp | sed -n 's/.*://p' | head -n 1)"
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
  port="$(docker port "$CONTAINER" 8000/tcp | sed -n 's/.*://p' | head -n 1)"
  [ "$(curl --silent --show-error --fail --max-time 2 "http://127.0.0.1:$port/storage/ci-persistence.txt")" = "upload-persists" ]
  docker exec "$CONTAINER" php -r \
    '$pdo = new PDO("sqlite:/data/application.sqlite"); exit($pdo->query("SELECT value FROM ci_persistence")->fetchColumn() === "database-persists" ? 0 : 1);'
  docker exec "$CONTAINER" php -r \
    '$pdo = new PDO("sqlite:/data/application.sqlite"); exit(((int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0 && (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() === 0) ? 0 : 1);'
}

start_container
wait_for_health
assert_persistence
docker restart "$CONTAINER" >/dev/null
wait_for_health
assert_persistence

echo "Container startup, health, no-seed, and persistent-volume smoke checks passed."
