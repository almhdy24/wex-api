# Wex Technology API - Complete REST API Reference

Base URL: `/api/v1`  
Protocol: HTTP/1.1 or HTTP/2 over HTTPS (forced in production)  
Data Format: `application/json; charset=UTF-8`  
Standard Header: `X-Request-Id: <uuid>` (returned on every response for request tracing)

---

## 1. Standard Response Envelope

Every endpoint (success and error) strictly adheres to the standard JSON envelope:

### Success Response
```json
{
    "success": true,
    "message": "نص الرسالة بالعربية",
    "data": { ... } | [ ... ] | null,
    "errors": null,
    "meta": {
        "page": 1,
        "per_page": 20,
        "total": 45,
        "total_pages": 3
    } | null
}
```

### Error Response
```json
{
    "success": false,
    "message": "رسالة الخطأ التوضيحية بالعربية",
    "data": null,
    "errors": {
        "field_name": "تفاصيل الخطأ في هذا الحقل"
    } | null,
    "meta": null
}
```

### HTTP Status Codes
| Code | Meaning | When Used |
|---|---|---|
| `200 OK` | Request succeeded | Standard response for GET, PATCH, and non-creation actions |
| `201 Created` | Resource created | Returned on successful POST (order, user, product, upload) |
| `204 No Content` | Success without body | Returned for preflight OPTIONS |
| `400 Bad Request` | Malformed request | Invalid JSON syntax or unparseable input |
| `401 Unauthorized` | Authentication required | Missing, expired, or invalid JWT Bearer token |
| `403 Forbidden` | Access denied | Insufficient role permissions or accessing another user's resource |
| `404 Not Found` | Resource not found | Route, product, or order number not found |
| `409 Conflict` | Business rule conflict | Locked order expired, status transition forbidden, or duplicate slug |
| `422 Unprocessable` | Validation failed | Form validation errors returned in `errors` map |
| `429 Too Many Requests` | Rate limit exceeded | Token-bucket throttle triggered; check `Retry-After` header |
| `500 Server Error` | Internal error | Unexpected system error (safe Arabic message in production) |

---

## 2. Authentication & Authorization

All authenticated endpoints require the standard Bearer header:
```http
Authorization: Bearer <access_token>
```

- **Access Token:** JWT HS256, 15-minute expiration, contains user ID, email, and role.
- **Refresh Token:** Cryptographically secure 64-char hex string, 30-day expiration, stored in DB with rotating token family ID. If a previously replaced token is used (theft detected), the entire token family is immediately revoked.

---

## 3. Endpoints Directory

### 3.1 Public & Health Check

#### Health Check
- **`GET /api/v1/ping`**
  - **Headers:** Optional `Accept-Language: en` (defaults to Arabic)
  - **Response 200:** Returns service status, version (`1.0.0`), UTC timestamp, and environment.

#### Public Shop Information
- **`GET /api/v1/shop`**
  - **Description:** Returns shop metadata: name, phone, WhatsApp, address, working hours, lock_hours, app_discount_percent, and prices_updated_at.
  - **Privacy:** Reference currency (AED) and exchange rates are strictly business-private and omitted from customer responses. Bank details are omitted here and provided inside authenticated order responses.

#### Shipping Zones
- **`GET /api/v1/shipping-zones`**
  - **Description:** Returns the active list of Sudan states and their delivery fees in whole SDG.

---

### 3.2 Authentication Endpoints

#### Google Sign-In
- **`POST /api/v1/auth/google`**
  - **Body:** `{"id_token": "eyJhbGciOi..."}`
  - **Description:** Verifies Google ID token against Google's public JWKS. Automatically provisions or updates user account.
  - **Response 200:** Returns `access_token`, `refresh_token`, `token_type: "Bearer"`, `expires_in: 900`, and user object.

#### Request Email OTP
- **`POST /api/v1/auth/email/request`**
  - **Body:** `{"email": "customer@example.com"}`
  - **Description:** Sends a 6-digit verification code to the customer's email.
  - **Rate Limits:** 1 request per 60 seconds per email; max 10 requests per hour per IP.
  - **Response 200:** Confirmation message in Arabic.

