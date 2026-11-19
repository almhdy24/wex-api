# Wex API - Working Curl Examples

This document contains verified working `curl` commands for all endpoints.

## Base URL
Local Development: `http://localhost:8081/api/v1`

---

## Phase 1: Core & Health Check

### Health Check (Ping)
```bash
curl -i http://localhost:8081/api/v1/ping
```

**Expected Response (200 OK):**
```json
{
    "success": true,
    "message": "خدمة ويكس تكنولوجي تعمل بنجاح",
    "data": {
        "service": "wex-api",
        "version": "1.0.0",
        "timestamp": "2026-11-19T15:09:07Z",
        "env": "development"
    },
    "errors": null,
    "meta": null
}
```

### English Locale Negotiation
```bash
curl -i -H "Accept-Language: en" http://localhost:8081/api/v1/ping
```

### Preflight CORS OPTIONS
```bash
curl -i -X OPTIONS http://localhost:8081/api/v1/ping \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: GET"
```

### 404 Route Not Found
```bash
curl -i http://localhost:8081/api/v1/nonexistent
```

**Expected Response (404 Not Found):**
```json
{
    "success": false,
    "message": "الرابط المطلوب غير موجود",
    "data": null,
    "errors": null,
    "meta": null
}
```

---

## Phase 3: Authentication & Profile

### 1. Request Email OTP (Sign-In)
```bash
curl -i -X POST http://localhost:8081/api/v1/auth/email/request \
  -H "Content-Type: application/json" \
  -d '{"email": "customer@example.com"}'
```

### 2. Verify Email OTP & Receive Tokens
```bash
# Read the 6-digit code from writable/logs/emails/
curl -i -X POST http://localhost:8081/api/v1/auth/email/verify \
  -H "Content-Type: application/json" \
  -d '{"email": "customer@example.com", "code": "123456", "name": "أحمد عثمان"}'
```

### 3. Rotate Refresh Token
```bash
curl -i -X POST http://localhost:8081/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "<YOUR_REFRESH_TOKEN>"}'
```

### 4. Logout Session
```bash
curl -i -X POST http://localhost:8081/api/v1/auth/logout \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "<YOUR_REFRESH_TOKEN>"}'
```

### 5. Get Current User Profile (Authenticated)
```bash
curl -i http://localhost:8081/api/v1/me \
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>"
```

### 6. Update Profile & Phone Number (Sudanese Format)
```bash
curl -i -X PATCH http://localhost:8081/api/v1/me \
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"name": "محمد أحمد", "phone": "0912345678"}'
```

### 7. Update Notification Preferences
```bash
curl -i -X PATCH http://localhost:8081/api/v1/me/preferences \
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"email_notifications": true}'
```

### 8. CLI Command: Create/Promote Admin
```bash
php spark admin:create --email=admin@wextechnology.com --name="مدير المتجر"
```

---

## Phase 4: Product Catalog & Image Management

### 1. Get Public Shop Info & Current Exchange Rate
```bash
curl -i http://localhost:8081/api/v1/shop
```

### 2. List Public Categories
```bash
curl -i http://localhost:8081/api/v1/categories
```

### 3. List Public Brands
```bash
curl -i http://localhost:8081/api/v1/brands
```

### 4. List Shipping Zones (Sudan States)
```bash
curl -i http://localhost:8081/api/v1/shipping-zones
```

### 5. Browse Products (Filter, Sort & Paginate)
```bash
# Filter laptops under 3000 AED, sort by price low to high
curl -i "http://localhost:8081/api/v1/products?type=laptop&max_price=3000&sort=price_asc&page=1&per_page=10"
```

### 6. View Single Product Details & Suggested Accessories
```bash
curl -i http://localhost:8081/api/v1/products/dell-latitude-7490
```

### 7. Admin: Create New Product
```bash
curl -i -X POST http://localhost:8081/api/v1/admin/products \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Lenovo ThinkPad T14 Gen 2",
    "type": "laptop",
    "category_id": 1,
    "brand_id": 2,
    "price_aed": 1450,
    "condition": "used_imported",
    "stock_quantity": 4,
    "short_description": "لابتوب أعمال بمعالج Core i5 الجيل الحادي عشر",
    "description": "حالة ممتازة وارد الخارج بطارية تدوم طويلاً",
    "warranty_text": "ضمان فحص وتجربة لمدة شهر كامل",
    "specs": {
      "processor": "Intel Core i5-1145G7",
      "ram": "16GB DDR4",
      "storage": "512GB NVMe SSD",
      "screen": "14.0 inch FHD IPS",
      "battery": "4-5 hours"
    }
  }'
```

### 8. Admin: Upload Product Image
```bash
curl -i -X POST http://localhost:8081/api/v1/admin/products/1/images \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -F "image=@/path/to/laptop.jpg" \
  -F "is_primary=1" \
  -F "sort_order=0"
```

### 9. Admin: Link Suggested Accessory
```bash
curl -i -X POST http://localhost:8081/api/v1/admin/products/1/accessories \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"accessory_id": 9}'
```

---

## Phase 5: Settings, Pricing & Shipping

### 1. Admin: View All Settings
```bash
curl -i http://localhost:8081/api/v1/admin/settings \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

### 2. Admin: Update Store Settings
```bash
curl -i -X PATCH http://localhost:8081/api/v1/admin/settings \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "shop_phone": "+249 912 345 678",
    "lock_hours": 6,
    "app_discount_percent": 3,
    "rounding_step_sdg": 100
  }'
