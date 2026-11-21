# Wex Technology API - Project Progress Tracker

## Stabilization Sprint (Branch: stabilize) — Active

- [x] **Phase 1: Behavioral Checks (RED Baseline)**
  - Built `scripts/audit-db.sh` for database claims, sample data, and `WEX-DBG-%` audit (RED on 15 rows)
  - Built `tests/unit/ClaimsScanTest.php` in PHPUnit scanning seeds, migrations, language files, and email templates (RED on 6 violations)
  - Synced E2E journey test `scripts/e2e.sh` verifying payment review lists and image streaming (RED on missing fields)
  - Documented baseline failure logs in `docs/STABILIZE-LOG.md`.

- [x] **Phase 2: P0 Fixes (All GREEN)**
  - Admin Payments List Payload: Updated `AdminOrderPresenter::presentListItem` and `OrderService::listAdminOrders` to include joined customer information (`customer_name`, `customer_phone`), integer amounts (`amount`, `expected_amount`), and proof records (`payment_proofs`, `proofs`).
  - Proof Image Streaming: Verified `/api/v1/orders/{orderNumber}/payment-proof/{proofId}/image` streams proof images with Bearer auth, correct image MIME type, and exact file size matching storage.
  - Rate Limiting: Exempted local loopback (`127.0.0.1`) under `ENVIRONMENT === 'development'` in `ThrottleFilter` and `OtpService` to unblock automated testing while preserving production rate limits.
  - Verified with `bash scripts/e2e.sh` passing all 8 steps. Full raw verification logs in `docs/STABILIZE-LOG.md`.

- [x] **Phase 3: Data and Content Cleanup (All GREEN)**
  - Dev & Debug Data Cleanup Script: Created `scripts/cleanup-dev-data.sh` with production environment check (refuses when `CI_ENVIRONMENT=production`), deleting `WEX-DBG-%` orders, proofs, test files, and test users.
  - Purged Placeholder Bank Data: Removed placeholder account `2849102` from `SettingsSeeder.php`, `OrderService.php`, `2026-11-20-000002_CreatePaymentAccountsAnnouncementsAndContentTables.php`, and email templates (`lock_warning.php`, `order_created.php`).
  - DevSeeder: Created `app/Database/Seeds/DevSeeder.php` refusing to execute in production environment.
  - Claims Removal: Cleaned marketing claims and placeholders across `ProductsSeeder.php`, `hero_slides`, `pages`, and `faq_items`.
  - Content Sanitization in Services & Controllers: Updated `SettingService.php` and `SettingsController.php` inspection and returns policies to factual text.
  - Database Audit: `scripts/audit-db.sh` reports 0 violating rows (PASS).
  - Claims PHPUnit Test: `tests/unit/ClaimsScanTest.php` reports 1 test, 1 assertion passed (PASS).
  - Test Suite: Full test suite passing with 100 tests and 4413 assertions (PASS).

## (a) Phase Checklist

- [x] **Phase 0: Setup**
  - Create CodeIgniter 4 project with `composer create-project`
  - Install `firebase/php-jwt`
  - Git repository initialization and `.gitignore` setup
  - Database configuration (SQLite with foreign keys enabled, MySQL support in prod)
  - Application configuration (`app/Config/Wex.php`)
  - Environment files (`.env`, `.env.example`)
  - Initial documentation (`README.md`, `PROGRESS.md`, `docs/DECISIONS.md`)
- [x] **Phase 1: Core Infrastructure**
  - Standard JSON ApiResponse library + BaseApiController
  - Custom ApiException classes + Global Exception Handler (RFC/JSON envelope for all errors including 404/405)
  - Filters: CorsFilter, SecurityHeadersFilter, ThrottleFilter, ForceHttpsFilter
  - Request ID tracing (`X-Request-Id`)
  - Arabic localization (`app/Language/ar/Api.php`) + English fallback (`app/Language/en/Api.php`)
  - Base routes skeleton + `GET /api/v1/ping` smoke test
  - 404 route handling with standard Arabic JSON error
  - Tests passing in `tests/feature/PingTest.php`
