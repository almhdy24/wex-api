# Wex Technology API - Project Progress Tracker

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
- [ ] **Phase 3: Authentication & User Management**
  - TokenService (JWT HS256 access tokens + rotating refresh token family)
  - OtpService (HMAC-SHA256 hashed 6-digit codes, rate-limiting, attempt count)
  - GoogleAuthService (JWKS caching, token verification)
  - AuthService & AuthController (Google sign-in, email OTP request/verify, refresh, logout)
  - AuthFilter & RoleFilter
  - MeController (profile, Sudan phone validation, preferences, account soft-delete)
  - CLI command `admin:create`
  - Unit/Feature tests for Auth
- [ ] **Phase 4: Product Catalog & Image Management**
  - Public endpoints: Categories, Brands, Products listing (filters/search/sort/paginate)
  - Product detail with stock level status, specs, accessories, images
  - ImageService: multi-upload, GD re-encoding (EXIF stripping), thumbnail generation
  - Admin CRUD: products, accessories linking, images management
  - Unit/Feature tests for Catalog
- [ ] **Phase 5: Settings, Pricing & Shipping**
  - SettingsService & ExchangeRate tracking
  - ShippingZones CRUD & public listing (18 Sudan states)
  - PricingService: central integer math, exchange rate conversion, discount, rounding step
  - Unit tests for PricingService
- [ ] **Phase 6: Order Processing & Payment Proofs**
  - OrderService & atomic stock reservation in DB transactions
  - OrderStateMachine (defined valid transitions)
  - Price lock dual enforcement (cron `orders:expire` + lazy check)
  - Payment proof upload & secure authorized image streaming endpoint
  - Customer cancel & reorder endpoints
  - Admin/Staff order management & payment proof review (approve/reject)
  - Comprehensive feature tests for order flow
- [ ] **Phase 7: Notifications & Email Outbox**
  - In-app notification system (unread count, mark read, list)
  - Email outbox pattern & worker command `mail:send` (retry backoff)
  - Arabic RTL HTML email templates (Wex colors, email-safe fonts)
  - User notification preferences support
  - Tests for notifications & outbox
- [ ] **Phase 8: Admin Management & Audit Logs**
  - Admin dashboard stats (order counts, pending proofs, today sales, low stock)
  - User management (role changes, active status)
  - Audit logging service & viewer endpoint
- [ ] **Phase 9: Security Audit & Hardening**
  - Complete security checklist verification
  - Input validation review & rate limit testing
  - Composer audit & PHP linting (`php -l`)
  - Documentation completion (`API.md`, `DATABASE.md`, `DEPLOYMENT.md`, `curl-examples.md`)
- [ ] **Phase 10: Final Review & Double Check**
  - Full automated test suite run
  - End-to-end smoke testing with curl against running server
  - Clean clone validation check

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
- Automated test `tests/feature/DatabaseTest.php` passing with 18 assertions.

---

## (c) What Is Next
- **Phase 3: Authentication & User Management**
  - Implement `TokenService` (JWT HS256 access tokens + rotating refresh token family with theft detection).
  - Implement `OtpService` (HMAC-SHA256 hashed 6-digit codes, rate limiting, attempts tracking, single use).
  - Implement `GoogleAuthService` (Google ID token verification against JWKS with caching).
  - Implement `MailService` with `log` driver for local development.
  - Implement `AuthService` and `AuthController` (Google sign-in, email request, verify, refresh, logout, logout-all).
  - Implement `AuthFilter` and `RoleFilter` (staff/admin checks).
  - Implement `MeController` (profile view/update, Sudan phone validation, preferences, soft delete).
  - Implement CLI command `admin:create`.
  - Feature tests for authentication flows.

---

## (d) Decisions
- Stored all architectural choices in `docs/DECISIONS.md` (passwordless OTP/Google, integer money, no DB enums, CSRF off for Bearer API, UTC timestamps, atomic stock reservations, dual price-lock expiry, email outbox pattern).
