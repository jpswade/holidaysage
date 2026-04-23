#!/usr/bin/env bash
# After activate: clear caches, reload services, restart Horizon + scheduler.
set -e
HOIST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=envvars
source "$HOIST_DIR/envvars"

SVC_PREFIX="${DEPLOY_SYSTEMD_PREFIX:-holidaysage}"

cd "$DEPLOY_CURRENT_PATH"
if [ ! -f artisan ]; then
  echo "postdeploy.sh: artisan not found at $DEPLOY_CURRENT_PATH"
  exit 1
fi

echo "=== clear caches ==="
php artisan config:clear
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

echo "=== permissions ==="
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

PHP_VER="$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")"
echo "=== reload php-fpm and nginx ==="
systemctl reload "php${PHP_VER}-fpm" nginx 2>/dev/null || true

echo "=== systemd WorkingDirectory (Horizon + scheduler) ==="
SYSTEMD_RELOAD=0
for svc in "${SVC_PREFIX}-horizon.service" "${SVC_PREFIX}-scheduler.service"; do
  unit="/etc/systemd/system/$svc"
  if [ -f "$unit" ] && grep -q '^WorkingDirectory=' "$unit"; then
    if ! grep -q "^WorkingDirectory=$DEPLOY_CURRENT_PATH\$" "$unit"; then
      sed -i "s|^WorkingDirectory=.*|WorkingDirectory=$DEPLOY_CURRENT_PATH|" "$unit"
      echo "Updated WorkingDirectory in $unit -> $DEPLOY_CURRENT_PATH"
      SYSTEMD_RELOAD=1
    fi
  fi
done
if [ "$SYSTEMD_RELOAD" -eq 1 ]; then
  systemctl daemon-reload
fi

echo "=== Horizon graceful restart ==="
php artisan horizon:terminate 2>/dev/null || true

echo "=== restart Horizon and scheduler ==="
systemctl restart "${SVC_PREFIX}-horizon" 2>/dev/null || true
systemctl restart "${SVC_PREFIX}-scheduler" 2>/dev/null || true

echo "Postdeploy finished. App is serving from $DEPLOY_CURRENT_PATH"
