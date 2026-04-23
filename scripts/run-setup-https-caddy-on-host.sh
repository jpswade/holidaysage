#!/usr/bin/env bash
# Copy Caddy setup script to the bind mount and run it inside the LXC via pct exec.
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

[ -f .env.deploy ] || { echo "Create .env.deploy"; exit 1; }
set -a && source .env.deploy && set +a

HOST="${DEPLOY_SERVER_HOST:?DEPLOY_SERVER_HOST}"
PORT="${DEPLOY_SERVER_PORT:-22}"
USER="${DEPLOY_SERVER_USER:-admin}"
CTID="${DEPLOY_LXC_CTID:?DEPLOY_LXC_CTID}"
LXC_PATH="${DEPLOY_LXC_PATH:?DEPLOY_LXC_PATH}"
SERVER_PATH="${DEPLOY_SERVER_PATH:?DEPLOY_SERVER_PATH}"
SETUP_USER="${DEPLOY_PROXMOX_USER:-$USER}"
USE_SUDO=
[[ "$SETUP_USER" != "root" ]] && USE_SUDO="sudo "
# ssh uses -p for port; scp uses -P (lowercase -p on scp means preserve times — breaks non-22 ports).
SSH_OPTS=(-o ConnectTimeout=10)
SCP_OPTS=(-o ConnectTimeout=10)
[[ "$PORT" != "22" ]] && SSH_OPTS+=(-p "$PORT") && SCP_OPTS+=(-P "$PORT")

scp -q "${SCP_OPTS[@]}" "$SCRIPT_DIR/setup-https-caddy-lxc.sh" "$SETUP_USER@$HOST:/tmp/setup-https-caddy-lxc.sh"
ssh "${SSH_OPTS[@]}" "$SETUP_USER@$HOST" "${USE_SUDO}cp /tmp/setup-https-caddy-lxc.sh $SERVER_PATH/scripts/setup-https-caddy-lxc.sh"
ssh -tt "${SSH_OPTS[@]}" "$SETUP_USER@$HOST" "${USE_SUDO}pct exec $CTID -- bash -c 'cd ${LXC_PATH} && chmod +x scripts/setup-https-caddy-lxc.sh && ./scripts/setup-https-caddy-lxc.sh'"

echo "Test: https://holidaysage.co.uk (after DNS and firewall)"
