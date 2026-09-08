#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 2 ]; then
  echo "Usage: sync-laravel-release.sh SOURCE_ROOT TARGET_ROOT" >&2
  exit 64
fi

SOURCE_ROOT="${1%/}"
TARGET_ROOT="${2%/}"
RSYNC_BIN="${DEPLOY_RSYNC_BIN:-rsync}"

if [ ! -d "$SOURCE_ROOT/laravel-app" ] || [ ! -d "$TARGET_ROOT/laravel-app" ]; then
  echo "Source and target must contain laravel-app directories." >&2
  exit 1
fi

if [ "$SOURCE_ROOT" = "$TARGET_ROOT" ] || [ "$TARGET_ROOT" = "/" ]; then
  echo "Refusing unsafe release synchronization target." >&2
  exit 1
fi

"$RSYNC_BIN" -a --checksum --delete \
  --exclude='/.git' \
  --exclude='/.github' \
  --exclude='/.env' \
  --exclude='/laravel-app/.env' \
  --exclude='/laravel-app/storage/***' \
  --exclude='/laravel-app/database/*.sqlite*' \
  --exclude='/laravel-app/public/storage' \
  "$SOURCE_ROOT/" "$TARGET_ROOT/"
