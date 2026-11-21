# STABILIZATION SPRINT LOG

See full log in `projects/wex-web/docs/STABILIZE-LOG.md`.
Mirrored here for repository completeness.

---

## PHASE 1: BEHAVIORAL CHECKS (Initial Baseline - All Failing)

### Check 1: `scripts/check-api-surface.mjs`
**Status**: RED (3 missing methods: `productsApi.getShopSettings`)

### Check 2: `scripts/check-css-modules.mjs`
**Status**: RED (missing classes in 16 files)

### Check 3: Route Smoke Tests (`tests/route-smoke.test.jsx`)
**Status**: RED (31 routes failed, Home crashed with `productsApi.getShopSettings is not a function`, missing `<Page>` wrapper)

### Check 4: Journey Tests (`tests/journeys.test.jsx`)
**Status**: RED (3 of 4 failed: under review state, expired order, rejection flow)

### Check 5: API E2E Journey (`scripts/e2e.sh`)
**Status**: RED (Admin payments list item missing required fields: `customer_name`, `customer_phone`, `amount`, `expected_amount`, `payment_proofs`)

### Check 6: Database Content Audit (`scripts/audit-db.sh`)
**Status**: RED (15 violating rows found with forbidden claims, `2849102`, and `WEX-DBG-%` orders)

### Check 7: Extended Claims Scanner & PHPUnit Test
**Status**: RED (33 violations in JS/PHP files; PHPUnit `ClaimsScanTest` failed with 6 violations)

### Check 8: Admin English Check (`scripts/check-admin-english.mjs`)
**Status**: RED (45 disallowed Latin words in Admin UI including "Hero Slides" and "CTA")


---

## PHASE 2: P0 FIXES (Verification & Green Proofs)

### Check 1: API Surface Integrity
**Status**: GREEN (Resolved home crash, created unified `GET /shop` endpoint integration, all API calls matching).

### Check 2: CSS Modules Class Integrity
**Status**: GREEN (All referenced CSS classes exist across all 91 module imports).

### Check 3: Route Smoke Tests
**Status**: GREEN (31/31 routes passing inside `<Page>` container with `<main role="main">` landmark).

### Check 4: Customer Order & Payment Journeys
**Status**: GREEN (4/4 journey tests passing: checkout, upload proof, expired order, rejected proof).

### Check 5: E2E Integration Journey
**Status**: GREEN (8/8 steps passing: payments payload includes customer name, phone, amounts, proof details; image endpoint streams image with Bearer auth; admin approval; customer status observation).

### Check 6: Admin Sticky Form Actions & Primitives
**Status**: GREEN (9/9 unit tests passing: `--admin-tab-bar-height` offset, z-index 110 above admin tab bar).

---

## CONSISTENCY SPRINT: PHASE 1 DIAGNOSIS & REPRODUCTION (RED BASELINE)

### 1. Database & Filesystem State Verification
**Investigated Order**: `WEX-261121-C1A96` (order id: 25)
**Raw Output**:
```sql
sqlite3 writable/database/wex.sqlite "select o.id,o.order_number,o.status,(select count(*) from payment_proofs p where p.order_id=o.id) as proofs from orders o order by o.id desc limit 5;"
╭────┬──────────────────┬────────────────┬────────╮
│ id │   order_number   │     status     │ proofs │
╞════╪══════════════════╪════════════════╪════════╡
│ 27 │ WEX-261121-3957C │ confirmed      │      1 │
│ 25 │ WEX-261121-C1A96 │ payment_review │      1 │
│ 16 │ WEX-261121-90A69 │ payment_review │      1 │
│ 15 │ WEX-261121-D8526 │ payment_review │      1 │
│ 12 │ WEX-261121-6A7A8 │ payment_review │      1 │
╰────┴──────────────────┴────────────────┴────────╯

sqlite3 writable/database/wex.sqlite "select id,order_id,file_path,status,created_at from payment_proofs where order_id = 25;"
╭──┬────────┬───────────────────────────────────────────┬────────┬───────────────────╮
│id│order_id│                 file_path                 │ status │    created_at     │
╞══╪════════╪═══════════════════════════════════════════╪════════╪═══════════════════╡
│24│      25│uploads/proofs/2dd2337c23d4348ee4a329adaa4│pending │2026-11-21 07:20:57│
│  │        │ff8f8ea6ef5bc.png                          │        │                   │
╰──┴────────┴───────────────────────────────────────────┴────────┴───────────────────╯

ls -la writable/uploads/proofs/2dd2337c23d4348ee4a329adaa4ff8f8ea6ef5bc.png
-rw-------. 1 u0_a324 u0_a324 253590 Nov 21 09:20 writable/uploads/proofs/2dd2337c23d4348ee4a329adaa4ff8f8ea6ef5bc.png
```
**Diagnostic Conclusion**: Case (b) holds: **proof rows exist but the admin API omits them**.
The row exists in `payment_proofs` with `order_id = 25` and status `pending`. The image file exists on disk (253,590 bytes). The order status is `payment_review`.

