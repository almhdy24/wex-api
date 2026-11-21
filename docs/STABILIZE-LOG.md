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
