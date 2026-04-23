#!/usr/bin/env bash
# Add the deploy public key to the LXC container root user (via Proxmox pct or ssh-copy-id).
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

DIRECT=0
for arg in "$@"; do
  [[ "$arg" == "--direct" ]] && DIRECT=1
done

if [ -f .env.deploy ]; then
  set -a && source .env.deploy && set +a
elif [ -f .github/deploy-config.env ]; then
  set -a && source .github/deploy-config.env && set +a
else
  echo "No .env.deploy or .github/deploy-config.env found."
  exit 1
fi

HOST="${DEPLOY_LXC_IP%%/*}"
USER="${DEPLOY_LXC_SSH_USER:-root}"
PORT="${DEPLOY_LXC_SSH_PORT:-22}"
KEY="${LXC_SSH_PRIVATE_KEY_PATH:-$HOME/.ssh/holidaysage-deploy}"
KEY="${KEY/#\~/$HOME}"
KEY_PUB="${KEY}.pub"

if [ ! -f "$KEY_PUB" ]; then
  echo "Public key not found: $KEY_PUB"
  exit 1
fi

PUBKEY="$(cat "$KEY_PUB")"

add_via_proxmox() {
  local PHOST="${DEPLOY_SERVER_HOST:?Set DEPLOY_SERVER_HOST for pct}"
  local PPORT="${DEPLOY_SERVER_PORT:-22}"
  local CTID="${DEPLOY_LXC_CTID:?Set DEPLOY_LXC_CTID}"
  local SUSER="${DEPLOY_PROXMOX_USER:-${DEPLOY_SERVER_USER:-admin}}"
  local USE_SUDO=""
  [[ "$SUSER" != "root" ]] && USE_SUDO="sudo "
  SSH_OPTS=(-o ConnectTimeout=10)
  [[ "$PPORT" != "22" ]] && SSH_OPTS+=(-p "$PPORT")

  echo "$PUBKEY" | ssh "${SSH_OPTS[@]}" "${SUSER}@${PHOST}" "${USE_SUDO}pct exec $CTID -- bash -c 'mkdir -p /root/.ssh && chmod 700 /root/.ssh && cat >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys'"
}

add_direct() {
  ssh-copy-id -i "$KEY_PUB" -p "$PORT" "${USER}@${HOST}"
}

if [[ "$DIRECT" == 1 ]]; then
  add_direct
elif [[ -n "${DEPLOY_SERVER_HOST:-}" ]]; then
  add_via_proxmox
else
  add_direct
fi

echo "Done."