- [x] **Phase 2: Database Layer**
  - 18 Migrations with foreign keys, indexes, and full MySQL/SQLite portability
  - Models & Entities with type casting (JSON specs/payload, booleans, integer money, dates)
  - Seeders: Settings, ShippingZones, Categories, Brands, Products, Admin, DatabaseSeeder
  - Verification: `php spark migrate`, `php spark migrate:refresh --all`, and `php spark db:seed DatabaseSeeder`
  - Automated tests passing in `tests/feature/DatabaseTest.php`
  - Complete schema documentation in `docs/DATABASE.md`
- [x] **Phase 3: Authentication & User Management**
  - TokenService (JWT HS256 access tokens + rotating refresh token family with theft detection)
  - OtpService (HMAC-SHA256 hashed 6-digit codes, attempt counts, cooldown, single use)
  - GoogleAuthService (JWKS caching, token verification, mock injection for testing)
  - MailService (log driver writing to writable/logs/emails/ and SMTP support)
  - Arabic RTL email layout and OTP template (`app/Views/emails/`)
  - AuthService & AuthController (Google sign-in, email request, verify, refresh, logout, logout-all)
  - AuthFilter & RoleFilter (Bearer JWT verification, active check, role authorization)
  - MeController (profile view/update, Sudan phone validation/normalization, preferences, soft delete)
  - CLI command `admin:create` with option parsing
  - Automated tests passing in `tests/feature/AuthTest.php` (7 tests, 27 assertions)
- [x] **Phase 4: Product Catalog & Image Management**
  - Public endpoints: Categories, Brands, Products listing (filters/search/sort/paginate)
  - Product detail with stock level status, specs, accessories, images
  - ImageService: multi-upload, GD re-encoding (EXIF stripping), thumbnail generation
  - Admin CRUD: products, accessories linking, images management
  - Unit/Feature tests for Catalog (6 tests, 30 assertions passing)
- [x] **Phase 5: Settings, Pricing & Shipping**
  - SettingsService & ExchangeRate tracking with historical logging
  - ShippingZones CRUD & public listing (18 Sudan states)
  - PricingService: central integer math, exchange rate conversion, discount, rounding step
  - Unit tests for PricingService (4 tests, 16 assertions)
  - Feature tests for Settings, Rates, and Shipping Zones (5 tests, 39 assertions)
- [x] **Phase 6: Order Processing & Payment Proofs**
  - OrderService & atomic stock reservation in DB transactions
  - OrderStateMachine (defined valid transitions matrix)
  - Price lock dual enforcement (cron `orders:expire` + lazy check on read/upload)
  - Payment proof upload & secure authorized image streaming endpoint
  - Customer cancel & reorder endpoints
  - Admin/Staff order management & payment proof review (approve/reject)
  - CLI command `orders:expire`
  - Automated feature tests in `tests/feature/OrderTest.php` (9 tests, 45 assertions)
- [x] **Phase 7: Notifications & Email Outbox**
  - In-app notification system (unread count, mark read, mark all read, list)
  - Email outbox pattern (`email_outbox` table, exponential backoff retries)
  - Worker command `php spark mail:send`
  - Arabic RTL HTML email templates (all 9 order event templates with Wex branding)
  - User notification preferences respect (no email enqueued if `email_notifications = 0`)
  - Automated feature tests in `tests/feature/NotificationTest.php` (4 tests, 23 assertions)
- [x] **Phase 8: Admin Management & Audit Logs**
  - Admin dashboard stats (order counts, pending proofs, today sales, low stock)
  - User management (role changes, active status, spending totals)
  - Audit logging service & viewer endpoint
  - Automated tests passing in `tests/feature/AdminTest.php` (9 tests, 37 assertions)
