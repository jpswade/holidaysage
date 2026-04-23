#!/usr/bin/env bash
# Run in staging: composer, npm build, migrate, cache.
set -e
HOIST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=envvars
source "$HOIST_DIR/envvars"

cd "$DEPLOY_STAGING_PATH"
if [ ! -f composer.json ]; then
  echo "predeploy.sh: not a Laravel app at $DEPLOY_STAGING_PATH"
  exit 1
fi

echo "=== composer install ==="
composer install --no-dev --no-interaction

if [ -f package.json ]; then
  echo "=== npm install and Vite build ==="
  if ! command -v npm &>/dev/null; then
    echo "predeploy.sh: npm not found."
    exit 1
  fi
  npm ci || npm install
  npm run build
  if [ ! -f public/build/manifest.json ]; then
    echo "predeploy.sh: Vite build did not produce public/build/manifest.json."
    exit 1
  fi
fi

echo "=== migrate ==="
php artisan migrate --force

echo "=== Laravel caches ==="
php artisan config:cache
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

echo "=== permissions ==="
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "Predeploy finished."
