#!/usr/bin/env bash
# One-time LXC setup: nginx, PHP 8.3, MariaDB, Redis, Horizon, scheduler. Run as root inside the CT.
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if [ ! -f composer.json ]; then
  echo "Run from the HolidaySage project root inside the container."
  exit 1
fi

NGINX_SITE="${DEPLOY_NGINX_SITE:-holidaysage}"
SVC_PREFIX="${DEPLOY_SYSTEMD_PREFIX:-holidaysage}"

if ! getent hosts archive.ubuntu.com >/dev/null 2>&1; then
  NS1="${NAMESERVER_1:-8.8.8.8}"
  NS2="${NAMESERVER_2:-1.1.1.1}"
  if [ -w /etc/resolv.conf ]; then
    grep -q "nameserver" /etc/resolv.conf 2>/dev/null || echo -e "nameserver $NS1\nnameserver $NS2" >> /etc/resolv.conf
  else
    echo "Fix DNS on the host for this CT, then retry."
    exit 1
  fi
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg
if [ ! -f /etc/apt/sources.list.d/nodesource.list ]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
fi

echo "=== Packages (nginx, PHP, MariaDB, Redis) ==="
apt-get install -y -qq nginx \
  php8.3-fpm php8.3-cli php8.3-redis php8.3-intl php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-tokenizer php8.3-bcmath php8.3-gd \
  mariadb-server redis-server unzip curl openssh-server wget gzip git nodejs

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

PHP_VER="$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")"
FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"

echo "=== .env and database ==="
[ -f .env ] || cp .env.example .env

DB_NAME="${DB_DATABASE:-holidaysage}"
DB_USER="${DB_USERNAME:-holidaysage}"
DB_PASS=""
if [ -f .env ]; then
  DB_PASS=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"' | tr -d "'" || true)
fi
if [ -n "$DB_PASS" ]; then
  mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`; CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS'; GRANT ALL ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1'; FLUSH PRIVILEGES;" 2>/dev/null || true
  sed -i "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" .env 2>/dev/null || true
fi

APP_ROOT="$ROOT"
if [ -n "${DEPLOY_GIT_DEPLOY:-}" ]; then
  echo "=== Git-deploy layout ==="
  BASE="$ROOT"
  mkdir -p "$BASE/storage" "$BASE/staging"
  if [ -d storage ]; then
    for sub in app framework logs; do
      [ -d "storage/$sub" ] && cp -a "storage/$sub" "$BASE/storage/" 2>/dev/null || true
    done
    rm -rf storage
  fi
  for x in .* *; do
    [ "$x" = "." ] || [ "$x" = ".." ] && continue
    [ "$x" = "staging" ] || [ "$x" = "storage" ] && continue
    [ ! -e "$x" ] && continue
    mv "$x" "$BASE/staging/"
  done
  for sub in app framework/cache/data framework/sessions framework/views logs; do
    mkdir -p "$BASE/storage/$sub"
  done
  chown -R www-data:www-data "$BASE/storage" 2>/dev/null || true
  export DEPLOY_BASE="$BASE"
  sh "$BASE/staging/scripts/hoist/storage.sh"
  sh "$BASE/staging/scripts/hoist/predeploy.sh"
  sh "$BASE/staging/scripts/hoist/activate.sh"
  APP_ROOT="$BASE/current"
fi

if [ -z "${DEPLOY_GIT_DEPLOY:-}" ]; then
  COMPOSER="$(command -v composer 2>/dev/null || echo /usr/local/bin/composer)"
  $COMPOSER install --no-dev --no-interaction
  grep -q '^APP_KEY=$' .env 2>/dev/null && php artisan key:generate --force || true
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache
fi

echo "=== Nginx ($NGINX_SITE) ==="
cat > "/etc/nginx/sites-available/$NGINX_SITE" << NGINX
server {
    listen 80 default_server;
    server_name _;
    root $APP_ROOT/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:$FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
}
NGINX
ln -sf "/etc/nginx/sites-available/$NGINX_SITE" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

PHP_BIN="$(command -v php)"
echo "=== Horizon (systemd) ==="
cat > "/etc/systemd/system/${SVC_PREFIX}-horizon.service" << SVC
[Unit]
Description=HolidaySage Laravel Horizon
After=network.target mariadb.service redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_ROOT
ExecStart=$PHP_BIN artisan horizon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVC

echo "=== Scheduler (systemd) ==="
cat > "/etc/systemd/system/${SVC_PREFIX}-scheduler.service" << SVC
[Unit]
Description=HolidaySage Laravel scheduler
After=network.target mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_ROOT
ExecStart=$PHP_BIN artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVC

systemctl daemon-reload
systemctl enable "${SVC_PREFIX}-horizon" "${SVC_PREFIX}-scheduler"
systemctl start "${SVC_PREFIX}-horizon" "${SVC_PREFIX}-scheduler" 2>/dev/null || true

systemctl enable ssh
systemctl start ssh 2>/dev/null || true

systemctl enable nginx "php${PHP_VER}-fpm" mariadb redis-server
systemctl start nginx "php${PHP_VER}-fpm" mariadb redis-server
systemctl reload nginx "php${PHP_VER}-fpm"

echo ""
echo "=== Done ==="
echo "  - Set .env: APP_URL, QUEUE_CONNECTION=redis, CACHE_STORE=redis, REDIS_*, DB_*, TRUSTED_PROXY_ALL=1 when behind Caddy."
echo "  - Run migrations if not using hoist: php artisan migrate --force"
