# Deploying Wex on HelioHost (single directory, no SSH)

The site (React app) and the API are served from **one** web root. On HelioHost's Plesk the web root folder is called **`httpdocs`**. Elsewhere it is often `public_html`. Wherever this guide says *web root*, use whichever your host has.

## What HelioHost provides (checked 2026-11-25)

| Item | Finding | Source |
|---|---|---|
| PHP | 8.3, 8.4 and 8.5 on Tommy, Johnny and Morty (Lily: up to 8.5). Wex requires **8.3+**. Select it in Plesk > Websites & Domains > Dev Tools > PHP. A version change can take up to 2 hours. | [wiki/PHP](https://wiki.helionet.org/PHP) |
| `memory_limit`, `max_execution_time` | "Intentionally limited", no published values (**UNVERIFIED**). The cron runner is time-boxed to 20 s (`CRON_TIME_BUDGET_SECONDS`) and uploads are capped at 5 MB / 40 MP. | [wiki/PHP](https://wiki.helionet.org/PHP), [forum](https://helionet.org/index/topic/41666-solved-changing-php-memory-limit-and-max-execution-time/) |
| Extensions gd, intl, mbstring, mysqli | Not listed on the wiki (**UNVERIFIED**). Check the phpinfo page for your PHP version before installing. `api/index.php` refuses to start on PHP < 8.3. | wiki/PHP |
| Database | MariaDB (and PostgreSQL) via Plesk, with phpMyAdmin. Use **MariaDB**. | [wiki/Plesk_Features](https://wiki.helionet.org/Plesk_Features) |
| SSH | Off. Plesk Scheduled Tasks can run jailed commands. | [wiki/FAQ](https://wiki.helionet.org/FAQ) |
| Cron | Plesk Scheduled Tasks: run a command, or "Fetch a URL". No count limit, but tasks count toward account load, and running every minute risks suspension. **Use every 5 minutes.** | [wiki/Cron_Jobs](https://wiki.helionet.org/Cron_Jobs) |
| Upload size | Raising `upload_max_filesize` / `post_max_size` is by request on the forum. Wex needs ≤ 5 MB per image. | wiki/PHP |
| `.htaccess` / mod_rewrite / mod_headers | The server is Apache (the wiki mentions Apache restarts). `AllowOverride` scope is **UNVERIFIED**. Post-deploy check 4 below tests it. | — |

## Package layout (`wex-deploy-<version>.zip`)

```
<web root>/
  index.html, assets/, sw.js, manifest.webmanifest, icons…   React build
  .htaccess                     HTTPS, routing, security + caching headers
  api/index.php, api/.htaccess  API front controller (/api/v1/...)
  uploads/products/             public product images (no script execution)
  _wex_core/                    app/, vendor/, writable/, .env  (denied to the web)
  MANIFEST.txt                  sha256 of every file (blocked from the web)
```

The core folder name defaults to `_wex_core`. Build with a custom name in CI with `scripts/package-deploy.sh <wex-web> <version> <name>`. Receipts live in `_wex_core/writable/uploads/proofs` and are only streamed by PHP after the owner/admin check.

Build the zip: GitHub > wex-api > Actions > "Deploy package (HelioHost layout) + smoke test" > Run workflow (or push a `v*` tag), then download the `wex-deploy-…` artifact. Every build is smoke-tested in Apache + MariaDB before it becomes an artifact.

## First deployment (numbered steps)

1. **Plesk > Websites & Domains > PHP**: select PHP 8.3 (or 8.4). Open its phpinfo and confirm `gd`, `intl`, `mbstring`, `mysqli`.
2. **Plesk > Databases > Add Database**: MariaDB, name e.g. `wex`, create a user with a strong password. Note the host (usually `localhost`), database, user and password.
3. **Plesk > File Manager**: open the web root (`httpdocs`). Remove the default placeholder files. Upload `wex-deploy-<version>.zip` and **Extract** it there. The web root must now contain `index.html`, `api/`, `_wex_core/`.
4. In `_wex_core/`, copy `.env.example` to **`.env`** and edit it:
   - `app.baseURL = 'https://wex-technology.com/'`, `APP_URL = 'https://wex-technology.com'`
   - the `database.default.*` values from step 2
   - `encryption.key = hex2bin:<64 hex chars>` and `JWT_SECRET = <64 hex chars>` (generate with any password generator: 64 characters of 0-9a-f)
   - `GOOGLE_CLIENT_IDS` (the web client ID), the SMTP settings, and the VAPID keys (generate once locally or in CI with `php spark push:keys`)
   - `CRON_TOKEN = <64 random chars>` and `INSTALL_TOKEN = <64 random chars>`
5. **Install** (pick one):
   - **Without a shell (web installer):** from any computer, run
     `curl -X POST https://wex-technology.com/api/v1/internal/install -H "X-Install-Token: <INSTALL_TOKEN>" -H "Content-Type: application/json" -d '{"admin_email":"you@example.com","admin_name":"صاحب المتجر"}'`
     It runs all migrations and creates your admin account (you sign in with an email code or Google). It refuses without the token, and refuses forever after success (`_wex_core/writable/install.lock`).
   - **With a Plesk Scheduled Task (CLI):** a one-off task running `/opt/plesk/php/8.3/bin/php -f httpdocs/_wex_core/spark migrate --all`, then `… spark admin:create` (follow the prompts in the task output).
6. **Delete `INSTALL_TOKEN`** from `.env` (the installer is then invisible: 404).
7. **SSL**: Plesk > SSL/TLS Certificates > Let's Encrypt for `wex-technology.com` and `www`. The `.htaccess` redirects HTTP to HTTPS, and HSTS is sent on HTTPS.
8. **Domain**: point `wex-technology.com` (A/AAAA records, or HelioHost nameservers) to the account. Add it in Plesk if it isn't the main domain.
9. **Periodic jobs**, every 5 minutes (pick one):
   - **Plesk Scheduled Task, Run a command (preferred, no token in logs):** `/opt/plesk/php/8.3/bin/php -f httpdocs/_wex_core/spark cron:run`
   - **Plesk Scheduled Task, Fetch a URL:** set `CRON_ALLOW_QUERY_TOKEN = true` in `.env`, then use URL `https://wex-technology.com/api/v1/internal/cron?token=<CRON_TOKEN>` (the token appears in access logs: rotate it if logs are shared).
   - **External pinger** (e.g. cron-job.org): `POST https://wex-technology.com/api/v1/internal/cron` with header `X-Cron-Token: <CRON_TOKEN>`.

   One pass runs: expire overdue price locks (and restore stock), send queued push notifications and emails, lock-expiry reminders, and a daily integrity check (violations are logged as critical). Overlapping runs are rejected (409). Orders also expire lazily whenever they are read, so a missed run never leaves a stale lock visible.
10. **Store settings**: sign in at `/login` with the admin email, then open Admin > Settings. Set the shop phone, WhatsApp, address, working hours, map link, payment accounts, the exchange rate, and shipping zones. Contact links stay hidden until real numbers are set.

## Post-deploy checks

1. `https://wex-technology.com/api/v1/ping` returns JSON with `"success": true`.
2. `https://wex-technology.com/_wex_core/.env` and `https://wex-technology.com/.env` return **403/404** (never the file).
3. `https://wex-technology.com/products/<any-slug>` and `/admin/products` load the app (not an Apache 404).
4. Browser devtools > Network > `index.html` shows `Cache-Control: no-cache` and a `Content-Security-Policy`. If these headers are missing, mod_headers / AllowOverride is restricted: ask HelioHost support.
5. Admin > Settings shows your values; the storefront footer shows your phone/WhatsApp.
6. Place a test order, upload a receipt, approve it in admin, and confirm the customer sees "confirmed".
7. After 5–10 minutes, `_wex_core/writable/cron_state.json` shows a recent `last_run`.

## Phone smoke checklist (10 steps, on mobile data)

1. Open the home page: it loads in a few seconds, shows products, and the Arabic text reads right-to-left.
2. Search for a brand in Arabic and in Latin spelling (e.g. «ديل» / "Dell"): results appear.
3. Open a product: price in ج.س, condition and availability shown, WhatsApp share works.
4. Add to cart, go to checkout, choose delivery: the zone shows its fee and the total includes it.
5. Sign in with an email code: the code arrives, and login keeps you on checkout.
6. Confirm the order: you land on the receipt upload with bank accounts, copy buttons and the countdown.
7. Upload a receipt photo from the gallery: the status becomes "قيد المراجعة".
8. In the admin on the phone: the order appears with the receipt. Approve it.
9. Back as the customer: the order shows as confirmed. A push notification arrives if enabled.
10. Add to home screen (PWA), open it offline: the offline page appears, not a browser error.

## Upgrades

- Build a new zip. In the web root, **replace** `index.html`, `assets/`, `sw.js`, the manifest and icons, `api/`, `.htaccess`, `uploads/.htaccess`, and inside `_wex_core/` replace `app/`, `vendor/`, `spark` and `.htaccess`.
- **Never overwrite** `_wex_core/.env`, `_wex_core/writable/`, or `uploads/products/` (contents).
- Then run migrations: a one-off Scheduled Task `/opt/plesk/php/8.3/bin/php -f httpdocs/_wex_core/spark migrate --all`, or temporarily set a fresh `INSTALL_TOKEN`, delete `writable/install.lock`, re-run the installer with the existing admin email (migrations only; the admin is kept), then remove the token.
- `MANIFEST.txt` lists every file's sha256, so you can compare what changed.
- Open the site once: the service worker picks up the new build (the app shell is `no-cache`).

## Rollback

Keep the previous zip. Re-extract its files over the same paths (same exclusions as Upgrades). Migrations are additive, so the old code runs on the newer schema. If a migration must be undone, restore the database backup taken before the upgrade.

## Backups

- Database: weekly, and before every upgrade, use Plesk > Databases > Export Dump (or phpMyAdmin > Export).
- Files: `_wex_core/.env`, `_wex_core/writable/uploads/proofs/` (receipts) and `uploads/products/`. Download them via the File Manager (Archive > Download).
- Test a restore once on a spare database.

## Split hosting (optional)

If the API ever lives on another origin, set `CORS_ALLOWED_ORIGINS` to the web origin, `COOKIE_SAMESITE=None` with `COOKIE_SECURE=true`, and build the web app with `VITE_API_BASE_URL=https://api.example.com/api/v1`.