- [x] **Phase 9: Security Audit & Hardening**
  - Complete security checklist verification (mass assignment protection, IDOR checks, upload hardening, rate limiting, token family revocation)
  - GD EXIF stripping & private payment proofs storage verified
  - Composer audit: 0 vulnerabilities found
  - Full codebase PHP linting (`php -l`): zero syntax errors
  - Comprehensive documentation completed (`docs/API.md`, `docs/DATABASE.md`, `docs/DEPLOYMENT.md`, `docs/curl-examples.md`, `docs/DECISIONS.md`, `README.md`)
- [x] **Phase 10: Final Review & Double Check**
  - Full automated test suite run (50 tests, 251 assertions, 100% passing)
  - End-to-end smoke testing with curl against running server
  - Clean clone validation check (migration refresh, seeding, CLI commands verified)
  - Release tag `v1.0.0`
- [x] **Phase A: Admin-Only Exchange Rate & AED Isolation (hide-rate branch)**
  - Migration `2026-11-20-000001_AddPricesUpdatedAtAndOrderItemSdgColumns`: added `unit_price_sdg` and `line_total_sdg` to `order_items`, backfilled existing items, added `prices_updated_at` to `settings`.
  - `PricingService`: single source of truth for SDG item calculations and order totals, `calculateOrderItemPrices`, `calculateOrderTotals` using sum of line totals, and `getPricesUpdatedAt()`.
  - `SettingService` & `ProductService`: audit hooks updating `prices_updated_at` on exchange rate changes or product AED price edits.
  - Serializers/Transformers: `PublicProductPresenter` & `AdminProductPresenter`, `CustomerOrderPresenter` & `AdminOrderPresenter`. Whitelisted fields on customer/public presenters.
  - `GET /api/v1/shop`: removed exchange rate and bank details; returns `lock_hours`, `app_discount_percent`, `prices_updated_at`, and shop metadata.
  - Email templates & Arabic strings sanitized: zero mention of "سعر الصرف" or AED in customer-facing templates.
  - Automated tests in `tests/feature/HideRateTest.php` (12 tests, 1,712 assertions) with recursive key scanner asserting zero leakage of `aed|rate|exchange|dirham` in public/customer endpoints, order totals equal sum of line totals, and order SDG immutability across rate changes.
  - Total automated tests passing: 63 tests, 2,352 assertions (100% passing).


---

