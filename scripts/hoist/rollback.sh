#!/usr/bin/env bash
# Roll back to previous release (current.old). Run on server from $DEPLOY_BASE/current.
set -e
HOIST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=envvars
source "$HOIST_DIR/envvars"

if [ ! -d "${DEPLOY_CURRENT_PATH}.old" ]; then
  echo "rollback.sh: no previous release found (${DEPLOY_CURRENT_PATH}.old missing)."
  exit 1
fi

echo "=== Rolling back to previous release ==="
mv -f "$DEPLOY_CURRENT_PATH" "${DEPLOY_CURRENT_PATH}.new"
mv -f "${DEPLOY_CURRENT_PATH}.old" "$DEPLOY_CURRENT_PATH"
rm -rf "${DEPLOY_CURRENT_PATH}.new"

ln -fsn "$DEPLOY_CURRENT_PATH/public" "$DEPLOY_PUBLIC_PATH"
ln -fsn "$DEPLOY_STORAGE_PATH" "$DEPLOY_CURRENT_PATH/storage"

"$HOIST_DIR/postdeploy.sh"
echo "Rollback complete. App is now at $DEPLOY_CURRENT_PATH"
