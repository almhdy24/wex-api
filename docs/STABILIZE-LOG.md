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
