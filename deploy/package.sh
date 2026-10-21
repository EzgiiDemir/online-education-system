#!/usr/bin/env bash
# Builds the release package that deploy/deploy.sh installs on the server.
# Runs on the developer machine (Git Bash on Windows, or Linux/macOS).
#
# Usage: bash deploy/package.sh      ->  dist/ecenter-<date>-<commit>.tar.gz
#
# The package contains the application source and the compiled front-end
# (public/build), so the server needs no Node.js. It deliberately contains
# no vendor/ (composer installs it on the server, for the server's PHP), no
# .env, no uploads/database/backups from this machine, and no tests.
set -euo pipefail
cd "$(dirname "$0")/.."

SHA="$(git rev-parse --short HEAD)"
[ -z "$(git status --porcelain)" ] || SHA="$SHA-dirty"
NAME="ecenter-$(date +%Y%m%d-%H%M)-$SHA"
mkdir -p dist

echo "==> Building front-end assets"
npm ci --ignore-scripts --no-audit --no-fund
npm run build

echo "==> Collecting files"
LIST="$(mktemp)"
trap 'rm -f "$LIST"' EXIT
{
    # Tracked + new (not ignored) files that still exist on disk.
    git ls-files --cached --others --exclude-standard | while IFS= read -r f; do [ -e "$f" ] && echo "$f"; done
    find public/build -type f
} | grep -Ev '^(tests/|dist/|\.idea/|\.claude/|phpunit\.xml$|PROJE_SUNUMU\.md$|.*\.docx$)' | sort -u > "$LIST"

# Shell scripts with Windows line endings fail on Linux with cryptic errors.
if grep -l $'\r' $(grep -E '\.sh$|^artisan$' "$LIST") 2>/dev/null; then
    echo "ERROR: the files above have CRLF line endings; convert them to LF." >&2
    exit 1
fi

tar -czf "dist/$NAME.tar.gz" -T "$LIST"
( cd dist && sha256sum "$NAME.tar.gz" > "$NAME.tar.gz.sha256" )

echo
echo "Package : dist/$NAME.tar.gz ($(du -h "dist/$NAME.tar.gz" | cut -f1), $(wc -l < "$LIST") files)"
echo "SHA-256 : $(cut -d' ' -f1 "dist/$NAME.tar.gz.sha256")"
[[ "$SHA" != *-dirty ]] || echo "NOTE: built from uncommitted changes -- commit them so this release can be traced."