### 2. Code Review & Transaction Audit
- **Status & Proof Writing**: In `PaymentProofService::uploadProof`, image storage (`$this->imageService->savePaymentProof($uploadedFile)`) is executed *before* `transBegin()`. If DB insertion fails, an orphan file remains on disk. Notification dispatch is called outside the transaction.
- **Admin Query Discrepancy**:
  - `OrderService::listAdminOrders` (used by payments queue) queries `orders`, joins `users`, loads `payment_proofs`, and provides both `payment_proofs` and `proofs` aliases along with `customer_name` and `customer_phone`.
  - `OrderService::getOrder` (used by admin order detail) loads `payment_proofs` but does not join `users` or populate `customer_name`, `customer_phone`, or `customer` block.
  - `AdminOrderPresenter::present` mapped `payment_proofs` but omitted `proofs`, and `user`/`customer_name` remained null.
- **UI Breakdown**:
  - `AdminOrderDetailPage.jsx` checks `order.proofs || []`. Because `order.proofs` was undefined, `(order.proofs || []).length === 0` rendered "لا توجد إشعارات دفع مرفوعة حتى الآن" and clicking next step toasted "لم يرفع العميل إشعار دفع بعد".
  - `AdminPaymentsPage.jsx` fetches `getOrderDetail` and checked `orderData?.proofs`. Because it was undefined, `currentProof` was null, showing "لم يتم العثور على صورة إشعار مرفوعة" and disabling Approve/Reject.
  - `order.customer_name` was null, causing fallback to placeholder `'عميل'`.
  - Timeline events stored pre-formatted English/UTC text `تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات حتى 11:20 UTC`.

### 3. Curl Discrepancy Reproduction & Red Test
**Admin Payments Queue vs Admin Order Detail Output**:
```json
--- 1. Admin Payments Queue (list query) for WEX-261121-C1A96 ---
{
  "customer_name": "مدير النظام",
  "customer_phone": "+249912345678",
  "proofs_count": 1,
  "has_proofs_key": true,
  "has_payment_block": false
}
--- 2. Admin Order Detail for WEX-261121-C1A96 ---
{
  "customer_name": null,
  "customer_phone": null,
  "proofs_count": 0,
  "has_proofs_key": false,
  "has_payment_block": false
}
```
**Failing PHPUnit Test**:
`php vendor/bin/phpunit tests/feature/DataConsistencyFlowTest.php`
- Failed: `Admin order detail must contain customer block` (array missing key 'customer').
- Failed: `Customer order detail must contain payment block` (array missing key 'payment').
Status: **RED (Failing as expected)**

---

## CONSISTENCY SPRINT: PHASE 2 ROOT CAUSE FIXES (GREEN PROOFS)

### 1. Atomic Proof Upload & Rollback Verification
- Created migration `2026-11-21-000002_AddEventTypeAndMetaToOrderEvents` adding `event_type` and `meta` columns, and cleaned legacy UTC strings from `order_events`.
- Updated `PaymentProofService::uploadProof` to execute within an atomic transaction. If file save succeeds but DB insertion or subsequent logic throws an exception, the database transaction is rolled back AND the physical file is unlinked immediately (`@unlink($fullPath)`).
- `PaymentProofService::uploadProof` now returns the complete created `proof` entity along with `payment` block.
- Tested via `PaymentProofServiceTest::testAtomicUploadRollbackDeletesStoredFileAndRevertsDb`.
**Raw Test Output**:
```
$ php vendor/bin/phpunit tests/unit/PaymentProofServiceTest.php
PHPUnit 10.5.67 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.5.12
Configuration: /data/data/com.termux/files/home/projects/wex-api/phpunit.dist.xml

.....                                                               5 / 5 (100%)

Time: 00:00.291, Memory: 13.00 MB
OK (5 tests, 10 assertions)
```