## (b) What Is Done
- Initialized CodeIgniter 4.7.6 project at `/data/data/com.termux/files/home/projects/wex-api`.
- Installed `firebase/php-jwt` (^7.2) for JWT management and Google JWKS verification.
- Configured `.gitignore` to protect sensitive files, uploads, logs, and database files.
- Configured `app/Config/Database.php` with SQLite defaults, `foreignKeys = true`, and path resolution.
- Configured `phpunit.dist.xml` with test SQLite database and environment variables.
- Created `app/Config/Wex.php` containing all application constants (roles, product types, order statuses, Sudan states, default settings).
- Created `.env` and fully documented `.env.example`.
- Created directory structure for all services, controllers, entities, exceptions, and documentation.
- Created `docs/DECISIONS.md`.
- Implemented `ApiResponse` formatter and `BaseApiController`.
- Created custom `ApiException` hierarchy and `ApiExceptionHandler` returning Arabic JSON envelopes.
- Created security filters: `CorsFilter`, `SecurityHeadersFilter`, `ThrottleFilter`, `ForceHttpsFilter`.
- Added Arabic and English translation dictionaries (`app/Language/ar/Api.php`, `app/Language/en/Api.php`).
- Implemented `PingController` and verified `GET /api/v1/ping`, CORS preflight, and 404 routing with curl and PHPUnit.
- Saved working curl commands in `docs/curl-examples.md`.
- Implemented all 18 database migrations using Forge with integer money, no ENUMs, and portable schema.
- Implemented 18 Entities with automatic casting (booleans, JSON specs/details/payload, integer currencies, dates).
- Implemented 17 Models with mass-assignment protection (`$allowedFields`) and timestamps.
- Implemented Seeders: Settings, ShippingZones (18 Sudan states), Categories, Brands, Products (8 laptops + 6 accessories linked), Admin.
- Verified database rollback and re-migration with `php spark migrate:refresh --all` and seeding with `php spark db:seed DatabaseSeeder`.
- Created schema architecture document in `docs/DATABASE.md`.
- Implemented `TokenService` with JWT HS256 access tokens and rotating refresh tokens with family theft detection.
- Implemented `OtpService` with HMAC-SHA256, 10-minute expiry, 60s cooldown, 5 attempts max, single use.
- Implemented `GoogleAuthService` with Google JWKS signature checking, caching, and mock injection.
- Implemented `MailService` with log driver writing to `writable/logs/emails/` and RTL Arabic layout.
- Implemented `AuthService` and `AuthController` (Google sign-in, OTP request/verify, refresh, logout, logout-all).
- Implemented `AuthFilter` and `RoleFilter` with active status checks.
- Implemented `MeController` (profile show/update, Sudan phone normalization `+249...`, preferences, soft delete).
- Implemented `admin:create` spark command.
- Verified end-to-end with curl and PHPUnit (`tests/feature/AuthTest.php` passing 7/7 tests).
- Implemented `PricingService` with integer money conversions, SDG rounding, order calculations, and snapshot math.
- Implemented `ImageService` with MIME verification, GD re-encoding (EXIF stripping), thumbnail generation, and private payment proof storage.
- Implemented `ProductService` with public catalog queries, filtering, sorting, slug generation with soft-delete checks, stock status masking, and accessory links.
- Implemented public catalog controllers: `ProductController`, `CategoryController`, `BrandController`, `SettingsController` (`GET /shop`), `ShippingZoneController`.
- Implemented admin management controllers: `ProductAdminController`, `ProductImageAdminController`, `CategoryAdminController`, `BrandAdminController`.
- Configured routes in `app/Config/Routes.php` and updated `permittedURIChars` in `app/Config/App.php` for Arabic slugs.
- Added comprehensive automated feature tests in `tests/feature/CatalogTest.php` (6 tests, 30 assertions passing).
- Implemented `SettingService` for shop info, whitelisted admin configuration updates, and historical rate audits.
- Implemented `AuditLogService` for recording administrative actions in `audit_logs`.
- Implemented `SettingAdminController`, `RateAdminController`, and `ShippingZoneAdminController`.
- Implemented `tests/unit/PricingServiceTest.php` covering integer currency conversions, ceil rounding steps, and order breakdowns (4 tests, 16 assertions).
- Implemented `tests/feature/SettingsTest.php` covering settings view/update, rate change audit trail, and shipping zone CRUD (5 tests, 39 assertions).
- Total automated tests passing: 28 tests, 146 assertions.
- Implemented `OrderStateMachine` with strict transition matrix, status labels in Arabic, and stock release rules.
- Implemented `OrderService` with atomic stock decrement transactions (`UPDATE ... WHERE stock_quantity >= ?`), snapshot rate & prices, price-lock expiry countdown, lazy lock refresh, cancel, and reorder.
- Implemented `PaymentProofService` with private image storage, authorized binary image streaming endpoint, and admin review (approve/reject with lock validity branching).
- Implemented `OrderController`, `PaymentProofController`, `OrderAdminController`, and `PaymentAdminController`.
- Implemented CLI command `orders:expire` (`php spark orders:expire`) for cron-based cleanup of overdue price-locked orders.
- Added comprehensive feature tests in `tests/feature/OrderTest.php` (9 tests, 45 assertions passing).
- Implemented `NotificationService` and `NotificationController` with badge unread counter, single mark-read, mark-all-read, and pagination.
- Implemented `MailService::enqueue()` and `MailService::processOutbox()` with exponential backoff retry scheduling.
- Built 9 Arabic RTL HTML email templates matching Wex design standards (order created, lock warning, payment received, payment approved, payment rejected, ready for pickup, out for delivery, completed, expired).
- Implemented Spark CLI worker command `mail:send` (`php spark mail:send [--batch=20]`).
- Connected order and payment lifecycle events to in-app notifications and email outbox dispatching, respecting user notification preferences.
- Added comprehensive automated feature tests in `tests/feature/NotificationTest.php` (4 tests, 23 assertions passing).
- Total automated tests passing: 41 tests, 214 assertions.
- Updated curl documentation in `docs/curl-examples.md`.
- Implemented `DashboardController` (`GET /api/v1/admin/dashboard`) returning order statuses breakdown, pending payment review counts, today's order count & revenue in SDG, low-stock alerts, and pricing/rate info.
- Implemented `UserAdminController` (`GET /api/v1/admin/users`, `GET /api/v1/admin/users/{id}`, `PATCH /api/v1/admin/users/{id}/role`, `PATCH /api/v1/admin/users/{id}/status`) with self-modification safeguards (cannot alter own role or deactivate self).
- Implemented `AuditAdminController` (`GET /api/v1/admin/audit-logs`) with filtering by action, user ID, entity name, and date range.
- Added comprehensive automated feature tests in `tests/feature/AdminTest.php` (9 tests, 37 assertions passing).
- Total automated tests passing: 50 tests, 251 assertions (100% passing).
- Completed security hardening: confirmed mass assignment protection (`$allowedFields` on all 17 models), IDOR safeguards on customer endpoints, GD re-encoding and EXIF stripping for image uploads, private storage and authorized streaming for payment proofs, rate limit throttles on auth/upload endpoints, passwordless security with single-use hashed OTP and token family theft revocation.
- Verified dependencies with `composer audit` (0 security advisories).
- Completed complete project documentation: `docs/API.md`, `docs/DATABASE.md`, `docs/DEPLOYMENT.md`, `docs/DECISIONS.md`, `docs/curl-examples.md`, and `README.md`.
- Executed final review and acceptance verification:
  - Full test suite passed (50/50 tests, 251 assertions).
  - Live smoke tests against running API daemon verified.
  - Database rollback and re-migration cleanly tested on SQLite.
  - CLI commands `php spark orders:expire` and `php spark mail:send` verified.
  - Tagged release `v1.0.0`.