```

### 3. Admin: Update Exchange Rate (AED -> SDG)
```bash
curl -i -X POST http://localhost:8081/api/v1/admin/rates \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"rate_sdg": 680}'
```

### 4. Admin: View Exchange Rate History
```bash
curl -i "http://localhost:8081/api/v1/admin/rates?page=1&per_page=10" \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

### 5. Admin: List All Shipping Zones
```bash
curl -i http://localhost:8081/api/v1/admin/shipping-zones \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

### 6. Admin: Create New Shipping Zone
```bash
curl -i -X POST http://localhost:8081/api/v1/admin/shipping-zones \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "state_name_ar": "الولاية الشمالية - دنقلا",
    "fee_sdg": 15000,
    "is_active": true
  }'
```

### 7. Admin: Update Shipping Zone Fee
```bash
curl -i -X PATCH http://localhost:8081/api/v1/admin/shipping-zones/1 \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"fee_sdg": 6000}'
```

---

## Phase 6: Order Processing & Payment Proofs

### 1. Place New Order (Pickup with Price Lock)
```bash
curl -i -X POST http://localhost:8081/api/v1/orders \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "order_type": "pickup",
    "contact_phone": "0912345678",
    "customer_notes": "سأستلم في المساء",
    "items": [
      {"product_id": 1, "quantity": 1}
    ]
  }'
```

### 2. Place New Order (Courier Delivery)
```bash
curl -i -X POST http://localhost:8081/api/v1/orders \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "order_type": "delivery",
    "shipping_zone_id": 1,
    "delivery_address": "الخرطوم، شارع الستين، عمارة 12، شقة 4",
    "contact_phone": "0912345678",
    "items": [
      {"product_id": 1, "quantity": 1},
      {"product_id": 9, "quantity": 1}
    ]
  }'
```

### 3. List Customer Orders
```bash
curl -i http://localhost:8081/api/v1/orders \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 4. View Order Details & Bank Transfer Info
```bash
curl -i http://localhost:8081/api/v1/orders/WEX-261119-C59ED \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 5. Upload Bankak Transfer Proof (Customer)
```bash
curl -i -X POST http://localhost:8081/api/v1/orders/WEX-261119-C59ED/payment-proof \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>" \
  -F "proof_image=@/path/to/bankak_receipt.jpg" \
  -F "sender_name=محمد أحمد" \
  -F "reference_number=MB-123456"
```

### 6. Stream Authorized Proof Image
```bash
curl -i http://localhost:8081/api/v1/orders/WEX-261119-C59ED/payment-proof/1/image \
  -H "Authorization: Bearer <CUSTOMER_OR_ADMIN_TOKEN>"
```

### 7. Customer: Cancel Pending Order
```bash
curl -i -X POST http://localhost:8081/api/v1/orders/WEX-261119-C59ED/cancel \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"reason": "رغبت في تغيير موديل اللابتوب"}'
```

### 8. Customer: Reorder from Cancelled/Expired Order
```bash
curl -i -X POST http://localhost:8081/api/v1/orders/WEX-261119-C59ED/reorder \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 9. Admin/Staff: Review Payment Proof (Approve / Reject)
```bash
# Approve (transitions order to confirmed)
curl -i -X POST http://localhost:8081/api/v1/admin/orders/WEX-261119-C59ED/proofs/1/review \
  -H "Authorization: Bearer <ADMIN_OR_STAFF_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "approve",
    "note": "تم مطابقة المبلغ في كشف حساب بنكك"
  }'

# Reject (transitions back to pending_payment or expired)
curl -i -X POST http://localhost:8081/api/v1/admin/orders/WEX-261119-C59ED/proofs/1/review \
  -H "Authorization: Bearer <ADMIN_OR_STAFF_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "reject",
    "note": "المبلغ المحول غير مطابق لقيمة الطلب"
  }'
```

### 10. Admin/Staff: Update Order Status
```bash
curl -i -X PATCH http://localhost:8081/api/v1/admin/orders/WEX-261119-C59ED/status \
  -H "Authorization: Bearer <ADMIN_OR_STAFF_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "out_for_delivery",
    "courier_name": "مندوب التوصيل - عثمان",
    "courier_phone": "+249911223344",
    "note": "خرج الطلب مع المندوب"
  }'
```

### 11. Admin/Staff: Extend Price Lock
```bash
curl -i -X POST http://localhost:8081/api/v1/admin/orders/WEX-261119-C59ED/lock \
  -H "Authorization: Bearer <ADMIN_OR_STAFF_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"hours": 6}'
```

### 12. CLI Command: Run Price-Lock Expiry Check
```bash
php spark orders:expire
```

---

## Phase 7: Notifications & Email Outbox

### 1. Get Unread Notifications Badge Count
```bash
curl -i http://localhost:8081/api/v1/notifications/unread-count \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 2. List In-App Notifications
```bash
curl -i "http://localhost:8081/api/v1/notifications?page=1&per_page=10" \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 3. Mark Single Notification as Read
```bash
curl -i -X PATCH http://localhost:8081/api/v1/notifications/1/read \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 4. Mark All Notifications as Read
```bash
curl -i -X POST http://localhost:8081/api/v1/notifications/mark-all-read \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>"
```

### 5. CLI Command: Process Email Outbox Queue
```bash
php spark mail:send --batch=20
```