#### Verify Email OTP
- **`POST /api/v1/auth/email/verify`**
  - **Body:**
    ```json
    {
      "email": "customer@example.com",
      "code": "123456",
      "name": "أحمد عثمان"
    }
    ```
  - **Description:** Verifies the 6-digit OTP code (HMAC-SHA256 hashed). Auto-creates new account if email is new.
  - **Response 200:** Returns `access_token`, `refresh_token`, `expires_in: 900`, and user profile.

#### Refresh Access Token
- **`POST /api/v1/auth/refresh`**
  - **Credentials:** HttpOnly `wex_refresh_token` cookie automatically transmitted by browser, or optional body `{"refresh_token": "<token_string>"}`.
  - **Headers (Optional):** `X-Client: native` (requests returning `refresh_token` in JSON body).
  - **Body (Optional):** `{"return_refresh_token": true}`
  - **Description:** Exchanges a valid refresh token for a new access token (15m) and rotated refresh token with 30-day sliding expiry (capped at 180 days from family creation). Replays within `REFRESH_REUSE_GRACE_SECONDS` (30s) are safely reissued to tolerate concurrent tab bootstrapping without session termination.
  - **Response 200:** Returns fresh `access_token`, `expires_in: 900`, `refresh_expires_in: 2592000`, user profile, and re-emits rotated persistent `wex_refresh_token` cookie.
  - **Response 401:** Returned if refresh token is missing, expired, or revoked.

#### Logout (Current Session)
- **`POST /api/v1/auth/logout`**
  - **Body:** `{"refresh_token": "<token_string>"}`
  - **Description:** Revokes the specific refresh token session.

#### Logout All Sessions
- **`POST /api/v1/auth/logout-all`**
  - **Auth:** Bearer Token
  - **Description:** Revokes all active refresh tokens for the current user.

---

### 3.3 Customer Profile (`/me`)

#### Get Current Profile
- **`GET /api/v1/me`**
  - **Auth:** Bearer Token
  - **Response 200:** User object (`id`, `name`, `email`, `phone`, `role`, `email_notifications`, `created_at`).

#### Update Profile
- **`PATCH /api/v1/me`**
  - **Auth:** Bearer Token
  - **Body:** `{"name": "محمد حسن", "phone": "0912345678"}`
  - **Validation:** Phone must be valid Sudanese format (`09...`, `01...`, `249...`, `+249...`). Automatically normalized to `+249912345678`.

#### Update Notification Preferences
- **`PATCH /api/v1/me/preferences`**
  - **Auth:** Bearer Token
  - **Body:** `{"email_notifications": false}`

#### Soft Delete Account
- **`DELETE /api/v1/me`**
  - **Auth:** Bearer Token
  - **Description:** Soft-deletes user account and revokes all active refresh tokens.

---

### 3.4 Public Product Catalog

#### List Categories
- **`GET /api/v1/categories`**
  - **Response 200:** Active categories sorted by `sort_order` with Arabic and English names, slugs, and product counts.

#### List Brands
- **`GET /api/v1/brands`**
  - **Response 200:** Active brands with name and slug.

#### List Products
- **`GET /api/v1/products`**
  - **Query Parameters:**
    - `page` (default: 1)
    - `per_page` (default: 20, max: 50)
    - `category` (slug string)
    - `brand` (slug string)
    - `type` (`laptop` | `phone` | `accessory`)
    - `condition` (`new` | `used_imported`)
    - `min_price_sdg` (integer in SDG)
    - `max_price_sdg` (integer in SDG)
    - `q` / `search` (keyword matched against name, specs, and short description)
    - `sort` (`price_asc`, `price_desc`, `newest`)
  - **Pricing Policy:** Products in customer endpoints are transformed through `PublicProductPresenter` and expose only `price_sdg`. Reference currency (AED) and exchange rates are strictly hidden from customers.
  - **Response 200:** Returns product cards with calculated `price_sdg`, `price_aed`, `stock_status` (`in_stock` | `low_stock` | `out_of_stock`), primary image, category, and brand.

#### Product Detail
- **`GET /api/v1/products/{slug}`**
  - **Response 200:** Complete product details including full specs object, warranty text, all gallery images, and suggested accessories.

