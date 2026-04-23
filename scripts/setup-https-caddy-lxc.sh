#!/usr/bin/env bash
# Caddy TLS in the LXC (nginx moves to backend port). Run as root inside the CT.
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKEND_PORT="${CADDY_BACKEND_PORT:-8080}"
SITE_DOMAIN="${CADDY_SITE_DOMAIN:-holidaysage.co.uk}"
SITE_WWW_DOMAIN="${CADDY_SITE_WWW_DOMAIN:-www.holidaysage.co.uk}"
NGINX_SITE="${DEPLOY_NGINX_SITE:-holidaysage}"

[[ "$(id -u)" -ne 0 ]] && echo "Run as root." && exit 1

echo "=== nginx -> port $BACKEND_PORT ==="
if grep -q "listen 80 " "/etc/nginx/sites-available/$NGINX_SITE" 2>/dev/null; then
  sed -i "s/listen 80 /listen $BACKEND_PORT /" "/etc/nginx/sites-available/$NGINX_SITE"
  systemctl reload nginx
elif grep -q "listen $BACKEND_PORT " "/etc/nginx/sites-available/$NGINX_SITE" 2>/dev/null; then
  echo "nginx already on $BACKEND_PORT"
else
  echo "Missing /etc/nginx/sites-available/$NGINX_SITE — run setup-lxc-app.sh first."
  exit 1
fi

if ! command -v caddy >/dev/null 2>&1; then
  apt-get update
  apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
  apt-get update
  apt-get install -y caddy
fi

mkdir -p /etc/caddy
cat > /etc/caddy/Caddyfile << CADDY
${SITE_DOMAIN}, ${SITE_WWW_DOMAIN} {
    reverse_proxy 127.0.0.1:${BACKEND_PORT}
}
CADDY

systemctl enable caddy
systemctl restart caddy
echo "HTTPS: https://${SITE_DOMAIN}"