---

## (c) What Is Next
- All 10 project phases are 100% COMPLETED. Ready for handover and production deployment!

---

## (d) Decisions
- Stored all architectural choices in `docs/DECISIONS.md` (passwordless OTP/Google, integer money, no DB enums, CSRF off for Bearer API, UTC timestamps, atomic stock reservations, dual price-lock expiry, email outbox pattern).

---

## Final Review Track (Branch: final-review) — Completed

- [x] **New Admin Features & Backend Hardening**
  - **Payment Accounts**: Created `payment_accounts` table, migration `2026-11-20-000002_CreatePaymentAccountsAnnouncementsAndContentTables.php`, seeded initial account from settings. Built `PaymentAccountService` and `PaymentAccountAdminController` with admin-only access, confirmation requirement, audit logging, and email notification via outbox. Guarded order creation if no active account.
  - **Announcements**: Created `announcements` table, public endpoint `/api/v1/announcements` filtered by active window and schedule, and admin CRUD endpoints `/api/v1/admin/announcements`.
  - **Recent Purchases Ticker**: Created `/api/v1/recent-purchases` returning anonymized real data from completed orders in past 30 days, respecting master setting `ticker_enabled` and per-order flag `ticker_hidden`. Built admin hide/unhide and settings endpoints.
  - **Order-Taking Control**: Added settings `orders_enabled` and `orders_disabled_message`. Guarded `POST /api/v1/orders` to return 400 with polite Arabic message when orders are paused.
  - **Dynamic Content**: Created tables, models, and controllers for `hero_slides`, `home_sections`, `faq_items`, `pages` (drafts: privacy-policy, terms, returns, about).
  - **Brand filtering fix**: Updated `ProductService.php` to match brand filter case-insensitively by slug or name.
  - **Validation & Arabic Localization**: Created `app/Language/ar/Validation.php` translating all validation rules into Modern Standard Arabic. Purged colloquial terms from `app/Language/ar/Api.php`.
  - **Go-Live Documentation**: Created `docs/GO-LIVE.md` covering domain, HTTPS, SMTP, cron jobs, backups, and pre-launch checks.
  - **Testing**: 75/75 PHPUnit tests passing (3207 assertions, 100%).

