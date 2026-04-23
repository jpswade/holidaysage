#!/usr/bin/env bash
# Run setup-proxmox-lxc.sh on the Proxmox host via SSH from your Mac (project root).
# Options come from .env.deploy; optional CLI overrides below.
#
# Usage:
#   ./scripts/run-setup-lxc-on-host.sh
#
# Required in .env.deploy: DEPLOY_SERVER_HOST, DEPLOY_SERVER_PATH, DEPLOY_LXC_CTID, DEPLOY_LXC_PATH, DEPLOY_LXC_IP
# Optional: DEPLOY_LXC_GW, DEPLOY_LXC_NAME, DEPLOY_SERVER_PORT, DEPLOY_SERVER_USER, DEPLOY_PROXMOX_USER

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

[ -f .env.deploy ] || { echo "Create .env.deploy from .env.deploy.example and set DEPLOY_*"; exit 1; }
set -a && source .env.deploy && set +a

HOST="${DEPLOY_SERVER_HOST:?Set DEPLOY_SERVER_HOST in .env.deploy}"
PORT="${DEPLOY_SERVER_PORT:-22}"
SETUP_USER="${DEPLOY_PROXMOX_USER:-$DEPLOY_SERVER_USER}"
SSH_TARGET="${SETUP_USER}@$HOST"
SSH_OPTS=(-o ConnectTimeout=10)
SSH_OPTS_TTY=(-o ConnectTimeout=10)
SCP_OPTS=(-o ConnectTimeout=10)
[[ "$PORT" != "22" ]] && SSH_OPTS+=(-p "$PORT") && SSH_OPTS_TTY+=(-p "$PORT") && SCP_OPTS+=(-P "$PORT")
USE_SUDO=
[[ "$SETUP_USER" != "root" ]] && USE_SUDO="sudo "

CTID="${DEPLOY_LXC_CTID:-210}"
NAME="${DEPLOY_LXC_NAME:-holidaysage}"
BIND="${DEPLOY_SERVER_PATH:?Set DEPLOY_SERVER_PATH}:${DEPLOY_LXC_PATH:?Set DEPLOY_LXC_PATH}"
IP="${DEPLOY_LXC_IP:?Set DEPLOY_LXC_IP in .env.deploy (CIDR, e.g. 87.117.209.50/26)}"
GW="${DEPLOY_LXC_GW:-}"
STORAGE="${DEPLOY_LXC_STORAGE:-local}"
BRIDGE="${DEPLOY_LXC_BRIDGE:-vmbr0}"
NS1="${NAMESERVER_1:-8.8.8.8}"
NS2="${NAMESERVER_2:-1.1.1.1}"
NS3="${NAMESERVER_3:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ctid) CTID="$2"; shift 2 ;;
    --name) NAME="$2"; shift 2 ;;
    --bind) BIND="$2"; shift 2 ;;
    --ip)   IP="$2"; shift 2 ;;
    --gw)   GW="$2"; shift 2 ;;
    --storage) STORAGE="$2"; shift 2 ;;
    --bridge)  BRIDGE="$2"; shift 2 ;;
    *) shift ;;
  esac
done

LXC_ARGS="--ctid $CTID --name $NAME --bind $BIND --ip $IP --storage $STORAGE --bridge $BRIDGE"
[[ -n "$GW" ]] && LXC_ARGS="$LXC_ARGS --gw $GW"
[[ -n "$NS1" ]] && LXC_ARGS="$LXC_ARGS --nameserver $NS1"

[[ -n "$USE_SUDO" ]] && SSH_OPTS_TTY+=(-tt)

CT_EXISTS=
ssh "${SSH_OPTS_TTY[@]}" "$SSH_TARGET" "${USE_SUDO}pct status $CTID" 2>/dev/null && CT_EXISTS=1 || true
if [[ -z "$CT_EXISTS" ]]; then
  echo "=== Create LXC container $CTID ==="
  TMP="/tmp/setup-proxmox-lxc-$$.sh"
  scp "${SCP_OPTS[@]}" "$SCRIPT_DIR/setup-proxmox-lxc.sh" "$SSH_TARGET:$TMP"
  ssh "${SSH_OPTS_TTY[@]}" "$SSH_TARGET" "chmod +x $TMP && ${USE_SUDO}env PATH=/usr/sbin:/usr/bin:\$PATH $TMP $LXC_ARGS && rm -f $TMP"
else
  echo "=== Container $CTID already exists, skipping create ==="
fi

echo ""
echo "=== Setting DNS on container (if NAMESERVER_1 set) ==="
[[ -n "$NAMESERVER_1" ]] && ssh "${SSH_OPTS_TTY[@]}" "$SSH_TARGET" "${USE_SUDO}pct set $CTID --nameserver $NS1" 2>/dev/null || true

