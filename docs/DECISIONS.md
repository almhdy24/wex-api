# Architecture & Technical Decisions (docs/DECISIONS.md)

This document tracks all design and technical decisions made for the **wex-api** service.

---

### 1. Passwordless Authentication
- **Decision:** The platform has no user passwords anywhere. The primary authentication is Google Sign-In (ID token verification against Google JWKS), and the secondary method is a 6-digit email OTP (sent via email).
- **Rationale:** Minimizes credential compromise risk, eliminates password resets, reduces friction for Sudanese users on mobile devices, and enforces email verification naturally.
- **Implementation:** OTP codes are 6-digit cryptographically secure random integers, stored only as HMAC-SHA256 hashes with an app secret, valid for 10 minutes with max 5 failed attempts before burning.

### 2. Integer-Only Money Storage
- **Decision:** All financial values are stored and calculated strictly as integers:
  - `price_aed`: whole UAE Dirhams.
  - `rate_sdg`: whole SDG per 1 AED (e.g., 650).
  - `subtotal_sdg`, `discount_sdg`, `delivery_fee_sdg`, `total_sdg`: whole Sudanese Pounds.
- **Rationale:** Floating point math causes precision loss and rounding errors. Integer arithmetic ensures exact penny/pound accuracy across all platforms and databases.
- **Rounding Rule:** SDG final totals are rounded to the nearest configured step (default 100 SDG) via `ceil($amount / $step) * $step` in `PricingService`.

### 3. No ENUM Database Columns
- **Decision:** Statuses and types (e.g. `order status`, `product type`, `condition`, `role`) are stored as `VARCHAR` columns with indexed lookups, enforced strictly via PHP constants (`Config\Wex`) and validation rules.
- **Rationale:** MySQL `ENUM` and SQLite have different behaviors. SQLite has no native ENUM. Migrations altering ENUM values are error-prone in MySQL and require table rebuilds in SQLite. `VARCHAR` ensures 100% migration portability.

### 4. CSRF Protection Disabled for API
- **Decision:** CSRF filter is disabled for `/api/*` endpoints.
- **Rationale:** The API is purely stateless and authenticates requests using `Authorization: Bearer <JWT>` tokens. Browsers do not automatically attach Bearer headers across origins (unlike cookies). Since authentication is not cookie-based, CSRF attacks are not applicable.
- **Mitigation:** Strict CORS allowlist (`CORS_ALLOWED_ORIGINS`), security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`), and rate limiting.

### 5. Timestamps in UTC, Output in ISO 8601 (Z)
- **Decision:** All timestamps are stored in UTC (`DATETIME` in UTC) and serialized to ISO 8601 format with trailing `Z` (e.g., `2026-11-19T14:30:00Z`).
- **Rationale:** Eliminates timezone ambiguity across mobile app clients, local web servers, and third-party services.

### 6. Atomic Stock Reservation and Concurrency
- **Decision:** Stock reservation during order creation uses atomic SQL updates within a database transaction:
  `UPDATE products SET stock_quantity = stock_quantity - :qty: WHERE id = :id: AND stock_quantity >= :qty:`
- **Rationale:** Prevents race conditions and overselling without requiring heavy table locks. If the affected rows count is 0, the transaction rolls back immediately and returns a stock failure message.

### 7. Dual Price Lock Expiry Enforcement
- **Decision:** The price lock (freezing the SDG exchange rate for an order for N hours) is enforced in two places:
  1. Cron task `php spark orders:expire` running every minute to expire stale orders and restore stock.
  2. Lazy evaluation inside `OrderService::refreshExpiry()` on every read or proof upload.
- **Rationale:** Guarantees that even if cron is delayed or suspended, a customer can never upload a payment proof or pay at an expired rate.

### 8. Email Outbox Pattern
- **Decision:** Order and status emails are not sent synchronously during HTTP requests. Instead, they are written to the `email_outbox` table and processed in batches by `php spark mail:send`.
- **Rationale:** Keeps HTTP responses sub-100ms, avoids timeout errors when SMTP servers are slow or unreachable, and provides exponential backoff with retry tracking. OTP emails attempt immediate dispatch, falling back to outbox if transient delivery fails.

### 9. File Upload Security & Isolation
- **Decision:** 
  - Product images are stored in `public/uploads/products/` with an `.htaccess` blocking script execution, resized to max 1600px with a 400px thumbnail generated using GD (re-encoding strips EXIF metadata).
  - Payment proof receipts are stored outside webroot in `writable/uploads/proofs/` and served only through an authorized streaming endpoint checking user ownership or staff/admin role.

### 10. Traversy Media Code Style
- **Decision:** Clean, beginner-friendly PHP with descriptive names, thin controllers, business logic encapsulated in single-responsibility services, PSR-12 formatting, and Arabic user-facing messages centralized in `app/Language/ar/Api.php`.

### 11. Role Safeguards & Administrative Audit Trail
- **Decision:** All administrative actions (exchange rate modifications, order status transitions, manual price-lock extensions, payment proof approvals/rejections, shop settings updates, and user role/status changes) are recorded to the `audit_logs` table with user ID, action name, entity type, ID, old/new values, IP address, and user agent.
- **Safeguards:** Self-modification locks in `UserAdminController` prevent administrators from demoting their own role or deactivating their own account, preventing accidental total lockout of administrative access.

