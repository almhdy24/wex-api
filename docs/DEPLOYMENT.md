# Wex Technology API - Deployment Guide

This guide covers deploying the Wex Technology REST API to production environments:
1. **Shared Hosting (cPanel / Apache / PHP 8.2+)**
2. **VPS / Cloud (Ubuntu 22.04+ / Nginx / PHP 8.2+ / MySQL 8+ or MariaDB 10.6+)**
3. **Automated Cron Workers (Price-Lock Expiry & Email Outbox Queue)**

---

## 1. System Requirements

- **PHP:** 8.2 or higher (CodeIgniter 4.7+ compatible).
- **Required PHP Extensions:**
  - `ext-curl`
  - `ext-intl`
  - `ext-json`
  - `ext-mbstring`
  - `ext-openssl`
  - `ext-gd` (for image processing, EXIF stripping, and thumbnail creation)
  - `ext-pdo` & `ext-mysqli` (for MySQL / MariaDB) or `ext-sqlite3` (for local development)
- **Database:** MySQL 8.0+ / MariaDB 10.6+ in production; SQLite 3.35+ for local development.
- **Web Server:** Apache with `mod_rewrite` enabled, or Nginx.
- **Composer:** v2.x.

---

## 2. Directory Layout & Web Root Protection

Security Rule: **Only the `public/` folder must ever be accessible via HTTP.**

```text
/home/username/
├── wex-api/                 <-- All source code (NOT accessible via browser)
│   ├── app/
│   ├── vendor/
│   ├── writable/            <-- Must be writable by web server (chmod 775)
│   │   ├── cache/
│   │   ├── logs/
│   │   └── uploads/
│   │       ├── products/    <-- Public thumbnails & gallery
│   │       └── payment_proofs/ <-- STRICTLY PRIVATE (streamed via API only)
│   ├── .env                 <-- Environment secrets (chmod 600)
│   ├── spark
│   └── public/              <-- WEB ROOT (accessible via domain/subdomain)
│       ├── index.php
│       ├── .htaccess
│       └── uploads/         <-- Symlinked or mapped to writable/uploads/products
```

---

## 3. Deployment Steps: Shared Hosting (cPanel)

### Step 3.1: Upload Project Files
1. Place the repository in a directory outside the cPanel `public_html`, e.g., `/home/username/wex-api`.
2. Ensure directory permissions:
   ```bash
   chmod -R 755 /home/username/wex-api
   chmod -R 775 /home/username/wex-api/writable
   ```

### Step 3.2: Configure Web Root
- In cPanel, configure your domain or subdomain (e.g., `api.wextechnology.sd`) to point directly to `/home/username/wex-api/public`.
- If your shared host does not permit changing DocumentRoot, create a symlink:
  ```bash
  rm -rf /home/username/public_html
  ln -s /home/username/wex-api/public /home/username/public_html
  ```

### Step 3.3: Production `.env` Setup
Copy `.env.example` to `.env` in the project root:
```bash
cp .env.example .env
chmod 600 .env
```

Edit `.env` with production credentials:
```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://api.wextechnology.sd/'
app.forceGlobalSecureRequests = true

# MySQL 8+ / MariaDB 10.6+ Database
database.default.hostname = localhost
database.default.database = wex_production
database.default.username = wex_dbuser
database.default.password = 'STRONG_RANDOM_PASSWORD_HERE'
database.default.DBDriver = MySQLi
database.default.DBPrefix = 
database.default.port     = 3306
database.default.charset  = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

# JWT Secret (Generate with: openssl rand -hex 32)
JWT_SECRET = 'd41d8cd98f00b204e9800998ecf8427e5b6f3c1a2e8d9f0a4b7c8e9f1a2b3c4d'
JWT_ACCESS_TTL = 900
JWT_REFRESH_TTL = 2592000

# CORS Allowed Origins (Web and Mobile app origins, comma-separated)
CORS_ALLOWED_ORIGINS = 'https://wextechnology.sd,https://admin.wextechnology.sd,http://localhost:3000'

# Outbound Email SMTP
mail.protocol = smtp
mail.SMTPHost = mail.wextechnology.sd
mail.SMTPUser = no-reply@wextechnology.sd
mail.SMTPPass = 'STRONG_EMAIL_PASSWORD'
mail.SMTPPort = 465
mail.SMTPCrypto = ssl
mail.fromEmail = no-reply@wextechnology.sd
mail.fromName = 'Wex Technology'

# Google OAuth Client ID (from Google Cloud Console)
GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_WEB_CLIENT_ID.apps.googleusercontent.com'
```

### Step 3.4: Run Migrations and Initial Seeder
Run the CLI tool from the project root:
```bash
php spark migrate --all
php spark db:seed DatabaseSeeder
```