echo ""
echo "=== Starting container $CTID ==="
ssh "${SSH_OPTS_TTY[@]}" "$SSH_TARGET" "${USE_SUDO}pct start $CTID 2>/dev/null || true"

echo ""
echo "=== Syncing repo to host (for bind mount) ==="
DEPLOY_RSYNC_USER="${DEPLOY_SERVER_USER:-admin}"
DEPLOY_RSYNC_TARGET="${DEPLOY_RSYNC_USER}@$HOST"
ssh "${SSH_OPTS[@]}" "$DEPLOY_RSYNC_TARGET" "mkdir -p $DEPLOY_SERVER_PATH"
echo "=== Ensuring $DEPLOY_SERVER_PATH is owned by $DEPLOY_RSYNC_USER (bind mount dir is often root-owned) ==="
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "${USE_SUDO}chown -R ${DEPLOY_RSYNC_USER}:${DEPLOY_RSYNC_USER} ${DEPLOY_SERVER_PATH}"
RSYNC_SSH="ssh -o ConnectTimeout=10"
[[ "$PORT" != "22" ]] && RSYNC_SSH="$RSYNC_SSH -p $PORT"
rsync -avz -e "$RSYNC_SSH" \
  --exclude .git --exclude node_modules --exclude storage/logs \
  --exclude .env --exclude '.env.*' --exclude .env.deploy \
  --exclude '.phpunit.cache' --exclude '.phpunit.result.cache' --exclude 'public/hot' --exclude 'public/storage' \
  ./ "$DEPLOY_RSYNC_TARGET:$DEPLOY_SERVER_PATH/"

echo ""
echo "=== Copying .env.live to host ==="
if [ -f .env.live ]; then
  scp "${SCP_OPTS[@]}" .env.live "$DEPLOY_RSYNC_TARGET:$DEPLOY_SERVER_PATH/.env"
else
  echo "No .env.live found. It should be in the repo root (see .env.live). Set DB_PASSWORD and re-run."
  exit 1
fi

echo ""
if [[ -n "$GW" ]]; then
  echo "=== Fixing Ubuntu LXC networking (netplan bypass) ==="
  FIX_TMP="/tmp/fix-lxc-netplan-$$.sh"
  scp -q "${SCP_OPTS[@]}" "$SCRIPT_DIR/fix-lxc-netplan.sh" "$SSH_TARGET:$FIX_TMP"
  ssh "${SSH_OPTS_TTY[@]}" "$SSH_TARGET" "cat $FIX_TMP | ${USE_SUDO}pct exec $CTID -- env LXC_IP=$IP LXC_GW=$GW bash -s" || true
  ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "rm -f $FIX_TMP" 2>/dev/null || true
else
  echo "=== Skipping network fix (DEPLOY_LXC_GW not set) ==="
fi
echo ""
echo "=== Running setup-lxc-app.sh inside container ==="
ENV_NS="NAMESERVER_1=$NS1 NAMESERVER_2=$NS2"
[[ -n "$NS3" ]] && ENV_NS="$ENV_NS NAMESERVER_3=$NS3"
ssh "${SSH_OPTS_TTY[@]}" "$SSH_TARGET" "${USE_SUDO}pct exec $CTID -- env $ENV_NS bash -c \"cd $DEPLOY_LXC_PATH && ./scripts/setup-lxc-app.sh\""

echo ""
echo "=== Adding deploy SSH key to container (for deploy.sh and GitHub Actions) ==="
PUBKEY=""
for f in ~/.ssh/holidaysage-deploy.pub ~/.ssh/domainsage-deploy.pub ~/.ssh/road-deploy.pub ~/.ssh/id_ed25519.pub ~/.ssh/id_rsa.pub; do
  if [ -f "$f" ]; then
    PUBKEY="$(cat "$f")"
    echo "  Using $f"
    break
  fi
done
if [ -n "$PUBKEY" ]; then
  echo "$PUBKEY" | ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "${USE_SUDO}pct exec $CTID -- bash -c 'mkdir -p /root/.ssh && chmod 700 /root/.ssh && cat >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys'"
  echo "  SSH key added. Deploy will connect directly to the container."
else
  echo "  No ~/.ssh/holidaysage-deploy.pub, domainsage-deploy.pub, road-deploy.pub, id_ed25519.pub or id_rsa.pub found."
  echo "  Create ~/.ssh/holidaysage-deploy (see setup-deploy-key.sh), then run ./scripts/add-deploy-key-to-container.sh"
fi

echo ""
echo "Done. Container $CTID is running with the HolidaySage stack."
echo "If deploy uses DEPLOY_LXC_IP directly, ensure the deploy key is in the container: ./scripts/add-deploy-key-to-container.sh"
echo "Then run ./scripts/deploy.sh for git-based deploys."
