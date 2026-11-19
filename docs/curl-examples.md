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


