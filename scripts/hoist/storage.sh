#!/usr/bin/env bash
# Ensure shared storage exists and link staging/storage to it (before activate).
set -e
HOIST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=envvars
source "$HOIST_DIR/envvars"

mkdir -p "$DEPLOY_STORAGE_PATH"
chown -R www-data:www-data "$DEPLOY_STORAGE_PATH" 2>/dev/null || true
chmod -R 775 "$DEPLOY_STORAGE_PATH" 2>/dev/null || true

for sub in app framework/cache/data framework/sessions framework/views logs; do
  mkdir -p "$DEPLOY_STORAGE_PATH/$sub"
done
chown -R www-data:www-data "$DEPLOY_STORAGE_PATH" 2>/dev/null || true

rm -rf "$DEPLOY_STAGING_PATH/storage"
ln -fsn "$DEPLOY_STORAGE_PATH" "$DEPLOY_STAGING_PATH/storage"

php "$DEPLOY_STAGING_PATH/artisan" storage:link 2>/dev/null || true
