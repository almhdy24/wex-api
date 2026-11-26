#!/usr/bin/env bash
# Deployment smoke test against a running package (CI only).
# Usage: scripts/smoke-deploy.sh <base-url> <container> <core-dir>
# Requires: curl, jq; the package installed in <container> at /var/www/html.
set -uo pipefail
BASE="${1:?base url}"; CT="${2:?container}"; CORE="${3:-_wex_core}"
WR="/var/www/html/$CORE/writable"
PASS=0; FAILS=()
ok()   { PASS=$((PASS+1)); echo "PASS  $*"; }
bad()  { FAILS+=("$*"); echo "FAIL  $*"; }
code() { curl -sk -o /dev/null -w '%{http_code}' "$@"; }
hdr()  { curl -sk -D - -o /dev/null "$@" | tr -d '\r'; }
API=()   # real HTTPS (self-signed) in the smoke container; app runs in production mode

expect_code() { local want="$1"; shift; local got; got=$(code "$@"); [[ " $want " == *" $got "* ]] && ok "$* -> $got" || bad "$* -> $got (want $want)"; }

# 0. HTTP is redirected to HTTPS
HTTP_BASE="${BASE/https:/http:}"
loc=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$HTTP_BASE/products")
[[ "$loc" == "301 https://"* ]] && ok "HTTP -> HTTPS ($loc)" || bad "no HTTPS redirect ($loc)"
h=$(hdr "$BASE/"); grep -qi '^strict-transport-security:' <<<"$h" && ok "HSTS on HTTPS" || bad "HSTS missing"

# 1. API up
expect_code "200" "${API[@]}" "$BASE/api/v1/ping"

# 2. Core, secrets and dev files are never served
for p in "/$CORE/.env" "/$CORE/.env.example" "/$CORE/app/Config/Paths.php" "/$CORE/writable/logs/" "/$CORE/composer.json" \
         "/$CORE/spark" "/$CORE/writable/install.lock" "/.env" "/composer.json" "/x.sqlite" "/backup.sql" "/MANIFEST.txt" \
         "/.git/config" "/$CORE/" "/api/.htaccess"; do
  expect_code "403 404" "$BASE$p"
done

# 3. No directory listing
for p in /assets/ /uploads/ /uploads/products/ /api/; do
  body=$(curl -sk "$BASE$p"); [[ "$body" == *"Index of"* ]] && bad "listing at $p" || ok "no listing at $p"
done