---

### 3.5 Customer Orders & Payments

#### List My Orders
- **`GET /api/v1/orders`**
  - **Auth:** Bearer Token
  - **Query Parameters:** `page`, `per_page`, `status`
  - **Response 200:** Paginated list of customer orders with live lock remaining seconds.

#### Create Order
- **`POST /api/v1/orders`**
  - **Auth:** Bearer Token
  - **Body:**
    ```json
    {
      "order_type": "delivery",
      "items": [
        {"product_id": 1, "quantity": 1},
        {"product_id": 5, "quantity": 2}
      ],
      "shipping_zone_id": 1,
      "delivery_address": "أم درمان - حي الملازمين - منزل 12",
      "delivery_phone": "0912345678",
      "customer_notes": "يرجى الاتصال قبل الوصول"
    }
    ```
  - **Description:** Atomically decrements product stock, computes integer SDG pricing using current rate and app discount (3%), creates order in `pending_payment` status, and freezes SDG price for 4 hours.
  - **Response 201:** Created order object with Bankak bank transfer instructions and lock expiry timestamp.

#### Get Order Detail
- **`GET /api/v1/orders/{order_number}`**
  - **Auth:** Bearer Token (Order Owner or Admin/Staff)
  - **Response 200:** Order breakdown, line items, lock countdown, timeline events, and payment proof status.

#### Cancel Order
- **`POST /api/v1/orders/{order_number}/cancel`**
  - **Auth:** Bearer Token (Order Owner)
  - **Description:** Allowed only while order is in `pending_payment`. Immediately releases reserved stock back to inventory.

#### Reorder (Fast Duplicate)
- **`POST /api/v1/orders/{order_number}/reorder`**
  - **Auth:** Bearer Token (Order Owner)
  - **Description:** Clones line items into a new order with fresh pricing and current exchange rate.

#### Upload Payment Proof (Bankak Receipt)
- **`POST /api/v1/orders/{order_number}/payment-proof`**
  - **Auth:** Bearer Token (Order Owner)
  - **Content-Type:** `multipart/form-data`
  - **Form Fields:**
    - `proof_image`: File (JPEG, PNG, WEBP, max 5MB). Field name `proof_image` is strictly required. Accepts nameless blobs (`filename=blob` or `filename=receipt`).
    - `sender_name`: string (optional, name on bank account, max 150)
    - `reference_number`: string (optional, transaction reference, max 100)
  - **Description:** Verifies price lock validity, validates real MIME type using `finfo`, re-encodes via GD stripping EXIF (with graceful direct copy fallback), stores privately, transitions order status to `payment_review`, and notifies staff. When an order is already in `payment_review`, re-upload replaces prior pending proof.
  - **Error Responses:**
    - `403 Forbidden`: Order does not belong to authenticated user (`"لا تملك صلاحية الوصول إلى هذا الطلب"`).
    - `409 Conflict`: Price lock expired (`"انتهت مهلة تثبيت السعر لهذا الطلب"`).
    - `413 Payload Too Large`: Upload exceeds limit or `post_max_size` (`"حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى"`).
    - `422 Unprocessable Entity`: Missing `proof_image` field or invalid image content (`"الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP"`).
    - `429 Too Many Requests`: Upload rate limit exceeded (maximum 5 attempts per order per hour, `"تجاوزت الحد المسموح من المحاولات"`).

#### Stream Payment Proof Image
- **`GET /api/v1/orders/{order_number}/payment-proof/{id}/image`**
  - **Auth:** Bearer Token (Order Owner or Staff/Admin)
  - **Response 200:** Binary image stream (`image/jpeg` or `image/png`). Direct access without authorization returns 403/404.

---

### 3.6 In-App Notifications

#### Get Unread Count (Badge)
- **`GET /api/v1/notifications/unread-count`**
  - **Auth:** Bearer Token
  - **Response 200:** `{"data": {"unread_count": 3}}`

#### List Notifications
- **`GET /api/v1/notifications`**
  - **Auth:** Bearer Token
  - **Query:** `page`, `per_page`

