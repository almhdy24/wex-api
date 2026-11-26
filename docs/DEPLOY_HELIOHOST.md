# Deploying Wex on HelioHost (single directory, no SSH)

The storefront (React build) and the API (CodeIgniter 4) are served from **one** web root.
HelioHost's Plesk calls it `httpdocs` on some accounts and `public_html` on others: wherever this
guide says *web root*, use the folder Plesk shows for the domain.

## 1. HelioHost findings (checked 2026-11-25/26)

| Item | Finding | Consequence for Wex | Source |
|---|---|---|---|
| PHP | 8.3 / 8.4 / 8.5 selectable per domain (Tommy, Johnny, Morty). PHP runs as **CGI/FastCGI**, not mod_php. Version change can take up to 2 h. | Wex needs 8.3+; CI smoke runs Apache + **PHP-FPM 8.3** (no mod_php) to match. `php_flag` lines are wrapped in `<IfModule mod_php…>` (they would 500 under CGI). The `Authorization` header is re-exported by `.htaccess` (CGI drops it). | [wiki/PHP](https://wiki.helionet.org/PHP) |
| `exec`, `proc_open`, `shell_exec` | Disabled. | Nothing in Wex shells out. Installer, migrations, secrets and cron all run in-process. | wiki/PHP, forum |
| `memory_limit`, `max_execution_time` | "Intentionally limited", values not published (**UNVERIFIED**; the installer's check step shows them). | Cron is time-boxed (`CRON_TIME_BUDGET_SECONDS = 20`), uploads capped at 5 MB / 40 MP. | wiki/PHP |
| Extensions gd, intl, mbstring, mysqli, openssl | Not listed (**UNVERIFIED**). | The installer check step lists each one with ✓/✗ before anything is written. | — |
| Database | MariaDB (Plesk > Databases, phpMyAdmin). | CI tests the package on **MariaDB 10.6** and **MySQL 8.0** (strict SQL, utf8mb4). | [wiki/Plesk_Features](https://wiki.helionet.org/Plesk_Features) |
| SSH | Off. | Browser installer at `/api/install`; upgrades via `POST /api/v1/internal/upgrade`. | [wiki/FAQ](https://wiki.helionet.org/FAQ) |
| Cron | Plesk Scheduled Tasks ("Run a command/PHP script" or "Fetch a URL"). "Fetch a URL" **cannot send headers**. Every-minute tasks risk account suspension. | Run **every 5 minutes**. For "Fetch a URL" use the `?token=` form with `CRON_ALLOW_QUERY_TOKEN = true`. Price locks also expire lazily on read. | [wiki/Cron_Jobs](https://wiki.helionet.org/Cron_Jobs) |
| `.htaccess` | Apache with `.htaccess`; exact `AllowOverride` scope **UNVERIFIED**. | Post-deploy check 4 detects a restricted `AllowOverride` (missing headers). | — |
| Nameservers | `ns1.heliohost.org`, `ns2.heliohost.org`. | Step 9. | HelioHost signup / wiki |

## 2. What is in the release zip

`wex-technology-release-<version>.zip` (built and tested only in GitHub Actions, workflow
**"Deploy package (HelioHost layout) + smoke test"**), with `….zip.sha256` and
`wex-technology-upgrade-<version>.zip` next to it.

```
<web root>/
  index.html, assets/, sw.js, manifest.webmanifest, icons     React build
  offline.html, maintenance.html  static pages (offline fallback, 503 page)
  .htaccess                     HTTPS, routing, SSR routes (/, /catalog, /faq, /products/*, /pages/*,
                                sitemap.xml, robots.txt), security + cache headers, maintenance switch
  api/index.php, api/.htaccess  API front controller (/api/v1/…, /api/install)
  uploads/                      public product images; only images are served, nothing executes
  _wex_core/                    app/, vendor/, writable/, .env.example  (denied to the web)
  MANIFEST.txt                  version, commits, build time, sha256 of every file (blocked from the web)
```

Before a zip is published, CI:
- scans it with `scripts/verify-package.sh` (no SQLite/logs/.env/tests/dev seeders, no
  secrets or private keys, no placeholder phone numbers or domains). A self-test plants each
  kind of violation and requires the scanner to reject it;
- installs it on Apache + PHP-FPM 8.3 with MariaDB 10.6 **and** MySQL 8.0 through the browser
  installer, runs `scripts/smoke-deploy.sh` (security, caching, first-run setup, full order
  journey, permissions, uploads, maintenance), applies the upgrade zip on top, and runs a
  Playwright pass at 360×800 and 1280×800 (fails on console errors, horizontal overflow, AED).

### Download it to the phone

```sh
termux-setup-storage            # once, grants ~/storage/downloads
cd ~/storage/downloads
RUN=$(gh run list -R <owner>/wex-api -w "Deploy package (HelioHost layout) + smoke test" -b stabilize -s success -L1 --json databaseId -q '.[0].databaseId')
gh run download $RUN -R <owner>/wex-api -p 'wex-technology-release-*'
cd wex-technology-release-* && sha256sum -c wex-technology-release-*.zip.sha256
```

Tagged builds (`git tag v1.0.0 && git push --tags`) also attach the zips to a GitHub Release:
`gh release download v1.0.0 -R <owner>/wex-api -D ~/storage/downloads`.

## 3. First deployment (numbered steps)

1. **PHP**: Plesk > Websites & Domains > PHP: select **8.3** (or 8.4). Wait until the version is active.
2. **Database**: Plesk > Databases > Add Database: MariaDB, name e.g. `wex`, a new user with a
   strong password. Note host (usually `localhost`), database, user, password.
3. **Mailbox for sign-in codes**: Plesk > Mail > create `noreply@wex-technology.com` (or use any
   SMTP provider). Sign-in is by email code (or Google), so **SMTP must work** before launch.
4. **Upload**: Plesk > File Manager > web root. Delete the default placeholder files. Upload
   `wex-technology-release-<version>.zip` and choose **Extract**. The web root must now contain
   `index.html`, `api/`, `_wex_core/`, `MANIFEST.txt`. Delete the zip afterwards.
5. **.env**: in `_wex_core/`, copy `.env.example` to `.env` and edit only these (checklist):
   - [ ] `app.baseURL = 'https://wex-technology.com/'` and `APP_URL = 'https://wex-technology.com'`
   - [ ] `database.default.hostname / database / username / password` from step 2
   - [ ] `MAIL_FROM_EMAIL`, `email.SMTPHost` (e.g. `mail.wex-technology.com`), `email.SMTPUser`, `email.SMTPPass`, port 587 + tls (or 465 + ssl)
   - [ ] `GOOGLE_CLIENT_IDS` (optional; Google sign-in)
   - [ ] `INSTALL_TOKEN = <random, 32+ chars>` (any password generator)
   - [ ] leave `encryption.key`, `JWT_SECRET`, `CRON_TOKEN`, `VAPID_*` **empty**: the installer fills them
   - [ ] `CI_ENVIRONMENT = production` (already set; do not change)
6. **Installer**: open `https://wex-technology.com/api/install` (or the temporary HelioHost
   address if DNS is not ready). Enter the install token.
   - Check step: every row ✓ (PHP version, extensions, writable folders, HTTPS, upload/memory
     limits) and the database connection succeeds. Fix any ✗ first.
   - Press **«توليد المفاتيح الناقصة»**: missing secrets are written into `.env` (shown once
     only if `.env` is not writable; then paste them in by hand). Download a copy of `.env`
     and keep it offline: losing `encryption.key` or `JWT_SECRET` cannot be undone.
   - Enter the **admin email** (you sign in with a code sent to it) and press install.
     Leave «محتوى تجريبي» **unchecked** for the real shop. The shop starts with **orders closed**.
7. **Delete the installer**: remove the `INSTALL_TOKEN` line from `.env`. `/api/install` then
   returns 404 (it already returns 410 after the install, because of `writable/install.lock`).
8. **SSL**: Plesk > SSL/TLS Certificates > Let's Encrypt for `wex-technology.com` and
   `www.wex-technology.com`. `.htaccess` redirects HTTP to HTTPS and sends HSTS.
9. **Domain**: at the registrar of `wex-technology.com` set the nameservers to
   **`ns1.heliohost.org`** and **`ns2.heliohost.org`** (propagation can take up to 24–48 h).
   Add the domain in Plesk if it is not the account's main domain. Re-run step 8 once it resolves.
10. **Cron, every 5 minutes** (Plesk > Scheduled Tasks > Add Task), pick one:
    - "Run a PHP script": script `_wex_core/spark` (inside the web root), arguments `cron:run`; or
    - "Fetch a URL": set `CRON_ALLOW_QUERY_TOKEN = true` in `.env`, URL
      `https://wex-technology.com/api/v1/internal/cron?token=<CRON_TOKEN from .env>`
      (the token appears in access logs; rotate it if logs are ever shared); or
    - an external pinger (cron-job.org etc.): `POST …/api/v1/internal/cron` with header `X-Cron-Token`.
    One pass expires overdue price locks (restoring stock), sends queued push/email, lock-expiry
    reminders, and a daily integrity check. Overlapping runs get 409.
11. **Store setup**: sign in at `/login` with the admin email. The dashboard shows
    **«أكمل إعداد المتجر»** with what is missing. Required before orders can open: WhatsApp/phone,
    shop address, working hours, at least one active payment account, the exchange rate.
    Recommended: shipping zones, map link, price-lock hours. Add products and photos.
    Then Admin > Settings > open orders (the server refuses until the required items are done).
12. **Push (VAPID)**: keys were generated in step 6. Allow notifications on your phone, then
    check a status change on a test order notifies you. If the keys are missing (host openssl
    without EC support), generate them in CI with `php spark push:keys` and paste them into `.env`.
13. **Verify**: run the post-deploy checks (§4) and the phone smoke checklist (§5).

## 4. Post-deploy checks

1. `https://wex-technology.com/api/v1/ping` returns JSON with `"success": true`.
2. `/_wex_core/.env`, `/.env`, `/MANIFEST.txt`, `/_wex_core/writable/` return **403/404**.
3. `/products/<slug>`, `/catalog`, `/admin/products` load the app (no Apache 404).
4. Devtools > Network: `/` has `Cache-Control: public, max-age=60, must-revalidate` + `ETag`
   and a `Content-Security-Policy`; `/admin/products` has `no-cache`. Missing headers mean
   `AllowOverride`/mod_headers is restricted: ask HelioHost support.
5. `/api/install` returns 404 (token removed).
6. `/uploads/products/<file>.jpg` loads; any `.php`/`.svg`/`.html` under `/uploads/` is 403.
7. After 10 minutes `_wex_core/writable/cron_state.json` shows a recent `last_run`.

## 5. Phone smoke checklist (12 steps, ~5 minutes, on mobile data)

1. Home page loads in a few seconds, Arabic right-to-left, prices in ج.س (no AED anywhere).
2. Search a brand in Arabic and Latin (e.g. «ديل» / "Dell"): results appear.
3. Open a product: photos open full-screen and swipe; condition and availability are shown.
4. Add to cart, checkout, choose delivery: the zone fee is added to the total.
5. Sign in with an email code: the code arrives within a minute; you return to checkout.
6. Confirm the order: the receipt page shows the bank account(s), copy buttons and the price-lock countdown (hours match the setting).
7. Upload a receipt photo from the gallery: the status becomes «قيد المراجعة».
8. Admin on the phone (`/admin`): the order appears with the receipt; approve it. Pages fit the screen, no sideways scrolling.
9. Back as the customer: the order shows as confirmed; a push notification arrives if allowed.
10. Admin > Settings: turn orders off, refresh the storefront: checkout is blocked with the closed notice. Turn orders back on.
11. Open `/faq` and `/pages/terms`: the lock duration, shop address and hours are your real values (no `{{…}}`, no «—»).
12. Add to home screen, turn on airplane mode, reopen: the offline page appears, not a browser error.

## 6. Maintenance mode

Create an empty file named `.maintenance` in the web root (File Manager > New File). Every page
and API call returns **503** with the Arabic maintenance page and `Retry-After: 600` (search
engines keep the index). Delete the file to reopen.

## 7. Upgrades

1. Back up (§9) and turn on maintenance mode (§6).
2. Upload `wex-technology-upgrade-<version>.zip` to the web root and **Extract**, overwriting.
   This zip never contains `_wex_core/.env`, `_wex_core/writable/` or uploaded images, so live
   data, receipts, sessions and the install lock are untouched (CI checks this).
3. Delete `.maintenance`, then run migrations from any computer or phone:
   `curl -X POST -H "X-Cron-Token: <CRON_TOKEN>" https://wex-technology.com/api/v1/internal/upgrade`
   → `"success": true` and «تم تحديث قاعدة البيانات.» (403 without the token; rate-limited).
4. The app shell is `no-cache`, so phones pick up the new build on the next visit.
5. Compare `MANIFEST.txt` (version, commits, sha256 per file) to see what changed.

## 8. Rollback

Keep the previous upgrade zip and the database dump taken in §7 step 1. Turn on maintenance,
extract the previous upgrade zip over the web root, and if the new version ran migrations,
restore the dump (Plesk > Databases > Import Dump). Migrations are additive, so the database
restore is only needed if the old code misbehaves on the newer schema. Delete `.maintenance`.

## 9. Backups

- Database: weekly and before every upgrade: Plesk > Databases > Export Dump (or phpMyAdmin > Export).
- Files: `_wex_core/.env` (secrets), `_wex_core/writable/uploads/proofs/` (receipts),
  `uploads/products/` (product photos): File Manager > select > Archive > Download.
- Restore-test once on a spare database.

## 10. Split hosting (optional)

If the API ever lives on another origin, set `CORS_ALLOWED_ORIGINS` to the web origin,
`COOKIE_SAMESITE = None` with `COOKIE_SECURE = true`, and build the web app with
`VITE_API_BASE_URL=https://<api-domain>/api/v1`.