# 4. SPA deep links return the app shell with 200
SLUG=$(curl -sk "${API[@]}" "$BASE/api/v1/products?per_page=1" | jq -r '.data[0].slug')
for p in / /admin/products "/products/$SLUG" /catalog /faq /orders /cart; do
  out=$(curl -sk -w '\n%{http_code}' "$BASE$p"); c=${out##*$'\n'}
  [[ "$c" == 200 && "$out" == *'id="root"'* ]] && ok "SPA $p" || bad "SPA $p -> $c"
done
expect_code "404" "$BASE/assets/does-not-exist.js"

# 4b. Link previews / SEO: product pages carry server-side meta + Product JSON-LD
page=$(curl -sk "$BASE/products/$SLUG")
grep -q 'property="og:title"' <<<"$page" && grep -q 'property="og:image"' <<<"$page" && ok "og tags on product page" || bad "og tags missing"
grep -q '"@type":"Product"' <<<"$page" && grep -q '"priceCurrency":"SDG"' <<<"$page" && ok "Product JSON-LD" || bad "Product JSON-LD missing"
grep -qE 'AED|درهم' <<<"$page" && bad "AED on product page" || ok "no AED on product page"
expect_code "404" "$BASE/products/no-such-slug-xyz"
xml=$(curl -sk "$BASE/sitemap.xml"); grep -q "/products/$SLUG</loc>" <<<"$xml" && ok "sitemap lists products" || bad "sitemap"
curl -sk "$BASE/robots.txt" | grep -q 'Disallow: /admin' && ok "robots.txt" || bad "robots.txt"

# 5. Caching and security headers
h=$(hdr "$BASE/admin/products"); grep -qi '^cache-control: no-cache' <<<"$h" && ok "static app shell no-cache" || bad "static shell cache-control"
h=$(hdr "$BASE/"); grep -qi '^cache-control: public, max-age=60' <<<"$h" && ok "SSR page short cache" || bad "SSR cache-control"
grep -qi '^cache-control:.*no-store' <<<"$h" && bad "SSR page no-store" || ok "SSR page never no-store"
ET=$(grep -i '^etag:' <<<"$h" | cut -d' ' -f2-); [[ -n "$ET" ]] && ok "SSR ETag" || bad "SSR ETag missing"
expect_code "304" -H "If-None-Match: $ET" "$BASE/"
grep -qi "^content-security-policy: default-src 'self'" <<<"$h" && ok "CSP on app shell" || bad "CSP missing"
grep -qi '^x-content-type-options: nosniff' <<<"$h" && ok "nosniff" || bad "nosniff missing"
h=$(hdr "$BASE/sw.js"); grep -qi '^cache-control: no-cache' <<<"$h" && ok "sw.js no-cache" || bad "sw.js cache-control"
ASSET=$(curl -sk "$BASE/" | grep -oE '/assets/[^"]+\.js' | head -1)
h=$(hdr "$BASE$ASSET"); grep -qi 'immutable' <<<"$h" && ok "assets immutable ($ASSET)" || bad "asset not immutable"
h=$(hdr "${API[@]}" "$BASE/api/v1/ping"); grep -qi "^content-security-policy: default-src 'none'" <<<"$h" && ok "API CSP" || bad "API CSP"

# 6. Installer and cron are refused without their tokens
expect_code "403" "${API[@]}" -X POST -H 'Content-Type: application/json' -d '{"admin_email":"x@y.z"}' "$BASE/api/v1/internal/install"
expect_code "403" "${API[@]}" -X POST -H 'X-Install-Token: wrong' -H 'Content-Type: application/json' -d '{"admin_email":"x@y.z"}' "$BASE/api/v1/internal/install"
expect_code "410" "${API[@]}" -X POST -H "X-Install-Token: $INSTALL_TOKEN" -H 'Content-Type: application/json' -d '{"admin_email":"again@wex.sd"}' "$BASE/api/v1/internal/install"
# Browser installer (/api/install): token form only, refuses after install
expect_code "200" "$BASE/api/install"
curl -sk "$BASE/api/install" | grep -q 'install_token' && ok "installer page asks for the token" || bad "installer page"
expect_code "403" -X POST -d 'install_token=wrong-token-wrong-token-wrong-token' "$BASE/api/install"
expect_code "410" -X POST --data-urlencode "install_token=$INSTALL_TOKEN" "$BASE/api/install"
expect_code "403" "${API[@]}" -X POST "$BASE/api/v1/internal/cron"
expect_code "200" "${API[@]}" -X POST -H "X-Cron-Token: $CRON_TOKEN" "$BASE/api/v1/internal/cron"

# 7. Receipts: only the owner (and admins) may stream them
login() { # email -> access token via the real email-OTP flow (MAIL_DRIVER=log)
  curl -sk "${API[@]}" -H 'Content-Type: application/json' -d "{\"email\":\"$1\"}" "$BASE/api/v1/auth/email/request" >/dev/null
  local otp; otp=$(docker exec "$CT" sh -c "grep -l '$1' $WR/logs/emails/*.log | xargs ls -t | head -1 | xargs grep -o 'code-box\">[0-9]\{6\}' | grep -o '[0-9]\{6\}'")
  curl -sk "${API[@]}" -D /tmp/verify_headers -H 'Content-Type: application/json' -d "{\"email\":\"$1\",\"code\":\"$otp\",\"name\":\"عميل تجريبي\"}" "$BASE/api/v1/auth/email/verify" | jq -r '.data.access_token'
}
# 6b. First run: ordering is CLOSED until the admin completes setup (real admin API flow)
ADM=$(login "owner@smoke.test")
[[ ${#ADM} -gt 20 ]] && ok "admin OTP login" || bad "admin login failed"
AH=(-H "Authorization: Bearer $ADM" -H 'Content-Type: application/json')
st=$(curl -sk "${AH[@]}" "$BASE/api/v1/admin/setup-status")
[[ "$(jq -r .data.complete <<<"$st")" == false ]] && ok "fresh install: setup incomplete" || bad "setup-status ($st)"
[[ "$(curl -sk "$BASE/api/v1/shop" | jq -r .data.orders_enabled)" == false ]] && ok "fresh install: ordering closed" || bad "ordering open on fresh install"
expect_code "422" "${AH[@]}" -X PATCH -d '{"orders_enabled":true}' "$BASE/api/v1/admin/settings"
expect_code "200" "${AH[@]}" -X PATCH -d '{"shop_whatsapp":"0911000000","shop_address":"عنوان المحل","working_hours":"يوميًا 9 ص - 9 م"}' "$BASE/api/v1/admin/settings"
expect_code "200 201" "${AH[@]}" -X POST -d '{"bank_name":"بنك الخرطوم","account_name":"المتجر","account_number":"1000001","is_active":true}' "$BASE/api/v1/admin/payment-accounts"
expect_code "200 201" "${AH[@]}" -X POST -d '{"rate_sdg":650}' "$BASE/api/v1/admin/rates"
[[ "$(curl -sk "${AH[@]}" "$BASE/api/v1/admin/setup-status" | jq -r .data.complete)" == true ]] && ok "setup complete" || bad "setup still incomplete"
expect_code "200" "${AH[@]}" -X PATCH -d '{"orders_enabled":true}' "$BASE/api/v1/admin/settings"

# 6c. Test-only features do not exist in production
expect_code "404" -X POST -H 'Content-Type: application/json' -d '{"email":"owner@smoke.test"}' "$BASE/api/v1/auth/testing-login"
expect_code "404" "${AH[@]}" "$BASE/api/v1/admin/push/fake-log"

A=$(login "a_$RANDOM@smoke.test"); B=$(login "b_$RANDOM@smoke.test")
[[ ${#A} -gt 20 && ${#B} -gt 20 ]] && ok "OTP logins" || bad "OTP login failed"
PID=$(curl -sk "${API[@]}" "$BASE/api/v1/products?per_page=20&in_stock=1" | jq -r '.data[0].id')
ORDER=$(curl -sk "${API[@]}" -H "Authorization: Bearer $A" -H 'Content-Type: application/json' \
  -d "{\"order_type\":\"pickup\",\"contact_phone\":\"0912345678\",\"items\":[{\"product_id\":$PID,\"quantity\":1}]}" \
  "$BASE/api/v1/orders" | jq -r '.data.order_number')
[[ "$ORDER" == WEX-* ]] && ok "order $ORDER created" || bad "order creation failed"
docker exec "$CT" php -r '$i=imagecreatetruecolor(40,40);imagepng($i,"/tmp/r.png");'
docker cp "$CT:/tmp/r.png" /tmp/receipt.png
URL=$(curl -sk "${API[@]}" -H "Authorization: Bearer $A" -F "proof_image=@/tmp/receipt.png;type=image/png" \
  "$BASE/api/v1/orders/$ORDER/payment-proof" | jq -r '.data.image_url')
[[ "$URL" == /api/v1/* ]] && ok "receipt uploaded" || bad "receipt upload failed"
expect_code "401" "${API[@]}" "$BASE$URL"
expect_code "403 404" "${API[@]}" -H "Authorization: Bearer $B" "$BASE$URL"
expect_code "200" "${API[@]}" -H "Authorization: Bearer $A" "$BASE$URL"
expect_code "403 404" "$BASE/$CORE/writable/uploads/proofs/"
expect_code "401" "${API[@]}" "$BASE/api/v1/admin/orders"
expect_code "403" "${API[@]}" -H "Authorization: Bearer $A" "$BASE/api/v1/admin/orders"

# 8. Uploaded files cannot execute under /uploads
docker exec "$CT" sh -c 'echo "<?php echo 123;" > /var/www/html/uploads/products/x.php'
expect_code "403" "$BASE/uploads/products/x.php"

# 8b. Refresh cookie: HttpOnly + Secure + SameSite=Lax
ck=$(grep -i '^set-cookie:' /tmp/verify_headers | tr -d '\r')
grep -qi 'httponly' <<<"$ck" && grep -qi 'secure' <<<"$ck" && grep -qi 'samesite=lax' <<<"$ck" && ok "session cookie HttpOnly/Secure/Lax" || bad "cookie flags: $ck"

# 9. uploads/ is an image allow-list
docker exec "$CT" sh -c 'cp /tmp/r.png /var/www/html/uploads/products/ok.png; echo "<svg xmlns=\"http://www.w3.org/2000/svg\"><script>1</script></svg>" > /var/www/html/uploads/products/x.svg; echo "<b>x</b>" > /var/www/html/uploads/products/x.html'
expect_code "200" "$BASE/uploads/products/ok.png"
expect_code "403" "$BASE/uploads/products/x.svg"
expect_code "403" "$BASE/uploads/products/x.html"

# 10. Maintenance switch: an empty .maintenance file in the web root -> 503 + Arabic page
docker exec "$CT" sh -c 'touch /var/www/html/.maintenance'
expect_code "503" "$BASE/"
expect_code "503" "$BASE/api/v1/ping"
curl -sk "$BASE/products" | grep -q 'قيد الصيانة' && ok "maintenance page body" || bad "maintenance page missing"
hdr "$BASE/" | grep -qi '^retry-after:' && ok "Retry-After on 503" || bad "Retry-After missing"
docker exec "$CT" sh -c 'rm -f /var/www/html/.maintenance'
expect_code "200" "$BASE/api/v1/ping"
expect_code "200" "$BASE/offline.html"

echo; echo "passed: $PASS, failed: ${#FAILS[@]}"
[[ ${#FAILS[@]} -eq 0 ]] || { printf ' - %s\n' "${FAILS[@]}"; exit 1; }