#### Mark Notification Read
- **`PATCH /api/v1/notifications/{id}/read`**
  - **Auth:** Bearer Token

#### Mark All Read
- **`POST /api/v1/notifications/mark-all-read`**
  - **Auth:** Bearer Token

---

### 3.7 Admin & Staff Endpoints (`/admin`)

#### Operational Dashboard (Admin & Staff)
- **`GET /api/v1/admin/dashboard`**
  - **Auth:** Staff or Admin
  - **Response 200:**
    - Orders count by status
    - Pending payment reviews count requiring action
    - Today's order count & revenue in SDG
    - Low-stock inventory alert list
    - Current exchange rate and lock hours

#### Order Management (Staff & Admin)
- **`GET /api/v1/admin/orders`** (List orders with filters: `status`, `order_type`, `search`, pagination)
- **`GET /api/v1/admin/orders/{order_number}`** (Full order detail with payment proofs)
- **`PATCH /api/v1/admin/orders/{order_number}/status`** (Advance status according to strict transition matrix: `processing`, `ready_for_pickup`, `out_for_delivery`, `completed`, `cancelled`)
- **`POST /api/v1/admin/orders/{order_number}/lock`** (Extend price lock by additional hours)
- **`POST /api/v1/admin/orders/{order_number}/proofs/{id}/review`**
  - **Body:** `{"action": "approve" | "reject", "note": "سبب الرفض"}`
  - **Description:** Approving confirms payment and sets order to `confirmed`. Rejecting returns order to `pending_payment` (if lock is still active) or `expired` (if lock expired), releasing stock.

#### Product Catalog Management (Admin only)
- **`GET /api/v1/admin/products`** (All products including inactive)
- **`POST /api/v1/admin/products`** (Create product with specs JSON)
- **`GET /api/v1/admin/products/{id}`** (Product details)
- **`PATCH /api/v1/admin/products/{id}`** (Update product)
- **`DELETE /api/v1/admin/products/{id}`** (Soft-delete product)
- **`PATCH /api/v1/admin/products/{id}/stock`** (Direct stock adjustment)
- **`POST /api/v1/admin/products/{id}/accessories`** (Link suggested accessory)
- **`DELETE /api/v1/admin/products/{id}/accessories/{accessory_id}`** (Unlink accessory)
- **`POST /api/v1/admin/products/{id}/images`** (Multi-image upload with thumbnail generation)
- **`PATCH /api/v1/admin/products/{id}/images/{img_id}`** (Set primary image or sort order)
- **`DELETE /api/v1/admin/products/{id}/images/{img_id}`** (Delete image and unlink physical files)

#### Categories & Brands Management (Admin only)
- CRUD endpoints for `/api/v1/admin/categories` and `/api/v1/admin/brands`

#### Settings & Exchange Rate (Admin only)
- **`GET /api/v1/admin/settings`** (List all shop settings)
- **`PATCH /api/v1/admin/settings`** (Update settings with whitelist validation and audit logging)
- **`GET /api/v1/admin/rates`** (Exchange rate history)
- **`POST /api/v1/admin/rates`**
  - **Body:** `{"rate": 850, "note": "تعديل سعر الصرف اليومي"}`
  - **Description:** Updates current AED -> SDG exchange rate and logs change to audit trail.

#### Shipping Zones Management (Admin only)
- CRUD endpoints for `/api/v1/admin/shipping-zones` (State name, fee in SDG, delivery estimates, active toggle).

#### User Management (Admin only)
- **`GET /api/v1/admin/users`** (Filter by `role`, `status`, `search`, paginated)
- **`GET /api/v1/admin/users/{id}`** (User profile with order history and total SDG spent)
- **`PATCH /api/v1/admin/users/{id}/role`** (`{"role": "staff" | "admin" | "customer"}` - cannot modify own role)
- **`PATCH /api/v1/admin/users/{id}/status`** (`{"is_active": true | false}` - cannot deactivate own account)

#### Audit Logs Viewer (Admin only)
- **`GET /api/v1/admin/audit-logs`**
  - **Query:** `page`, `per_page`, `action`, `user_id`, `entity_type`
  - **Description:** Complete chronological audit trail of all staff and admin operations.
