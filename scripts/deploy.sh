#!/usr/bin/env bash
# Deploy to LXC: git clone to staging, hoist to current. Requires .env.deploy (see .env.deploy.example).
# Usage: ./scripts/deploy.sh | ./scripts/deploy.sh --no-push | ./scripts/deploy.sh v1.0.0

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

resolve_origin_default_branch() {
  if [ -d .git ]; then
    local b
    b=$(git symbolic-ref -q refs/remotes/origin/HEAD 2>/dev/null | sed 's@^refs/remotes/origin/@@')
    if [ -n "$b" ]; then
      echo "$b"
      return
    fi
    if git rev-parse --verify -q origin/main >/dev/null 2>&1; then
      echo "main"
      return
    fi
    if git rev-parse --verify -q origin/master >/dev/null 2>&1; then
      echo "master"
      return
    fi
  fi
  echo "main"
}

if [ ! -f .env.deploy ]; then
  echo "Create .env.deploy from .env.deploy.example."
  exit 1
fi

set -a && source .env.deploy && set +a

NO_PUSH=
REF_ARG=
for arg in "$@"; do
  case "$arg" in
    --no-push) NO_PUSH=1 ;;
    *) [ -z "$REF_ARG" ] && REF_ARG="$arg" ;;
  esac
done

if [ -n "${DEPLOY_LXC_CTID:-}" ] && [ -n "${DEPLOY_LXC_PATH:-}" ] && [ -n "${DEPLOY_LXC_IP:-}" ]; then
  DEPLOY_HOST="${DEPLOY_LXC_IP%%/*}"
  DEPLOY_USER="${DEPLOY_LXC_SSH_USER:-root}"
  DEPLOY_PORT="${DEPLOY_LXC_SSH_PORT:-22}"
  APP_BASE="${DEPLOY_BASE:-$DEPLOY_LXC_PATH}"
  VCS_REPO="${DEPLOY_VCS_REPO:-$(git remote get-url origin 2>/dev/null || true)}"
  VCS_BRANCH="${DEPLOY_VCS_BRANCH:-$(resolve_origin_default_branch)}"
  VCS_REF="${DEPLOY_VCS_REF:-$REF_ARG}"
  if [ -z "$VCS_REPO" ]; then
    echo "Set DEPLOY_VCS_REPO in .env.deploy for LXC deploy."
    exit 1
  fi
else
  echo "Set DEPLOY_LXC_CTID, DEPLOY_LXC_PATH and DEPLOY_LXC_IP in .env.deploy."
  exit 1
fi

NGINX_SITE="${DEPLOY_NGINX_SITE:-holidaysage}"
SSH_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"

LXC_SSH_KEY="${LXC_SSH_PRIVATE_KEY_PATH:-$HOME/.ssh/holidaysage-deploy}"
LXC_SSH_KEY="${LXC_SSH_KEY/#\~/$HOME}"
if [ ! -f "$LXC_SSH_KEY" ]; then
  echo "SSH private key for the container not found: $LXC_SSH_KEY"
  echo "  Set LXC_SSH_PRIVATE_KEY_PATH or create ~/.ssh/holidaysage-deploy (./scripts/setup-deploy-key.sh)."
  exit 1
fi

SSH_OPTS=(-o ConnectTimeout=10 -o BatchMode=yes -o IdentitiesOnly=yes -i "$LXC_SSH_KEY" -p "$DEPLOY_PORT")

DEPLOY_GIT_KEY="${DEPLOY_KEY_PATH:-$LXC_SSH_KEY}"
DEPLOY_GIT_KEY="${DEPLOY_GIT_KEY/#\~/$HOME}"
if [ ! -f "$DEPLOY_GIT_KEY" ]; then
  echo "Git/SSH key not found: $DEPLOY_GIT_KEY"
  exit 1
fi

if [ -z "$NO_PUSH" ] && [ -z "$VCS_REF" ] && [ -d .git ]; then
  if git remote -q get-url origin &>/dev/null; then
    echo "=== git push ==="
    git push
  fi
fi

ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "mkdir -p $APP_BASE"
if ! ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "command -v git" &>/dev/null; then
  ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git"
fi
if [ -f package.json ] && ! ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "command -v npm" &>/dev/null; then
  ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl gnupg && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nodejs"
fi

if [ -d .git ]; then
  git fetch origin 2>/dev/null || true
  if [ -n "$VCS_REF" ]; then
    EXPECTED_COMMIT=$(git rev-parse "$VCS_REF" 2>/dev/null || true)
  else
    EXPECTED_COMMIT=$(git rev-parse "origin/$VCS_BRANCH" 2>/dev/null || true)
  fi
else
  EXPECTED_COMMIT=""
fi

case "$VCS_REPO" in
  git@*:*) GIT_HOST="${VCS_REPO#*@}"; GIT_HOST="${GIT_HOST%%:*}" ;;
  https://*) GIT_HOST="${VCS_REPO#https://}"; GIT_HOST="${GIT_HOST%%/*}" ;;
  *) GIT_HOST="github.com" ;;
