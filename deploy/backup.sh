#!/usr/bin/env bash
# Dumps the database (sqlite/mysql/pgsql, auto-detected from .env) plus the
# public storage disk (uploaded materials, avatars, recordings -- everything
# under storage/app/public) into storage/app/backups/<timestamp>-*.
#
# This is local, on-disk-of-the-same-server backup: a genuine first line of
# defense (accidental DROP TABLE, a bad migration, a corrupted upload), but
# NOT disaster recovery on its own -- if the disk/instance is lost, these go
# with it. For real DR, sync storage/app/backups/ offsite on a schedule
# (`aws s3 sync`, `rclone sync`, etc.); that sync step is deliberately not
# included here since it needs credentials/target-specific configuration
# this repo can't know in advance.
#
# Usage: deploy/backup.sh
set -euo pipefail
cd "$(dirname "$0")/.."

set -a
[ -f .env ] && source .env
set +a

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="storage/app/backups"
mkdir -p "$BACKUP_DIR"

case "${DB_CONNECTION:-pgsql}" in
  sqlite)
    DB_PATH="${DB_DATABASE:-database/database.sqlite}"
    cp "$DB_PATH" "$BACKUP_DIR/db-$TIMESTAMP.sqlite"
    DB_BACKUP_FILE="db-$TIMESTAMP.sqlite"
    ;;
  mysql)
    mysqldump --single-transaction -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" > "$BACKUP_DIR/db-$TIMESTAMP.sql"
    DB_BACKUP_FILE="db-$TIMESTAMP.sql"
    ;;
  pgsql)
    PGPASSWORD="${DB_PASSWORD}" pg_dump -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" "${DB_DATABASE}" > "$BACKUP_DIR/db-$TIMESTAMP.sql"
    DB_BACKUP_FILE="db-$TIMESTAMP.sql"
    ;;
  *)
    echo "Unsupported DB_CONNECTION: ${DB_CONNECTION:-}" >&2
    exit 1
    ;;
esac

tar -czf "$BACKUP_DIR/storage-$TIMESTAMP.tar.gz" -C storage/app public

echo "Backup complete:"
echo "  $BACKUP_DIR/$DB_BACKUP_FILE"
echo "  $BACKUP_DIR/storage-$TIMESTAMP.tar.gz"
