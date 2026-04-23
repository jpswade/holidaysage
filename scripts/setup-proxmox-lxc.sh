#!/usr/bin/env bash
# Create a Proxmox LXC container for HolidaySage. Run on the Proxmox host.
#
# With --bind: host path is bind-mounted into the container; rsync from your Mac to the host,
# then run setup-lxc-app.sh inside the CT. Use DEPLOY_LXC_CTID and DEPLOY_LXC_PATH in .env.deploy.
#
# One-time: ensure an Ubuntu 24 LXC template exists on the host:
#   pveam update && pveam available --section system
#   pveam download local ubuntu-24.04-standard_24.04-2_amd64.tar.zst
#
# Usage:
#   ./scripts/setup-proxmox-lxc.sh [OPTIONS]
#
# Options:
#   --ctid ID        Container ID (default: 210)
#   --name NAME      Hostname (default: holidaysage)
#   --bind H:P       Bind mount: host path H → container path P
#   --template NAME  Template (default: local Ubuntu 24.04)
#   --storage NAME   Storage for rootfs (default: local)
#   --memory MB      Memory in MB (default: 2048)
#   --cores N        CPU cores (default: 1)
#   --ip IP          Static IP (e.g. 87.117.209.50/26)
#   --gw GATEWAY     Gateway (optional; use with --ip)
#   --nameserver IP  DNS server (optional; default: 8.8.8.8)
#   --bridge NAME    Bridge (default: vmbr0)
#   --dry-run        Print commands only

set -e

CTID=210
NAME="holidaysage"
BIND=
NAMESERVER="8.8.8.8"
TEMPLATE="local:vztmpl/ubuntu-24.04-standard_24.04-2_amd64.tar.zst"
STORAGE="local"
MEMORY=2048
CORES=1
IP=
GW=
BRIDGE="vmbr0"
DRY_RUN=

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ctid)     CTID="$2"; shift 2 ;;
    --name)     NAME="$2"; shift 2 ;;
    --bind)     BIND="$2"; shift 2 ;;
    --template) TEMPLATE="$2"; shift 2 ;;
    --storage)  STORAGE="$2"; shift 2 ;;
    --memory)   MEMORY="$2"; shift 2 ;;
    --cores)    CORES="$2"; shift 2 ;;
    --ip)       IP="$2"; shift 2 ;;
    --gw)       GW="$2"; shift 2 ;;
    --nameserver) NAMESERVER="$2"; shift 2 ;;
    --bridge)   BRIDGE="$2"; shift 2 ;;
    --dry-run)  DRY_RUN=1; shift ;;
    *)
      echo "Unknown option: $1"
      echo "Usage: $0 [--ctid ID] [--name NAME] [--bind HOST_PATH:CONTAINER_PATH] ..."
      exit 1
      ;;
  esac
done

run() {
  if [[ -n "$DRY_RUN" ]]; then
    echo "  $*"
  else
    "$@"
  fi
}

if [[ -n "$IP" ]]; then
  NET0="name=eth0,bridge=$BRIDGE,ip=$IP"
  [[ -n "$GW" ]] && NET0="${NET0},gw=$GW"
else
  NET0="name=eth0,bridge=$BRIDGE,ip=dhcp"
fi

if [[ -n "$BIND" ]]; then
  BIND_HOST="${BIND%%:*}"
  BIND_CT="${BIND#*:}"
  echo "=== Create Proxmox LXC for HolidaySage (native stack, bind mount) ==="
  echo "  CTID=$CTID  Name=$NAME  Memory=${MEMORY}MB  Cores=$CORES"
  echo "  Bind: $BIND_HOST → $BIND_CT"
  [[ -n "$DRY_RUN" ]] && echo "(dry run)"
  FEATURES="--features nesting=1"
else
  echo "=== Create Proxmox LXC for HolidaySage (Docker-ready) ==="
  echo "  CTID=$CTID  Name=$NAME  Memory=${MEMORY}MB  Cores=$CORES"
  [[ -n "$DRY_RUN" ]] && echo "(dry run)"
  FEATURES="--features nesting=1,keyctl=1"
fi

run pct create "$CTID" "$TEMPLATE" \
  --hostname "$NAME" \
  --memory "$MEMORY" \
  --cores "$CORES" \
  --rootfs "${STORAGE}:16" \
  $FEATURES \
  --net0 "$NET0" \
  --nameserver "$NAMESERVER" \
  --unprivileged 0

if [[ -n "$BIND" ]]; then
  echo "Adding bind mount (container must be stopped)..."
  run mkdir -p "$BIND_HOST"
  run pct set "$CTID" -mp0 "${BIND_HOST},mp=${BIND_CT}"
  echo ""
  echo "Start the container: pct start $CTID"
  echo "Set in .env.deploy: DEPLOY_LXC_CTID=$CTID DEPLOY_LXC_PATH=$BIND_CT"
  echo "Then from your Mac: ./scripts/run-setup-lxc-on-host.sh (or pct exec $CTID -- bash -c 'cd $BIND_CT && ./scripts/setup-lxc-app.sh')"
else
  echo ""
  echo "Start the container: pct start $CTID"
fi
