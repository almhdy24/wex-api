# Delivery readiness — Wex Technology release for HelioHost

Date: 2026-11-26 · Branch `stabilize` · Evidence = GitHub Actions runs (nothing was built or
browser-tested on the phone).

## Verdict

__VERDICT__

## CI evidence

| Suite | Run | Result |
|---|---|---|
| Release package + scanner + scanner self-test + upgrade zip | __DEPLOY__ (job `package`) | __PKG__ |
| Smoke on the zip: Apache + PHP-FPM 8.3 + MariaDB 10.6 | __DEPLOY__ | __MARIA__ |
| Smoke on the zip: Apache + PHP-FPM 8.3 + MySQL 8.0 | __DEPLOY__ | __MYSQL__ |
| API PHPUnit (SQLite, MySQL 8, MariaDB 10.6; 199 tests) | 45861691099 | success |
| Web e2e (real API + storefront + admin) | 45857099888 | success |
| Web UI & visual regression check (unit tests, copy, a11y, contrast) | 45857099342 | success |
| Web visual snapshots | 45857099121 | success |

## Features

| Feature | Status | Evidence |
|---|---|---|
| Package has no dev data, secrets, placeholder contacts | VERIFIED | verify-package.sh in package job; self-test plants 15 violation kinds, each rejected (it caught a real scanner bug: private keys were never matched, fixed a91f803) |
| Browser installer `/api/install` (token, checks, DB test, secrets → .env, admin, 410 lock) | VERIFIED | deploy smoke "First run through the browser installer"; InternalEndpointsTest |
| Secrets generated into .env, never echoed | VERIFIED | same step greps .env and the page |
| Fresh install: orders closed until setup complete; "أكمل إعداد المتجر" | VERIFIED | smoke 6b (422 → setup → 200); InternalEndpointsTest; setup-checklist-banner test |
| Testing-only features absent in production (testing-login, fake push) | VERIFIED | smoke 6c (404); ProductionModeTest |
| Customer journey: order → receipt upload → admin approve → customer sees confirmed → ready_for_pickup | VERIFIED | smoke 7/7b on both DBs |
| Other customer cannot read the order; non-image receipt rejected | VERIFIED | smoke 7b/7c |
| Security: core/.env/MANIFEST/writable denied; cookies HttpOnly/Secure/Lax; uploads image-only | VERIFIED | smoke 1, 8, 9 |
| SSR caching `public, max-age=60` + ETag/304; shell no-cache | VERIFIED | smoke; SeoTest |
| Maintenance switch (503 + Arabic page + Retry-After) | VERIFIED | smoke 10 |
| Upgrade zip keeps .env/writable/uploads; `/internal/upgrade` guarded + migrates | VERIFIED | deploy step "Upgrade path" |
| Lock duration from setting with Arabic forms everywhere | VERIFIED | ContentTokensTest; arabic-duration + i18n-integrity tests (caught raw `{duration}`, fixed f38159a) |
| Pages render at 360/1280 without console errors, overflow, AED | __UI__ | deploy step "Browser pass"; screenshots artifact `package-ui-*` |
| Optional sample content | VERIFIED data; ships **without photos** (paths only; CI stands in placeholders). Leave it off for the real shop | deploy installer step |
| Offline page (service worker fallback) | VERIFIED (file + SW code); airplane-mode behaviour UNVERIFIED on a real phone | smoke offline.html 200 |
| Real SMTP delivery of sign-in codes | UNVERIFIED | CI uses MAIL_DRIVER=log; must be checked on the host (phone checklist step 5) |
| Real web push to a device | UNVERIFIED | needs a real browser + VAPID on the host |
| HelioHost specifics (AllowOverride scope, extensions, limits) | UNVERIFIED | mirrored in CI (PHP-FPM, no mod_php); the installer check step reports them on the host |

## Risks (ranked)

1. **SMTP on HelioHost** — sign-in is email-code only (or Google). If mail does not send, nobody can sign in, including the admin. Test it in installer step / phone checklist step 5 before announcing the shop.
2. **HelioHost `.htaccess`/header restrictions or PHP limits** — not documented by the host; if `AllowOverride` is limited, security/cache headers or routing break. Post-deploy checks 3–4 detect it.
3. **Cron reliability / account load** — Plesk tasks every 5 minutes; a suspended or missed cron delays push/email queues. Price-lock expiry is also enforced lazily on read, so no stale lock is shown.
4. Losing `.env` (encryption.key/JWT_SECRET) — keep an offline copy (deploy guide step 6).
5. DNS propagation for `wex-technology.com` (up to 48 h) — use the temporary HelioHost address until then.
