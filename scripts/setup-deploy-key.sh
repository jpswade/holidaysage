#!/usr/bin/env bash
# Create a deploy key and add it to GitHub (gh). Default key: ~/.ssh/holidaysage-deploy
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

KEY_PATH="${1:-$HOME/.ssh/holidaysage-deploy}"

if [ ! -f "$KEY_PATH" ]; then
  echo "Creating deploy key at $KEY_PATH"
  ssh-keygen -t ed25519 -f "$KEY_PATH" -N "" -C "holidaysage-deploy"
fi

if ! command -v gh &>/dev/null; then
  echo "Install gh and run: gh auth login"
  echo "Or add manually: gh repo deploy-key add ${KEY_PATH}.pub -t holidaysage-deploy"
  exit 1
fi

GH_OUTPUT=$(gh repo deploy-key add "${KEY_PATH}.pub" -t "holidaysage-deploy" 2>&1) || true
echo "$GH_OUTPUT"

echo ""
echo "Set in .env.deploy: DEPLOY_KEY_PATH=$KEY_PATH (and LXC_SSH_PRIVATE_KEY_PATH if using the same key for SSH)"
