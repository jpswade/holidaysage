#!/usr/bin/env bash
# Atomic swap: staging becomes current.
set -e
HOIST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=envvars
source "$HOIST_DIR/envvars"

if [ ! -d "$DEPLOY_STAGING_PATH" ]; then
  echo "activate.sh: staging path not found: $DEPLOY_STAGING_PATH"
  exit 1
fi

[[ -d "$DEPLOY_CURRENT_PATH" ]] && mv -f "$DEPLOY_CURRENT_PATH" "${DEPLOY_CURRENT_PATH}.old"
mv -f "$DEPLOY_STAGING_PATH" "$DEPLOY_CURRENT_PATH"

ln -fsn "$DEPLOY_CURRENT_PATH/public" "$DEPLOY_PUBLIC_PATH"
ln -fsn "$DEPLOY_STORAGE_PATH" "$DEPLOY_CURRENT_PATH/storage"

echo "Activated: $DEPLOY_CURRENT_PATH"
