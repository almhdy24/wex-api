# Wex Technology API (`wex-api`)

Production-grade CodeIgniter 4 REST API for **Wex Technology**, a laptop, phone, and accessories retail store located in Al-Waha Mall, Al-Shuhada, Omdurman, Sudan.

Built with clean, readable PHP (Traversy Media style), strict separation of concerns, integer-only currency math, passwordless authentication, dual SQLite/MySQL portability, price-lock expiration, manual Bankak payment verification, and asynchronous email outbox dispatching.

---

## 🌟 Key Features

1. **Passwordless Authentication:**
   - **Google Sign-In:** Authenticates Google ID tokens by verifying RS256 signatures directly against Google's public JWKS endpoint.
   - **Email OTP:** 6-digit one-time passcodes hashed with HMAC-SHA256, 10-minute validity, 60s cooldown, max 5 attempts.
   - **JWT Tokens:** HS256 access tokens (15-min TTL) paired with rotating refresh tokens (30-day TTL) featuring token family theft detection.
2. **Dual Database Portability (SQLite & MySQL 8+ / MariaDB 10.6+):**
   - 100% portable CI4 Forge migrations.
   - SQLite locally (`writable/database/wex.sqlite`) with foreign keys enabled; seamlessly switches to MySQL 8+ in production without changing queries or migrations.
   - Booleans stored as `TINYINT(1)`, JSON specs as `TEXT`, statuses as `VARCHAR` (no database ENUMs).
3. **Strict Integer-Only Money Arithmetic:**
   - Base prices stored in whole UAE Dirhams (`price_aed`).
   - Exchange rates stored as whole SDG per 1 AED (`rate_sdg`).
   - Order calculations, app discounts (3%), and delivery fees computed in integer SDG and rounded using configurable step (default: 100 SDG).
4. **Price Lock Mechanism:**
   - Freezes SDG exchange rate and prices for 4 hours when an order is created.
   - Dual enforcement: periodic CLI worker (`php spark orders:expire`) and lazy validation on receipt upload/order read.
   - Atomic stock reservations within database transactions (`WHERE stock_quantity >= ?`).
5. **Bankak Payment Proof Management:**
   - Customer uploads Bankak transfer screenshot.
   - GD image processing re-encodes images and strips all EXIF metadata.
   - Stored in private storage outside the web root (`writable/uploads/payment_proofs/`).
   - Streamed via authenticated, authorized binary streaming endpoint with ownership checks.
   - Staff review workflow (approve / reject with dynamic lock validity handling).
6. **Email Outbox Queue:**
   - Decoupled asynchronous delivery with exponential backoff retries.
   - CLI worker: `php spark mail:send --batch=20`.
   - 9 branded Arabic RTL HTML responsive templates.
7. **Operational Dashboard & Audit Trail:**
   - Real-time statistics: order counts by status, pending payment reviews, daily revenue, low stock alerts.
   - Comprehensive audit logging of all administrative actions.
   - User role and status management with self-demotion safeguards.

---

## 🚀 Quick Start (Local Development)

### Prerequisites
- PHP 8.2 or 8.5 with `pdo_sqlite`, `curl`, `intl`, `mbstring`, `gd`
- Composer 2.x

### 1. Installation
```bash
# Clone and enter the repository
cd ~/projects/wex-api

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
```

### 2. Database Migration & Seeding
```bash
# Run all 18 database migrations
php spark migrate --all

# Seed catalog, Sudan shipping zones, admin user, and settings
php spark db:seed DatabaseSeeder
```

### 3. Start the Development Server
```bash
php spark serve --port 8081
```
The API is live at `http://localhost:8081/api/v1`.

---

## 🧪 Testing

Execute the automated test suite (50 feature and unit tests, 251 assertions):
```bash
php vendor/bin/phpunit
```

Test coverage includes:
- Health check & CORS preflight (`PingTest`)
- Database migrations, casting & models (`DatabaseTest`)
- Passwordless OTP, Google OAuth & token rotation (`AuthTest`)
- Catalog filtering, sorting & stock masking (`CatalogTest`)
- Integer money conversions & rounding math (`PricingServiceTest`)
- Shop settings, rates & shipping zones (`SettingsTest`)
- Orders, price lock, stock reservation & payment proofs (`OrderTest`)
- Notifications badge count & email outbox queuing (`NotificationTest`)
- Admin operational dashboard, users & audit logs (`AdminTest`)

---

## 🛠️ CLI Commands

| Command | Purpose |
|---|---|
| `php spark orders:expire` | Expires overdue price-locked orders and returns reserved stock to inventory |
| `php spark mail:send [--batch=20]` | Dispatches pending outbound emails via SMTP with retry tracking |
| `php spark admin:create` | Creates an administrator or staff user (`--name`, `--email`, `--phone`, `--role`) |
| `php spark migrate` | Runs pending database migrations |
| `php spark db:seed DatabaseSeeder` | Seeds initial shop data, inventory, and default rates |

---

## 🔑 Default Seeded Accounts

| Role | Email | Purpose |
|---|---|---|
| **Admin** | `admin@wextechnology.sd` | Full system access, catalog, pricing, settings, users, audit logs |
| **Staff** | `staff@wextechnology.sd` | Operational dashboard, order status updates, payment proof reviews |
| **Customer** | `customer@example.com` | Browsing catalog, placing orders, uploading Bankak receipts |

*Note: Since the API is passwordless, authenticate in development by requesting an OTP (`POST /api/v1/auth/email/request`) and retrieving the 6-digit code logged in `writable/logs/emails/`.*

---

## 📚 Project Documentation

- [REST API Endpoints Reference](docs/API.md)
- [Architecture & Design Decisions](docs/DECISIONS.md)
- [Database Schema & ERD](docs/DATABASE.md)
- [Production Deployment Guide](docs/DEPLOYMENT.md)
- [Verified Curl Examples](docs/curl-examples.md)
