#!/usr/bin/env bash

set -euo pipefail

REPOSITORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ROOT="$REPOSITORY_ROOT/laravel-app"
AUDIT_ROOT="$(mktemp -d)"

cleanup() {
  case "$AUDIT_ROOT" in
    /tmp/*) rm -rf -- "$AUDIT_ROOT" ;;
  esac
}
trap cleanup EXIT

for command in composer npm jq; do
  command -v "$command" >/dev/null || {
    echo "Required audit tool is unavailable: $command" >&2
    exit 2
  }
done

set +e
(cd "$APP_ROOT" && composer audit --locked --format=json --no-interaction) >"$AUDIT_ROOT/composer.json" 2>"$AUDIT_ROOT/composer.stderr"
composer_status=$?
set -e

if ! jq -e '(.advisories | type) == "object" or (.advisories | type) == "array"' "$AUDIT_ROOT/composer.json" >/dev/null 2>&1; then
  echo "Composer advisory service did not return a valid audit result." >&2
  exit 2
fi

composer_findings="$(jq '[.advisories[]?[]?] | length' "$AUDIT_ROOT/composer.json")"
if [ "$composer_findings" -ne 0 ]; then
  echo "Composer audit reported $composer_findings known advisory finding(s)." >&2
  jq -r '.advisories[]?[]? | "- \(.packageName): \(.advisoryId) [\(.severity)]"' "$AUDIT_ROOT/composer.json" >&2
  exit 1
fi

if [ "$composer_status" -ne 0 ]; then
  echo "Composer advisory service failed despite returning no findings." >&2
  exit 2
fi

set +e
(cd "$APP_ROOT" && npm audit --json --audit-level=info) >"$AUDIT_ROOT/npm.json" 2>"$AUDIT_ROOT/npm.stderr"
npm_status=$?
set -e

if ! jq -e '.metadata.vulnerabilities.total | type == "number"' "$AUDIT_ROOT/npm.json" >/dev/null 2>&1; then
  echo "npm advisory service did not return a valid audit result." >&2
  exit 2
fi

npm_findings="$(jq '.metadata.vulnerabilities.total' "$AUDIT_ROOT/npm.json")"
if [ "$npm_findings" -ne 0 ]; then
  echo "npm audit reported $npm_findings known vulnerable package(s)." >&2
  jq -r '.vulnerabilities | to_entries[] | "- \(.key): \(.value.severity)"' "$AUDIT_ROOT/npm.json" >&2
  exit 1
fi

if [ "$npm_status" -ne 0 ]; then
  echo "npm advisory service failed despite returning no findings." >&2
  exit 2
fi

echo "Composer audit completed successfully with no known advisories."
echo "npm audit completed successfully with no known advisories, including development/build dependencies."
