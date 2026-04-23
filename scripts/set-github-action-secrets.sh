#!/usr/bin/env bash
# Push GitHub Actions repository secrets from local files (same idea as DomainSage).
# Requires: gh CLI, gh auth login. Run from repo root.
# Defaults (override with env vars):
#   LXC_SSH_PRIVATE_KEY ← ~/.ssh/holidaysage-deploy
#   ENV_PRODUCTION      ← .env.live
#   DEPLOY_KEY          ← ~/.ssh/holidaysage-deploy

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if ! command -v gh &>/dev/null; then
  echo "gh CLI not found. Install with: brew install gh"
  exit 1
fi

if ! gh auth status &>/dev/null; then
  echo "Not logged in to GitHub. Run: gh auth login"
  exit 1
fi

LXC_KEY_PATH="${LXC_SSH_PRIVATE_KEY_PATH:-$HOME/.ssh/holidaysage-deploy}"
LXC_KEY_PATH="${LXC_KEY_PATH/#\~/$HOME}"
[[ "${LXC_KEY_PATH:0:1}" == "/" ]] || LXC_KEY_PATH="$ROOT/$LXC_KEY_PATH"

if [[ -f "$LXC_KEY_PATH" ]]; then
  echo "Validating LXC key loads..."
  TMP_KEY=$(mktemp)
  trap 'rm -f "$TMP_KEY"' EXIT
  printf '%s\n' "$(cat "$LXC_KEY_PATH")" | tr -d '\r' > "$TMP_KEY"
  chmod 600 "$TMP_KEY"
  eval "$(ssh-agent -s)"
  if ! ssh-add "$TMP_KEY" 2>/dev/null; then
    echo "Failed: LXC key could not be loaded. Fix $LXC_KEY_PATH then re-run."
    exit 1
  fi
  ssh-add -D 2>/dev/null || true
  trap - EXIT
  rm -f "$TMP_KEY"
  echo "  OK"
fi

push_secret() {
  local secret_name="$1"
  local path="$2"
  path="${path/#\~/$HOME}"
  [[ "${path:0:1}" == "/" ]] || path="$ROOT/$path"
  if [[ ! -f "$path" ]]; then
    echo "Skip $secret_name (file not found: $path)"
    return 0
  fi
  echo "Setting secret: $secret_name (from $path)"
  gh secret set "$secret_name" < "$path"
}

push_secret "LXC_SSH_PRIVATE_KEY" "$LXC_KEY_PATH"
push_secret "ENV_PRODUCTION" "${ENV_PRODUCTION_PATH:-$ROOT/.env.live}"
push_secret "DEPLOY_KEY" "${DEPLOY_KEY_PATH:-$HOME/.ssh/holidaysage-deploy}"

echo ""
echo "Checking required secrets are set..."
LIST=$(gh secret list 2>/dev/null | awk '{print $1}' || true)
missing=0
for name in LXC_SSH_PRIVATE_KEY ENV_PRODUCTION; do
  if echo "$LIST" | grep -qx "$name"; then
    echo "  OK   $name"
  else
    echo "  MISS $name"
    ((missing++)) || true
  fi
done
if [[ "$missing" -gt 0 ]]; then
  echo "One or more required secrets are missing."
  exit 1
fi
echo ""
echo "Done."
