#!/usr/bin/env bash
# make-upgrade-zip.sh <webroot-dir> <version> [core-dir-name]
# Builds wex-technology-upgrade-<version>.zip from an assembled release webroot (CI): the
# same files minus everything the live site owns: the core .env, writable/ (logs, sessions,
# receipts, install.lock) and uploaded product images (uploads/.htaccess is kept so
# security rules still update). After extracting it over public_html, run migrations:
#   curl -X POST -H "X-Cron-Token: <CRON_TOKEN>" https://<domain>/api/v1/internal/upgrade
set -euo pipefail
ROOT="$(cd "${1:?webroot}" && pwd)"; VERSION="${2:?version}"; CORE="${3:-_wex_core}"
OUT="$(dirname "$ROOT")/../wex-technology-upgrade-${VERSION}.zip"
OUT="$(cd "$(dirname "$OUT")" && pwd)/$(basename "$OUT")"
rm -f "$OUT"
( cd "$ROOT" && zip -qr9 "$OUT" . \
    -x "$CORE/.env" "$CORE/writable/*" "uploads/*" \
  && zip -q "$OUT" uploads/.htaccess )
# The upgrade zip must never carry live-site data
if unzip -l "$OUT" | awk '{print $4}' | grep -E "^($CORE/\.env$|$CORE/writable/.+|uploads/products/.+)"; then
  echo "upgrade zip contains live-site paths" >&2; exit 1
fi
( cd "$(dirname "$OUT")" && sha256sum "$(basename "$OUT")" > "$(basename "$OUT").sha256" )
echo "built $OUT ($(du -h "$OUT" | cut -f1))"