### Step 3.5: Create Super Admin Account
Create your primary administrator account:
```bash
php spark admin:create --name="المدير العام" --email="admin@wextechnology.sd" --phone="0912300000" --role=admin
```

---

## 4. Apache `.htaccess` Configuration

Verify `public/.htaccess` includes rewriting rules and blocks sensitive file access:

```apache
<IfModule mod_rewrite.c>
    Options -Indexes
    RewriteEngine On

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L]
</IfModule>

<IfModule !mod_rewrite.c>
    ErrorDocument 404 /index.php
</IfModule>

# Block access to hidden files and env
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

---

## 5. Background Workers & Cron Jobs

Wex API relies on two automated CLI background tasks:
1. **Price-Lock Expiry Cleaner:** Checks for unpaid orders older than the lock window (e.g. 4 hours), marks them `expired`, and returns reserved stock.
2. **Email Outbox Dispatcher:** Reads pending emails from `email_outbox` and sends them via SMTP with exponential backoff retries.

### Crontab Setup
Open crontab:
```bash
crontab -e
```

Add the following entries (running every minute):
```cron
# Expire overdue price-locked orders every 2 minutes
*/2 * * * * cd /home/username/wex-api && php spark orders:expire >> /home/username/wex-api/writable/logs/cron_orders.log 2>&1

# Process queued email outbox batches every minute
* * * * * cd /home/username/wex-api && php spark mail:send --batch=25 >> /home/username/wex-api/writable/logs/cron_mail.log 2>&1
```

---

## 6. Verification & Health Monitoring

### Health Check Endpoint
```bash
curl -i https://api.wextechnology.sd/api/v1/ping
```
Expected: `200 OK` with `success: true` and `env: "production"`.

### Operational Dashboard Check
```bash
curl -i https://api.wextechnology.sd/api/v1/admin/dashboard \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

### Log Files
- Application Errors: `writable/logs/log-YYYY-MM-DD.log`
- Email Worker: `writable/logs/cron_mail.log`
- Order Expire Worker: `writable/logs/cron_orders.log`

---

## 7. Backup & Maintenance Procedures

1. **Database Daily Backup:**
   ```bash
   mysqldump -u wex_dbuser -p wex_production > /home/username/backups/wex_db_$(date +\%F).sql
   ```
2. **File Storage Backup:**
   Regularly archive `writable/uploads/` (containing product images and Bankak receipts).

---

## 8. File Upload Configuration & Limits

For Bankak payment transfer receipts and product images:

### PHP Configuration (`php.ini` or `/etc/php/8.x/fpm/conf.d/99-uploads.ini`)
```ini
file_uploads = On
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_file_uploads = 5
```

### Nginx Configuration
```nginx
client_max_body_size 12M;
```

### Application Limit (`.env`)
```ini
UPLOAD_MAX_MB = 5
```
Uploads exceeding `UPLOAD_MAX_MB` or `post_max_size` trigger a standardized HTTP 413 error with the Arabic message:
`"حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى"`.

---

## 9. Cookie Configuration & Same-Site Requirements (Customer Login Persistence)

Mobile browsers (Android Chrome, iOS Safari) strictly block or restrict third-party cross-site cookies. For persistent 30-day customer sessions to function reliably on mobile devices:

### 1. Same-Site Domain Architecture (Recommended)
Host the storefront web application and the REST API under the same registrable domain (eTLD+1), or proxy `/api` on the same origin:
- Web Storefront: `https://wextechnology.sd` (or `https://wex.example.com`)
- API Server: `https://api.wextechnology.sd` (or reverse-proxied at `https://wextechnology.sd/api`)

Under this architecture, cookies are treated as first-party same-site cookies:
```ini
COOKIE_SAMESITE = Lax
COOKIE_SECURE = true
```

### 2. Cross-Site Deployment (If APIs reside on separate domain)
If the web storefront and API are on different domains (e.g., `https://wex-storefront.pages.dev` and `https://api-render.com`):
```ini
COOKIE_SAMESITE = None
COOKIE_SECURE = true
```
> [!WARNING]
> `SameSite=None` requires HTTPS and `COOKIE_SECURE=true`. Modern mobile browsers will silently drop `SameSite=None` cookies on HTTP or when third-party cookie restrictions are active.

### 3. Session Lifetime & Concurrency Keys (`.env`)
```ini
# Sliding refresh token TTL (default 30 days)
JWT_REFRESH_TTL_DAYS = 30

# Absolute lifetime ceiling for a token family (default 180 days)
JWT_REFRESH_MAX_LIFETIME_DAYS = 180

# Replay grace window for rotated tokens (default 30 seconds)
# Prevents session termination when concurrent tabs or mobile network retries present the old token
REFRESH_REUSE_GRACE_SECONDS = 30
```

