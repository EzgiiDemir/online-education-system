#!/usr/bin/env bash
# One-time preparation of a fresh Ubuntu 24.04 server for eCenter
# (SUNUCU_TEKNIK_GEREKSINIMLERI.md). Safe to re-run: every step checks
# before it changes anything, and an existing .env / database password is
# never overwritten.
#
# Usage (on the server, from the unpacked release package):
#   sudo DOMAIN=ecenter.arucad.edu.tr bash deploy/server-setup.sh
#
# Installs: Nginx, PHP 8.3 FPM + extensions, Composer, PostgreSQL 16,
# Supervisor, UFW, Fail2ban, unattended-upgrades, Certbot.
# Creates:  /var/www/ecenter/{releases,shared}, the ecenter database + user,
#           shared/.env (with a generated APP_KEY and DB password), the Nginx
#           site, two queue workers, the scheduler cron and a nightly backup.
#
# Does NOT: request the TLS certificate (DNS must point here first -- see
# KURULUM.md), change sshd settings, or deploy the application itself
# (deploy/deploy.sh does that).
set -euo pipefail

DOMAIN="${DOMAIN:-ecenter.arucad.edu.tr}"
APP_DIR=/var/www/ecenter
SHARED="$APP_DIR/shared"
PHP=8.3
SRC="$(cd "$(dirname "$0")/.." && pwd)"

[ "$(id -u)" -eq 0 ] || { echo "Run as root: sudo bash $0" >&2; exit 1; }
. /etc/os-release
[ "${VERSION_ID:-}" = "24.04" ] || echo "WARNING: written for Ubuntu 24.04, this is ${PRETTY_NAME:-unknown}." >&2

step() { printf '\n==> %s\n' "$*"; }

step "Time zone: Europe/Istanbul"
timedatectl set-timezone Europe/Istanbul 2>/dev/null || ln -sf /usr/share/zoneinfo/Europe/Istanbul /etc/localtime

step "Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q \
    nginx \
    php$PHP-fpm php$PHP-cli php$PHP-common php$PHP-pgsql php$PHP-intl php$PHP-mbstring \
    php$PHP-xml php$PHP-curl php$PHP-zip php$PHP-gd php$PHP-bcmath php$PHP-opcache \
    composer unzip curl ca-certificates openssl \
    postgresql-16 postgresql-client-16 \
    supervisor cron ufw fail2ban unattended-upgrades \
    certbot python3-certbot-nginx

step "PHP settings (uploads up to 500 MB, opcache)"
cat > /etc/php/$PHP/fpm/conf.d/99-ecenter.ini <<'INI'
; eCenter -- live-class recordings are accepted up to 500 MB.
upload_max_filesize = 600M
post_max_size = 600M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
date.timezone = Europe/Istanbul
INI
cp /etc/php/$PHP/fpm/conf.d/99-ecenter.ini /etc/php/$PHP/cli/conf.d/99-ecenter.ini
systemctl enable --now php$PHP-fpm >/dev/null 2>&1 || service php$PHP-fpm start
systemctl restart php$PHP-fpm 2>/dev/null || service php$PHP-fpm restart

step "PostgreSQL: database 'ecenter' and user 'ecenter' (local access only)"
systemctl enable --now postgresql >/dev/null 2>&1 || service postgresql start
# Only generate a password when the role does not exist yet; afterwards
# the one in shared/.env is the source of truth.
if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='ecenter'" | grep -q 1; then
    DB_PASSWORD="$(openssl rand -hex 24)"
    runuser -u postgres -- psql -q -c "CREATE ROLE ecenter LOGIN PASSWORD '$DB_PASSWORD'"
    NEW_DB_PASSWORD="$DB_PASSWORD"
fi
if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_database WHERE datname='ecenter'" | grep -q 1; then
    runuser -u postgres -- psql -q -c "CREATE DATABASE ecenter OWNER ecenter ENCODING 'UTF8' TEMPLATE template0 LC_COLLATE 'C.UTF-8' LC_CTYPE 'C.UTF-8'"
