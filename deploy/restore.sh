#!/usr/bin/env bash
# Restores a database dump (and optionally the storage tarball) produced by
# deploy/backup.sh. Destructive: overwrites the current database and, if a
# storage archive is given, extracts on top of storage/app/public.
#
# Usage: deploy/restore.sh <db-backup-file> [storage-backup.tar.gz]
set -euo pipefail
cd "$(dirname "$0")/.."

DB_BACKUP="${1:-}"
STORAGE_BACKUP="${2:-}"

if [ -z "$DB_BACKUP" ]; then
  echo "Usage: $0 <db-backup-file> [storage-backup.tar.gz]" >&2
  exit 1
fi
if [ ! -f "$DB_BACKUP" ]; then
  echo "No such backup file: $DB_BACKUP" >&2
  exit 1
fi

set -a
[ -f .env ] && source .env
set +a

case "${DB_CONNECTION:-sqlite}" in
  sqlite)
    cp "$DB_BACKUP" "${DB_DATABASE:-database/database.sqlite}"
    ;;
  mysql)
    mysql -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" < "$DB_BACKUP"
    ;;
  pgsql)
    PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" "${DB_DATABASE}" < "$DB_BACKUP"
    ;;
  *)
    echo "Unsupported DB_CONNECTION: ${DB_CONNECTION:-}" >&2
    exit 1
    ;;
esac

if [ -n "$STORAGE_BACKUP" ]; then
  tar -xzf "$STORAGE_BACKUP" -C storage/app
fi

echo "Restore complete from $DB_BACKUP${STORAGE_BACKUP:+ and $STORAGE_BACKUP}."
