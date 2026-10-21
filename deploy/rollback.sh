#!/usr/bin/env bash
# Points /var/www/ecenter/current back at the previous release.
#
# Code only: database migrations are NOT reversed. If the bad release added
# a migration the old code cannot live with, restore the pre-deploy backup
# with deploy/restore.sh instead.
#
# Usage: sudo bash /var/www/ecenter/current/deploy/rollback.sh
set -euo pipefail

APP_DIR=/var/www/ecenter
PHP=8.3

[ "$(id -u)" -eq 0 ] || { echo "Run as root: sudo bash $0" >&2; exit 1; }

CURRENT="$(readlink -f "$APP_DIR/current")"
PREVIOUS="$(ls -1dt "$APP_DIR"/releases/*/ | sed 's:/$::' | grep -v '\.failed$' | grep -vx "$CURRENT" | head -n 1 || true)"
[ -n "$PREVIOUS" ] || { echo "No previous release to roll back to." >&2; exit 1; }

ln -sfn "$PREVIOUS" "$APP_DIR/current.tmp"
mv -T "$APP_DIR/current.tmp" "$APP_DIR/current"
# The failed release would otherwise be "newest" and win the next rollback.
mv "$CURRENT" "$CURRENT.failed"

runuser -u www-data -- php "$APP_DIR/current/artisan" optimize
systemctl reload php$PHP-fpm 2>/dev/null || service php$PHP-fpm reload
supervisorctl restart 'ecenter-worker:*' || true
runuser -u www-data -- php "$APP_DIR/current/artisan" up || true

echo "Rolled back: current -> $(basename "$PREVIOUS")  (was $(basename "$CURRENT"), kept as .failed)"
