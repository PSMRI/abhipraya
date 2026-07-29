#!/usr/bin/env bash
set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/abhipraya}"
DOMAIN_NAME="${DOMAIN_NAME:-_}"
PHP_VERSION="${PHP_VERSION:-8.2}"

if [[ "${EUID}" -ne 0 ]]; then echo "Run as root (for example: sudo bash deploy/linux/bootstrap-linux-nginx.sh)." >&2; exit 1; fi
if [[ ! -d "$APP_PATH" ]]; then echo "Application path does not exist: $APP_PATH" >&2; exit 1; fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-redis" mariadb-server redis-server

install -d -o www-data -g www-data "$APP_PATH/api/storage/sessions" "$APP_PATH/api/storage/events"
chown -R www-data:www-data "$APP_PATH/api/storage"

cat > /etc/nginx/sites-available/abhipraya <<EOF
server {
    listen 80;
    server_name ${DOMAIN_NAME};
    root ${APP_PATH};
    index index.php;
    location / { try_files \$uri \$uri/ /ui/router.php?route=\$uri&\$args; }
    location /api/ { try_files \$uri /api/index.php?\$args; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
    location ~ /\. { deny all; }
}
EOF
ln -sfn /etc/nginx/sites-available/abhipraya /etc/nginx/sites-enabled/abhipraya
nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm" nginx mariadb redis-server
systemctl reload nginx

cat <<'EOF'
Bootstrap complete. Next steps:
1. Create a protected .env with DB_* values and ABHIPRAYA_SESSION_HANDLER=redis when using shared sessions.
2. Configure the installed Redis service (or another approved Redis-compatible service) with private binding, credentials, TLS where required, and PHP session.save_path.
3. Apply api/database/schema/abhipraya_core_schema.sql only to a new empty database.
4. Configure HTTPS through the organisation-approved certificate process, then test routes and administrator sign-in.
EOF