esac
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "mkdir -p ~/.ssh && ssh-keyscan -H $GIT_HOST >> ~/.ssh/known_hosts 2>/dev/null || true"

echo "=== Copy deploy key to container ==="
scp -P "$DEPLOY_PORT" -o ConnectTimeout=10 -o BatchMode=yes -o IdentitiesOnly=yes -i "$LXC_SSH_KEY" "$DEPLOY_GIT_KEY" "$SSH_TARGET:~/.ssh/deploy_key"
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "chmod 600 ~/.ssh/deploy_key"

GIT_TEST_CMD="ssh -i ~/.ssh/deploy_key -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=10 git@$GIT_HOST 2>&1"
GIT_TEST_OUTPUT=$(ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$GIT_TEST_CMD" || true)
echo "$GIT_TEST_OUTPUT"
if echo "$GIT_TEST_OUTPUT" | grep -q "successfully authenticated\|Hi .*! You've successfully authenticated"; then
  echo "(SSH to $GIT_HOST from container: OK)"
elif echo "$GIT_TEST_OUTPUT" | grep -qi "Permission denied\|publickey"; then
  echo "Add the deploy key to GitHub (read-only), then re-run: ./scripts/setup-deploy-key.sh"
  exit 1
fi

echo "=== Clone to staging ==="
if ! ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "set -e
  export DEPLOY_BASE='$APP_BASE'
  export DEPLOY_STAGING_PATH=\"\$DEPLOY_BASE/staging\"
  export DEPLOY_CURRENT_PATH=\"\$DEPLOY_BASE/current\"
  [ -f ~/.ssh/deploy_key ] && export GIT_SSH_COMMAND=\"ssh -i ~/.ssh/deploy_key -o IdentitiesOnly=yes\"
  rm -rf \"\${DEPLOY_BASE}/current.old\"
  rm -rf \"\$DEPLOY_STAGING_PATH\"
  if [ -n '$VCS_REF' ]; then
    git clone '$VCS_REPO' \"\$DEPLOY_STAGING_PATH\"
    (cd \"\$DEPLOY_STAGING_PATH\" && git checkout '$VCS_REF')
  else
    git clone -b '$VCS_BRANCH' '$VCS_REPO' \"\$DEPLOY_STAGING_PATH\"
  fi
"; then
  echo "Clone failed."
  exit 1
fi

ENV_FILE=".env"
[ -f .env.live ] && ENV_FILE=".env.live"
echo "=== Copy $ENV_FILE to staging ==="
scp -P "$DEPLOY_PORT" -o ConnectTimeout=10 -o BatchMode=yes -o IdentitiesOnly=yes -i "$LXC_SSH_KEY" "$ENV_FILE" "$SSH_TARGET:$APP_BASE/staging/.env"

echo "=== Hoist ==="
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "set -e
  export DEPLOY_BASE='$APP_BASE'
  bash '$APP_BASE/staging/scripts/hoist/storage.sh'
  bash '$APP_BASE/staging/scripts/hoist/predeploy.sh'
  bash '$APP_BASE/staging/scripts/hoist/activate.sh'
  bash '$APP_BASE/current/scripts/hoist/postdeploy.sh'
  (cd '$APP_BASE/current' && git rev-parse HEAD > public/.commit)
"

echo "=== nginx docroot ==="
NGINX_ROOT="$APP_BASE/current/public"
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "set -e
  BASE='$APP_BASE'
  ln -fsn \"\$BASE/current/public\" \"\$BASE/public\"
  if [ -f /etc/nginx/sites-available/$NGINX_SITE ]; then
    sed -i 's|^[[:space:]]*root[[:space:]].*;|    root $NGINX_ROOT;|' /etc/nginx/sites-available/$NGINX_SITE
    nginx -t && systemctl reload nginx
  fi
  echo \"Docroot: $NGINX_ROOT\"
"

DEPLOY_VERIFY_URL="${DEPLOY_URL:-http://$DEPLOY_HOST}"
if [ -n "$EXPECTED_COMMIT" ]; then
  DEPLOYED_COMMIT=$(curl -sSf "${DEPLOY_VERIFY_URL}/.commit" 2>/dev/null | tr -d '\n\r' || true)
  if [ -z "$DEPLOYED_COMMIT" ]; then
    echo "Warning: could not fetch .commit from ${DEPLOY_VERIFY_URL}/.commit"
  elif [ "$DEPLOYED_COMMIT" != "$EXPECTED_COMMIT" ]; then
    echo "Deploy version mismatch: expected $EXPECTED_COMMIT, got $DEPLOYED_COMMIT"
    exit 1
  else
    echo "Deployed commit verified: $DEPLOYED_COMMIT"
  fi
fi

echo ""
echo "Deploy finished. Check $DEPLOY_VERIFY_URL"
