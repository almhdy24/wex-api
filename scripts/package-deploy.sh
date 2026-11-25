#!/usr/bin/env bash
# Builds wex-deploy-<version>.zip: the exact web-root layout for single-directory hosting.
# CI ONLY (builds the web app and installs composer deps) — never run on the phone.
#
# Usage: scripts/package-deploy.sh <path-to-wex-web> [version] [core-dir-name]
set -euo pipefail

API_DIR="$(cd "$(dirname "$0")/.." && pwd)"
WEB_DIR="$(cd "${1:?path to wex-web}" && pwd)"
VERSION="${2:-$(git -C "$API_DIR" describe --tags --always 2>/dev/null || date +%Y%m%d)}"
CORE="${3:-_wex_core}"
OUT="$API_DIR/build/deploy"
ROOT="$OUT/webroot"
ZIP="$API_DIR/build/wex-deploy-${VERSION}.zip"

[[ "$CORE" =~ ^[A-Za-z0-9_-]{3,64}$ ]] || { echo "invalid core dir name: $CORE" >&2; exit 1; }

rm -rf "$OUT" "$ZIP"
mkdir -p "$ROOT"

echo "==> web build"
( cd "$WEB_DIR" && npm ci --no-audit --no-fund && VITE_API_BASE_URL=/api/v1 npm run build )
cp -R "$WEB_DIR/dist/." "$ROOT/"

echo "==> api core ($CORE)"
mkdir -p "$ROOT/$CORE"
rsync -a --delete \
  --exclude '.git' --exclude 'tests' --exclude 'build' --exclude 'docs' --exclude 'scripts' \
  --exclude 'deploy' --exclude 'public' --exclude 'node_modules' --exclude '.github' \
  --exclude '.env' --exclude 'env' --exclude 'phpunit*' --exclude '*.md' \
  --exclude 'writable/*.sqlite' --exclude 'writable/database/*.sqlite' --exclude 'writable/logs/*' --exclude 'writable/cache/*' \
  --exclude 'writable/session/*' --exclude 'writable/uploads/*' --exclude 'writable/debugbar/*' \
  --exclude 'writable/*.lock' --exclude 'writable/cron_state.json' \
  "$API_DIR/" "$ROOT/$CORE/"
( cd "$ROOT/$CORE" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --quiet )
mkdir -p "$ROOT/$CORE/writable/"{cache,logs,session,uploads/proofs,database}
cp "$API_DIR/deploy/heliohost/env.production.example" "$ROOT/$CORE/.env.example"

echo "==> web-root files"
mkdir -p "$ROOT/api" "$ROOT/uploads/products"
sed "s/__WEX_CORE_DIR__/$CORE/g" "$API_DIR/deploy/heliohost/api/index.php" > "$ROOT/api/index.php"
cp "$API_DIR/deploy/heliohost/api/.htaccess" "$ROOT/api/.htaccess"
sed "s/__WEX_CORE_DIR__/$CORE/g" "$API_DIR/deploy/heliohost/root.htaccess" > "$ROOT/.htaccess"
cp "$API_DIR/deploy/heliohost/core/.htaccess" "$ROOT/$CORE/.htaccess"
cp "$API_DIR/deploy/heliohost/uploads/.htaccess" "$ROOT/uploads/.htaccess"
[ -f "$API_DIR/public/robots.txt" ] && [ ! -f "$ROOT/robots.txt" ] && cp "$API_DIR/public/robots.txt" "$ROOT/robots.txt"

# Second line of defence: an index stub in every non-public directory of the core.
find "$ROOT/$CORE" -type d -not -path '*/vendor/*' | while read -r d; do
  [ -e "$d/index.html" ] || [ -e "$d/index.php" ] || cp "$API_DIR/deploy/heliohost/stub-index.html" "$d/index.html"
done

echo "==> validate"
fail() { echo "LAYOUT ERROR: $*" >&2; exit 1; }
for f in index.html .htaccess api/index.php api/.htaccess "$CORE/.htaccess" "$CORE/.env.example" \
         "$CORE/app/Config/Paths.php" "$CORE/vendor/codeigniter4/framework/system/Boot.php" uploads/.htaccess; do
  [ -e "$ROOT/$f" ] || fail "missing $f"
done
[ -e "$ROOT/$CORE/.env" ] && fail ".env must not be shipped"
grep -q "__WEX_CORE_DIR__" "$ROOT/.htaccess" "$ROOT/api/index.php" && fail "unsubstituted core dir"
BAD=$(find "$ROOT" \( -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.db' -o -name '*.log' -o -name '.git' -o -name 'phpunit*' -o -name '.env' \) \
  -not -path '*/vendor/*')
[ -n "$BAD" ] && { echo "$BAD" >&2; fail "dev/secret files present"; }
[ -d "$ROOT/$CORE/tests" ] && fail "tests shipped"
# no secret values in shipped env files (keys must be present but empty in the example)
SECRETS=$(grep -RHE "^(JWT_SECRET|encryption\.key|INSTALL_TOKEN|CRON_TOKEN|VAPID_PRIVATE_KEY|email\.SMTPPass|database\.default\.password) *= *[^[:space:]]" \
  "$ROOT/$CORE" --include='.env*' 2>/dev/null || true)
[ -n "$SECRETS" ] && { echo "$SECRETS" >&2; fail "secret value in a shipped env file"; }
php -l "$ROOT/api/index.php" >/dev/null

echo "==> manifest + zip"
( cd "$ROOT" && find . -type f -not -name MANIFEST.txt | LC_ALL=C sort | xargs sha256sum > MANIFEST.txt )
( cd "$ROOT" && zip -qr9 "$ZIP" . )
echo "built $ZIP ($(du -h "$ZIP" | cut -f1)), $(wc -l < "$ROOT/MANIFEST.txt") files"