fi
# PostgreSQL 16 on Ubuntu listens on localhost only by default; we leave it so.

step "Directories under $APP_DIR"
mkdir -p "$APP_DIR/releases" \
    "$SHARED/storage/app/public" "$SHARED/storage/app/private" "$SHARED/storage/app/backups" \
    "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
    "$SHARED/storage/framework/views" "$SHARED/storage/logs"

step "Environment file $SHARED/.env"
if [ ! -f "$SHARED/.env" ]; then
    [ -n "${NEW_DB_PASSWORD:-}" ] || { echo "Database user 'ecenter' already exists but $SHARED/.env is missing." >&2
        echo "Create $SHARED/.env from .env.production.example by hand with that user's password, then re-run." >&2; exit 1; }
    sed -e "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" \
        -e "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" \
        -e "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$NEW_DB_PASSWORD|" \
        -e "s|^ENTRA_REDIRECT_URI=.*|ENTRA_REDIRECT_URI=https://$DOMAIN/auth/entra/callback|" \
        "$SRC/.env.production.example" > "$SHARED/.env"
    echo "Created. Still to fill in by hand: MAIL_PASSWORD (and ENTRA_* if SSO is used)."
else
    echo "Exists, left untouched."
fi
chown -R www-data:www-data "$APP_DIR"
chmod 640 "$SHARED/.env"

step "Nginx site for $DOMAIN"
sed "s|__DOMAIN__|$DOMAIN|g" "$SRC/deploy/nginx-ecenter.conf" > /etc/nginx/sites-available/ecenter
ln -sfn /etc/nginx/sites-available/ecenter /etc/nginx/sites-enabled/ecenter
rm -f /etc/nginx/sites-enabled/default
sed -i 's/^\s*#\?\s*server_tokens .*/\tserver_tokens off;/' /etc/nginx/nginx.conf
nginx -t
systemctl enable --now nginx >/dev/null 2>&1 || service nginx start
systemctl reload nginx 2>/dev/null || service nginx reload

step "Queue workers (Supervisor) and scheduler / backup (cron)"
cp "$SRC/deploy/supervisor-worker.conf.example" /etc/supervisor/conf.d/ecenter-worker.conf
systemctl enable --now supervisor >/dev/null 2>&1 || service supervisor start
cp "$SRC/deploy/crontab-scheduler.example" /etc/cron.d/ecenter
chmod 644 /etc/cron.d/ecenter
systemctl enable --now cron >/dev/null 2>&1 || service cron start

step "Firewall (UFW): 80, 443 (+ SSH if UFW was off)"
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
if ufw status | grep -q "Status: active"; then
    # Already managed (e.g. SSH limited to the VPN): leave the SSH rules alone.
    echo "UFW already active -- only 80/443 added, existing SSH rules untouched."
else
    # Allow the port sshd really listens on before enabling, or we lock ourselves out.
    SSH_PORT="$( (sshd -T 2>/dev/null || true) | awk '/^port /{print $2; exit}')"
    ufw allow "${SSH_PORT:-22}/tcp" >/dev/null
    ufw --force enable || echo "UFW could not be enabled here (container?) -- enable it on the real server."
fi

step "Fail2ban and automatic security updates"
systemctl enable --now fail2ban >/dev/null 2>&1 || service fail2ban start || true
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'APT'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT

cat <<DONE

Server prepared.

Next:
  1. sudo nano $SHARED/.env        -> fill MAIL_PASSWORD (ENTRA_* only if SSO is used)
  2. sudo bash deploy/deploy.sh <package .tar.gz>
  3. sudo -u www-data php $APP_DIR/current/artisan ecenter:create-admin you@arucad.edu.tr
  4. When DNS for $DOMAIN points here:  sudo certbot --nginx -d $DOMAIN
DONE