---

## Phase A: Payment Proof Upload Fix (API)
- **Status**: Completed
- **Changes**:
  - Enforced HTTP 413 `EntityTooLargeException` with Arabic message `"حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى"`.
  - Added detection of PHP `post_max_size` overflow where `$_POST` and `$_FILES` are cleared by PHP engine.
  - Accepted nameless blobs (`filename=blob`, `filename=receipt`) without extension, resolving MIME via `finfo(FILEINFO_MIME_TYPE)`.
  - Graceful GD fallback: if image format fails GD decode, safely stores validated file instead of 500 error.
  - Rate limiting fixed: `TooManyRequestsException` properly returns 429 with Arabic message `"تجاوزت الحد المسموح من المحاولات"`.
  - Replacement support: when order is in `payment_review` and price lock is active, customer re-upload replaces prior pending proof (marked `replaced`), inserts new proof as `pending`, and logs timeline event.
  - Verified 12 reproduction cases with curl against running server; added PHPUnit unit and feature tests.

---

## Phase B: Invented Claims Removal (API)
- **Status**: Completed
- **Changes**:
  - Removed all invented warranty text from `ProductsSeeder.php` (`warranty_text` defaulted to `null` across all products).
  - Cleaned default settings and seeders: purged "ضمان معتمد", "فحص واختبار", "فحص مخبري", "وكيل تجاري/رسمي", "التوصيل الآمن", "قبل سداد أي مبلغ".
  - Added new policy settings keys: `inspection_note`, `returns_note`, and `extra_delivery_note` in `SettingService.php` and `SettingsController.php`.
  - Replaced unverified wording in transactional email templates (`payment_approved.php`, `lock_warning.php`, `order_expired.php`).
  - Added PHPUnit automated claims scanner: `tests/unit/ClaimsScannerTest.php` enforcing zero invented claims across Views, Seeders, and Controllers.

---

## Phase 2: Login Persistence & Refresh Token Hardening (API)
- **Status**: Completed
- **Branch**: `fix-login-persistence`
- **Changes**:
  - Implemented 30-second token reuse grace window (`REFRESH_REUSE_GRACE_SECONDS`, default 30s): replaying a recently rotated token within the grace period reissues a fresh pair in the same family instead of destroying the session.
  - Implemented sliding expiry: each refresh extends token validity by 30 days (`JWT_REFRESH_TTL_DAYS`) from now, capped by a 180-day absolute ceiling (`JWT_REFRESH_MAX_LIFETIME_DAYS`, `family_created_at`).
  - Added audit reason tracking (`revoke_reason`: `rotated`, `logout`, `logout_all`, `reuse_detected`, `expired_cap`) via migration `2026-11-21-000001_AddRevokeReasonAndFamilyCreatedAtToRefreshTokens`.
  - Enforced HTTP 401 on missing or invalid refresh tokens in `AuthController::refresh()`.
  - Omitted `refresh_token` from JSON body for standard web clients by default (relies on HttpOnly cookie), returning it only when requested via `X-Client: native` or `return_refresh_token=true`.
  - Added comprehensive test suite `tests/feature/PersistentLoginFeatureTest.php` verifying regression race prevention, 30-day sliding expiry, theft detection outside grace window, and 180-day absolute cap.
  - Verification: 98/98 PHPUnit tests green (4,084 assertions).



