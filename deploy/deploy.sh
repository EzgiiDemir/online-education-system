#!/usr/bin/env bash
# Deploys one release package (made by deploy/package.sh) on a server
# prepared by deploy/server-setup.sh. Used for the first install and for
# every update afterwards.
#
# Usage:  sudo bash deploy/deploy.sh /home/<user>/ecenter-YYYYMMDD-<sha>.tar.gz
#
# Layout:
#   /var/www/ecenter/releases/<timestamp>/   one directory per deploy
#   /var/www/ecenter/shared/.env             survives deploys
#   /var/www/ecenter/shared/storage/         uploads, logs, backups
#   /var/www/ecenter/current -> releases/<timestamp>   what Nginx serves
#
# The switch to the new release is a single atomic symlink swap; the five
# most recent releases are kept so deploy/rollback.sh can go back.
set -euo pipefail

PACKAGE="${1:-}"
APP_DIR=/var/www/ecenter
SHARED="$APP_DIR/shared"
KEEP=5
PHP=8.3

[ "$(id -u)" -eq 0 ] || { echo "Run as root: sudo bash $0 <package.tar.gz>" >&2; exit 1; }
[ -f "$PACKAGE" ] || { echo "Usage: sudo bash $0 <package.tar.gz>" >&2; exit 1; }
[ -f "$SHARED/.env" ] || { echo "$SHARED/.env missing -- run deploy/server-setup.sh first." >&2; exit 1; }

as_www() { runuser -u www-data -- "$@"; }
step() { printf '\n==> %s\n' "$*"; }

RELEASE="$APP_DIR/releases/$(date +%Y%m%d-%H%M%S)"

step "Unpacking $(basename "$PACKAGE") into $RELEASE"
mkdir -p "$RELEASE"
tar -xzf "$PACKAGE" -C "$RELEASE" 2>&1 | grep -v "Ignoring unknown extended header" || true
# A package tarred on macOS carries an AppleDouble "._name" twin for every
# file (671 of them on the first server), including "._2026_..._table.php"
# next to real migrations. They are never part of the app.
find "$RELEASE" \( -name '._*' -o -name '.DS_Store' \) -delete

# Refuse a broken package before anything touches the live site. The first
# production deploy shipped without public/vendor (a macOS `tar --exclude`
# of "vendor" also matched public/vendor), so every page lost Bootstrap.
missing=""
for required in artisan composer.lock public/index.php public/vendor/bootstrap/bootstrap.custom.min.css \
                public/vendor/bootstrap/bootstrap.bundle.min.js public/build/manifest.json; do
    [ -e "$RELEASE/$required" ] || missing="$missing $required"
done
if [ -n "$missing" ]; then
    rm -rf "$RELEASE"
    echo "ERROR: package is incomplete, nothing was deployed. Missing:$missing" >&2
    echo "Build packages only with: bash deploy/package.sh" >&2
    exit 1
fi
rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"
ln -s "$SHARED/.env" "$RELEASE/.env"
chmod +x "$RELEASE/artisan" "$RELEASE"/deploy/*.sh
chown -R www-data:www-data "$RELEASE"

step "Installing PHP dependencies (no dev packages)"
cd "$RELEASE"
as_www env COMPOSER_HOME=/tmp/composer-www composer install \
    --no-dev --optimize-autoloader --no-interaction --no-progress

PREVIOUS="$(readlink -f "$APP_DIR/current" 2>/dev/null || true)"
if [ -n "$PREVIOUS" ] && [ -d "$PREVIOUS" ]; then
    step "Maintenance mode on (old release)"
    as_www php "$PREVIOUS/artisan" down --retry=15 || true
fi
# Whatever happens below, never leave the site in maintenance mode.
trap 'as_www php "$APP_DIR/current/artisan" up >/dev/null 2>&1 || true' EXIT

step "Database migrations"
as_www php artisan migrate --force

step "Caches and public storage link"
as_www php artisan storage:link --force
as_www php artisan optimize
as_www php artisan filament:optimize 2>/dev/null || true

step "Switching current -> $(basename "$RELEASE")"
ln -sfn "$RELEASE" "$APP_DIR/current.tmp"
mv -T "$APP_DIR/current.tmp" "$APP_DIR/current"

step "Syncing scheduler (cron) and queue worker (Supervisor) config from the release"
# These used to be installed only by server-setup.sh, so a release that
# changed them (e.g. moving the nightly backup into the Laravel scheduler)
# never reached the server.
install -m 644 "$RELEASE/deploy/crontab-scheduler.example" /etc/cron.d/ecenter
install -m 644 "$RELEASE/deploy/supervisor-worker.conf.example" /etc/supervisor/conf.d/ecenter-worker.conf

step "Reloading PHP-FPM and restarting queue workers"
systemctl reload php$PHP-fpm 2>/dev/null || service php$PHP-fpm reload
supervisorctl reread >/dev/null && supervisorctl update >/dev/null
supervisorctl restart 'ecenter-worker:*' || true
as_www php artisan up

step "Removing old releases (keeping $KEEP)"
ls -1dt "$APP_DIR"/releases/*/ | tail -n +$((KEEP + 1)) | xargs -r rm -rf

step "Health check"
# Resolve the public host name to this machine, so the check also follows
# certbot's http->https redirect instead of counting the 301 as success.
HOST="$(grep -oP '^APP_URL=https?://\K[^/]+' "$SHARED/.env")"
if curl -fsSL -o /dev/null --max-time 20 --resolve "$HOST:80:127.0.0.1" --resolve "$HOST:443:127.0.0.1" "http://$HOST/up"; then
    echo "OK -- /up answers 200."
else
    echo "WARNING: /up did not answer 200. Check $SHARED/storage/logs/ and: sudo bash deploy/rollback.sh" >&2
    exit 1
fi