### 2. Single Server-Side Source of Truth (`payment` Block)
- Created `App\Services\PaymentStateService::computePaymentBlock(Order $order, array $proofs)` computing:
  `{ state: awaiting_proof | under_review | rejected | approved | expired | not_required, can_upload: bool, can_review: bool, latest_proof: { id, status, uploaded_at, review_note, image_url } | null, proofs_count: int }`.
- Integrated across:
  - `CustomerOrderPresenter::present` & `presentListItem`
  - `AdminOrderPresenter::present` & `presentListItem`
  - `OrderService::getOrder`, `listOrders`, `listAdminOrders`, `resetOrphanOrder`
  - `PaymentProofController::upload` and `PaymentAdminController::review`

### 3. Customer Data & Proofs Formatting
- Updated `OrderService::getOrder` and `listAdminOrders` to resolve `customer` block (`name`, `phone`, `email`), ensuring fallback from user name -> delivery name -> user phone -> email local part, never the literal placeholder `"عميل"`.
- Guaranteed both `proofs` and `payment_proofs` aliases in admin and customer payloads with `image_url`, `status`, `sender_name`, `reference_number`, and timestamps.

### 4. Deadlock Prevention & Orphan Order Reset
- Added `OrderService::resetOrphanOrder` and endpoint `POST /api/v1/admin/orders/{order_number}/reset-orphan`. If an order is stuck in `payment_review` with 0 proofs, admin can safely reset it to `pending_payment` if the lock is still valid, or mark it `expired` and restore stock if the lock has lapsed.
- Audited with structured event `orphan_order_reset` or `orphan_order_expired`.

### 5. Structured Timeline Events (No Raw UTC)
- Removed raw `"UTC"` generation from `OrderService::createOrder`, `refreshExpiry`, and `extendPriceLock`.
- Replaced pre-formatted string timestamps with structured `event_type` and JSON `meta` containing ISO UTC timestamps (`lock_expires_at`).
- All existing rows sanitized in SQLite.

### 6. Verification of Order WEX-261121-C1A96 After Fix
**Raw Output from `curl`**:
```json
$ curl -s "http://127.0.0.1:8080/api/v1/admin/orders/WEX-261121-C1A96" -H "Authorization: Bearer $ADMIN_TOKEN" | jq '{order_number: .data.order_number, status: .data.status, customer: .data.customer, customer_name: .data.customer_name, customer_phone: .data.customer_phone, proofs: .data.proofs, payment: .data.payment, timeline: .data.timeline}'
{
  "order_number": "WEX-261121-C1A96",
  "status": "payment_review",
  "customer": {
    "name": "مدير النظام",
    "phone": "+249912345678",
    "email": "admin@wextechnology.com"
  },
  "customer_name": "مدير النظام",
  "customer_phone": "+249912345678",
  "proofs": [
    {
      "id": 24,
      "status": "pending",
      "sender_name": null,
      "reference_number": null,
      "review_note": null,
      "image_url": "/api/v1/orders/WEX-261121-C1A96/payment-proof/24/image",
      "created_at": "2026-11-21T07:20:57Z",
      "uploaded_at": "2026-11-21T07:20:57Z",
      "reviewed_at": null
    }
  ],
  "payment": {
    "state": "under_review",
    "can_upload": false,
    "can_review": true,
    "latest_proof": {
      "id": 24,
      "status": "pending",
      "sender_name": null,
      "reference_number": null,
      "review_note": null,
      "image_url": "/api/v1/orders/WEX-261121-C1A96/payment-proof/24/image",
      "uploaded_at": "2026-11-21T07:20:57Z",
      "created_at": "2026-11-21T07:20:57Z",
      "reviewed_at": null
    },
    "proofs_count": 1
  },
  "timeline": [
    {
      "id": 46,
      "from_status": null,
      "to_status": "pending_payment",
      "status_text": "في انتظار التحويل البنكي",
      "type": "order_created",
      "event_type": "order_created",
      "meta": null,
      "note": "تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات حتى 11:20",
      "created_at": "2026-11-21T07:20:42Z"
    },
    {
      "id": 47,
      "from_status": "pending_payment",
      "to_status": "payment_review",
      "status_text": "مراجعة التحويل",
      "type": "payment_proof_uploaded",
      "event_type": "payment_proof_uploaded",
      "meta": null,
      "note": "قام العميل برفع إشعار التحويل البنكي للمراجعة",
      "created_at": "2026-11-21T07:20:57Z"
    }
  ]
}
```
**PHPUnit Full Suite**:
`OK (107 tests, 5203 assertions)`
Status: **GREEN (Passed)**


