#!/usr/bin/env bash
# verify-package.sh <webroot-dir> [core-dir-name]
# Fails the release if the assembled public_html tree contains dev data, secrets or
# placeholder contact data. Run by scripts/package-deploy.sh (CI) on every build, and by
# the self-test step in deploy-package.yml (which plants each kind of violation).
set -uo pipefail

ROOT="${1:?webroot dir}"
CORE="${2:-_wex_core}"
[ -d "$ROOT" ] || { echo "no such dir: $ROOT" >&2; exit 2; }
cd "$ROOT"

fails=0
fail() { echo "VERIFY FAIL: $*" >&2; fails=$((fails + 1)); }

# ---------------------------------------------------------------- 1. dev data & files
while IFS= read -r f; do fail "dev/secret file shipped: $f"; done < <(
  find . -not -path "./$CORE/vendor/*" \( \
    -iname '*.sqlite' -o -iname '*.sqlite3' -o -iname '*.db' -o -iname '*.log' -o -iname '*.sql' \
    -o -name '.env' -o -name '.git' -o -name '.github' -o -name 'node_modules' -o -name 'phpunit*' \
    -o -name 'tests' -o -name 'e2e' -o -name '__tests__' -o -name 'screenshots' -o -name 'docs' \
    -o -name 'install.lock' -o -name 'cron_state.json' -o -name '*.bak' -o -name '*.orig' \
    -o -name 'DevSeeder.php' -o -name 'AdminSeeder.php' -o -name 'DatabaseSeeder.php' -o -name 'SettingsSeeder.php' \
  \) -print)

# writable/ may only hold empty dirs, index stubs and .htaccess
if [ -d "$CORE/writable" ]; then
  while IFS= read -r f; do fail "writable/ content shipped: $f"; done < <(
    find "$CORE/writable" -type f -not -name 'index.html' -not -name '.htaccess' -print)
fi
# uploads/ ships no images (real product photos are uploaded on the host)
if [ -d uploads ]; then
  while IFS= read -r f; do fail "uploads/ content shipped: $f"; done < <(
    find uploads -type f -not -name 'index.html' -not -name '.htaccess' -print)
fi

# ---------------------------------------------------------------- 2. secrets
SECRET_RE='-----BEGIN ([A-Z]+ )?PRIVATE KEY-----|"type"[[:space:]]*:[[:space:]]*"service_account"'
while IFS= read -r f; do fail "private key / service account in: $f"; done < <(
  grep -rlEI --exclude-dir=vendor -e "$SECRET_RE" . 2>/dev/null)

ENV_SECRET_RE='^[[:space:]]*(JWT_SECRET|encryption\.key|INSTALL_TOKEN|CRON_TOKEN|VAPID_PRIVATE_KEY|email\.SMTPPass|MAIL_SMTP_PASS|SMTP_PASS|database\.default\.password|GOOGLE_CLIENT_SECRET)[[:space:]]*=[[:space:]]*[^[:space:]#]'
while IFS= read -r f; do
  fail "secret value in shipped env file: $f ($(grep -cE "$ENV_SECRET_RE" "$f") line(s))"
done < <(find . -not -path "./$CORE/vendor/*" -name '.env*' -type f -exec grep -lE -e "$ENV_SECRET_RE" {} + 2>/dev/null)

# hard-coded long hex/base64 secrets assigned in PHP config (heuristic)
while IFS= read -r hit; do fail "hard-coded secret-like value: $hit"; done < <(
  grep -rnEI --include='*.php' --exclude-dir=vendor \
    "(secret|password|private_?key|api_?key)['\"]?[[:space:]]*(=>|=)[[:space:]]*['\"][A-Za-z0-9+/_=-]{24,}['\"]" "$CORE" 2>/dev/null)

# ---------------------------------------------------------------- 3. placeholder contact data
# Outside vendor/. The web bundle is scanned too; a bundled-library hit must be added to
# ALLOW with the exact file + reason.
PLACEHOLDER_RE='wextechnology\.com|example\.(com|org|net)|0123456789|0912345678|249912345678|مدير النظام \(CI\)|customer@wex\.sd|@wex\.sd|smoke\.test|mailto:support@wex\.sd'
ALLOW_RE='^$'
while IFS= read -r hit; do
  [[ "$hit" =~ $ALLOW_RE ]] && continue
  fail "placeholder data: ${hit:0:200}"
done < <(grep -rnoEI --exclude-dir=vendor --exclude='MANIFEST.txt' -e "$PLACEHOLDER_RE" . 2>/dev/null)

# ---------------------------------------------------------------- 4. layout sanity
for f in index.html .htaccess api/index.php api/.htaccess "$CORE/.htaccess" uploads/.htaccess; do
  [ -e "$f" ] || fail "missing $f"
done
grep -q "__WEX_CORE_DIR__" .htaccess api/index.php 2>/dev/null && fail "unsubstituted core dir"

if [ "$fails" -gt 0 ]; then
  echo "❌ verify-package: $fails problem(s)" >&2
  exit 1
fi
echo "✅ verify-package: no dev data, secrets or placeholder data in $(find . -type f | wc -l) files"
