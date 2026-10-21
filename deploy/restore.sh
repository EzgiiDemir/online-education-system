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

# Same DB_*-only parsing as backup.sh (sourcing .env breaks on secrets).
env_get() { [ -f .env ] && grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -E "s/^([\"'])(.*)\1\$/\2/"; }
for key in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
  value="$(env_get "$key" || true)"
  [ -n "$value" ] && export "$key=$value"
done

case "${DB_CONNECTION:-pgsql}" in
  sqlite)
    cp "$DB_BACKUP" "${DB_DATABASE:-database/database.sqlite}"
    ;;
  mysql)
    mysql -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" < "$DB_BACKUP"
    ;;
  pgsql)
    # All or nothing: stop at the first error and roll the whole load back,
    # instead of reporting success over a half-restored database.
    PGPASSWORD="${DB_PASSWORD}" psql -q -v ON_ERROR_STOP=1 --single-transaction -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" "${DB_DATABASE}" < "$DB_BACKUP" > /dev/null
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
