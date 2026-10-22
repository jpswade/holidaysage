#!/usr/bin/env bash
# Keep deployment builds on the patched Node.js 22 line.
set -euo pipefail
minimum=22.23.4
is_supported() {
  command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1 || return 1
  node -e 'const v=process.versions.node.split(".").map(Number); process.exit(v[0]===22 && (v[1]>23 || (v[1]===23 && v[2]>=4)) ? 0 : 1)'
}
if is_supported; then
  node --version
  exit 0
fi
if [[ $(id -u) != 0 ]]; then
  echo "Node.js 22 >= $minimum is required; rerun as root to upgrade." >&2
  exit 1
fi
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg
setup=$(mktemp)
trap 'rm -f "$setup"' EXIT
curl -fsSL https://deb.nodesource.com/setup_22.x -o "$setup"
bash "$setup"
apt-get install -y -qq nodejs
is_supported || { echo "Repository did not supply Node.js 22 >= $minimum." >&2; exit 1; }
node --version
npm --version
