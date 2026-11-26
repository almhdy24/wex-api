# Storefront and Admin UX Audit

Evidence types:
- **T**: automated test (vitest/phpunit/e2e) that fails without the change
- **S**: CI screenshot opened and read
- **C**: code reading only (UNVERIFIED visually)

## Changes made, by page

| Page | Before | After | Evidence |
|---|---|---|---|
| Home | "الأكثر طلباً" row showed featured items (false popularity claim) | "مختارات المتجر"; claim scanners block popularity claims | T (home-rescue, check-claims mutation) |
| Home / footer / FAQ / desktop header | Hard-coded placeholder WhatsApp/phone numbers | Numbers only from admin settings; links hidden when unset | T (contact-numbers) |
| Catalog search | Raw substring LIKE: «ديل» found nothing, hamza/taa marbuta/diacritics/Arabic digits missed, multi-word = one phrase | Normalised, cross-script (Dell↔ديل, Lenovo↔لينوفو…), every word must match at a word start | T (ArabicSearchTest) |
| Product page | Endless 3-request refetch loop (346 product fetches/s) | One fetch per slug | T (pdp-fetch-loop) |
| Product page | Share = copy link only | WhatsApp send with name, SDG price and URL; server-rendered link preview (og:title/og:image/Product JSON-LD) | T (shop-settings, SeoTest), smoke (Apache) |
| Product data | SW served prices/stock stale for up to 12 h | Network-first, 10-minute cache cap (offline fallback only) | T (pwa-workbox) |
| Checkout | Customer notes silently dropped; double tap = two orders | `customer_notes` sent; Idempotency-Key | T |
| Order page | Delivery orders showed "الاستلام"; countdown could be off by hours (cached Date header) | Uses `delivery_type`; clock only from no-store responses | T |
| Order page | No help path | "استفسر عن هذا الطلب عبر واتساب" with the order number prefilled | T (helpers) |
| Orders list | Retry button never worked (click event passed as AbortSignal) | Works | T |
| Account | "إيصالات السداد" | "إشعارات التحويل" (glossary) | check-copy |
| Admin orders (mobile) | Tapping a card body did nothing | Whole card opens the order | T (datalist-card-click), e2e 03 |
| Admin order detail | Address never shown, area always "الخرطوم"; items showed 0 ج.س in fixtures | Real `delivery_address` + `shipping_zone_name`; fixtures fixed | T (ResponseContractTest, order-fields-contract) |
| Admin users | Empty filtered list crashed the page; removing a filter chip wiped the search | Fixed | T (hooks-regressions) |
| Admin products | Brand always "ماركة غير محددة" | Real `brand_name` | T (ResponseContractTest) |
| Admin recent purchases | Toggle always saved "disabled" | Fixed | T + contract-check |
| All pages | Duplicate concurrent GETs (e.g. /announcements ×3) | Shared in-flight GET | T |

## Checklist status (Phase 2)

| Area | Status |
|---|---|
| IA: bottom nav Home / Catalog / Cart / Orders / Account; search one tap away | Present (C, S) |
| Breadcrumbs on desktop | Present for admin. Storefront desktop breadcrumbs **not verified** |
| Home: one promise + one CTA; featured/newest above the fold; how-it-works 3 steps; store block | Present (S 360/1280). Hero rotates 3 slides, so it is not a single promise (**open**) |
| Catalog: sticky search, filter sheet, chips, sort, count, empty-results help | Present (C). Scroll restoration and infinite vs paginated behaviour **not verified** |
| Arabic search normalisation | **Done** (API). Client-side normalisation is not needed because the API normalises |
| Typo tolerance | **Not done** (no cheap safe option on MySQL LIKE; candidate: trigram table) |
| Product card badges from real low-stock threshold | API `stock_status` uses the setting (C). Badge copy **not re-audited** |
| Sticky add-to-cart, gallery swipe/zoom, specs chips + table | Present (C, S) |
| Related products / compatible accessories | **Done** (web:c31d3da). Correction: accessories were *not* present; the app-shell redesign had dropped the section. Now 'ملحقات متوافقة' (admin-linked) and 'أجهزة مشابهة' (same category, one request; skipped on Data Saver) (T) |
| Cart/checkout: guest until order step, inline Arabic validation, zone "name - fee", totals incl. delivery, double-submit protection, cart kept on error | Present (C, T for idempotency/notes) |
| Post-order: lands on receipt upload, bank accounts + copy, countdown only while pending | Present (S). VIS-02 **fixed** (web:adf566a, T) |
| Push opt-in timing | Unchanged (built earlier) |
| Offline page, skeletons, error states with next action | Present (C). Each state was **not** individually screenshotted |
| Accessibility: contrast check script, 44 px targets (admin audit), RTL logical CSS | Scripts exist and pass in CI. A full manual a11y pass was **not done** |
| Weak network: code splitting, lazy images, font-display | Present (C). **Bundle size not measured in this pass**; WebP/AVIF srcset **not implemented**; low-data mode **done** for network: Data Saver / 2G skips route prefetch, similar devices and the purchases ticker (web:62c6eb6, T) |

## Admin structure (Phase 5): present, not reworked

Checked in code on 2026-11-26: the dashboard leads with the payment-review alert; bulk actions exist on orders and products; quick stock edit on the product list (`adminApi.updateStock`); settings are grouped into five titled cards; "معاينة في المتجر" links exist on the product list and the edit form. None was redesigned in this pass. The existing e2e (spec 04) proves settings→storefront sync for `orders_enabled` only.
