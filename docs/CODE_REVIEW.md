# Wex Deep Code Review — wex-api + wex-web

Branch `stabilize` in both repos · review date 2026-11-25 · companion docs: `docs/SYSTEM_MAP.md`, `docs/REVIEW_COVERAGE.md`.

## 1. Executive summary

**Verdict: NOT READY for client delivery.**

Why:
1. **Review coverage is incomplete.** Files read fully or partially: wex-api 90/296 (~30%), wex-web 48/432 (~11%). Reading was prioritised by risk: auth, orders, stock, payments, uploads, push, admin writes, the API client, redirects and React hooks. The unread files are covered only by automated gates (lint, `php -l`, PHPUnit, vitest, contract-check), not by a human pass.
2. **The end-to-end suite is still red.** The last run (45624593840) failed specs 03 (admin order lifecycle), 06 (push: a stale heading selector after this review renamed the heading, now fixed but not yet re-run) and 07 (home IA order). The full customer and admin journeys, concurrency, authorization and upload e2e scenarios requested in Phase 4 were **not** written.
3. The visual snapshot job has never passed in its history. Its remaining failure (`net::ERR_FAILED` console errors in the harness) is UNVERIFIED.

What *is* in good shape now: 45 defects were fixed (15 High, 24 Medium, 6 Low), each with a regression test that was run against the old code and shown to fail (with the exceptions noted in the table). The API PHPUnit suite is green in CI (run 45620097658), wex-web unit tests and ESLint are green in CI (ui-check 45624593976 got past vitest/lint), and the static frontend/API contract check reports 0 mismatches.

Several of the fixed defects would each have been serious in production:
- Any email could log in as any user, admins included, on non-production servers.
- Google sign-in accepted tokens issued to other apps when unconfigured.
- Product pages looped forever on the API (346 requests/s per view).
- Concurrent admin actions restored stock twice, or confirmed cancelled orders, leading to overselling.
- Admin PUT features crashed outright.
- Customers were sent to placeholder phone numbers that may belong to strangers.
- A previous account's orders were shown on shared devices.
- The price-lock countdown could be off by hours.

## 2. Findings

Severity: Blocker / High / Medium / Low. Status: Fixed (with a test shown to fail before and pass after, unless noted), Deferred, UNVERIFIED.
Commit hashes are in wex-api unless prefixed `web:`.

| ID | Sev | Area | file:line (at fix time) | Problem | Impact | Fix | Commit | Test | Status |
|---|---|---|---|---|---|---|---|---|---|
| API-AUTH-01 | High | API | Controllers/Api/V1/AuthController.php `testingLogin` | Minted tokens for any email whenever `CI_ENVIRONMENT != production` | Full account takeover (admin) on staging/misconfigured prod | Requires `TESTING_LOGIN_ENABLED=true` and never in production | a664ba1 | AuthHardeningTest | Fixed |
| API-AUTH-02 | High | API | Services/GoogleAuthService.php verifyIdToken | `aud` check skipped when `GOOGLE_CLIENT_IDS` empty | Token substitution from any Google app | Fail closed | 0f11fe9 | AuthHardeningTest | Fixed |
| API-AUTH-03 | Medium | API | Services/OtpService.php verify | Attempt counter and single-use not atomic | >5 guesses in parallel; one code logs in twice | Conditional UPDATEs | 8482196 | AuthHardeningTest | Fixed |
| API-AUTH-04 | Medium | API | Services/OtpService.php generate | No per-email issuance cap (IP-only throttle) | OTP brute force via IP rotation | 5 codes/email/hour | 8482196 | AuthHardeningTest | Fixed |
| API-ORD-01 | High | API | Services/OrderService.php updateOrderStatus | Status written without compare-and-set | Concurrent cancels restored stock twice | `casStatus`, 409 on conflict | eeef0af | OrderIntegrityTest | Fixed |
| API-ORD-02 | Medium | API | Services/OrderStateMachine.php shouldRestoreStock | No restock when cancelling from ready/out_for_delivery | Permanent inventory loss | Restore from every non-terminal state | eeef0af | OrderIntegrityTest | Fixed |
| API-ORD-03 | Medium | API | OrderStateMachine | Pickup order could go out_for_delivery and vice versa | Wrong customer notifications/flow | Delivery-type guard | eeef0af | OrderIntegrityTest | Fixed |
| API-ORD-04 | High | API | Services/PaymentProofService.php reviewProof | No order-status guard; review race | Approving a proof re-confirmed cancelled/expired orders → oversell | Require `payment_review`; CAS proof + order | 2bb2333 | OrderIntegrityTest | Fixed |
| API-ORD-05 | Medium | API | OrderService / PaymentProofService | Admin order and payment mutations unaudited | No accountability | Audit entries with actor + diff | eeef0af, 2bb2333 | OrderIntegrityTest | Fixed |
| API-ORD-06 | Medium | API | OrderService::refreshExpiry | Expire + restock not transactional | Expired order with stock never released | One transaction | eeef0af | OrderTest (existing) | Fixed (no new fail-before test) |
| API-ORD-07 | Medium | API | POST /orders | No idempotency | Double tap = two orders, double reservation | `Idempotency-Key` + unique (user_id, key) | 1b031ea, 5fa3114 | OrderIntegrityTest | Fixed (the unique-index test passes on old code because the persisted test DB keeps the schema; disclosed) |
| API-ORD-08 | Medium | API | OrderService::generateOrderNumber | Hex suffix could spell "AED" | Internal currency shown to customers; flaky CI | Vowel-free alphabet | 3651218 | OrderNumberTest | Fixed |
| API-INT-01 | High | API | Services/IntegrityService.php repairIntegrity | Repair duplicated expiry logic without CAS | Double stock restore racing admin/expiry | Reuse casStatus/refreshExpiry | 4ebaafb | IntegrityRepairRaceTest | Fixed |
| API-PUSH-01 | High | API | PushController::subscribe, WebPushProvider | Any URL accepted as push endpoint | SSRF (internal hosts, cloud metadata) | HTTPS push-service host allowlist | 42fb802 | PushEndpointPolicyTest | Fixed |
| API-PUSH-02 | Medium | API | PushService::deliverRows | No claim before sending | Duplicate push notifications | Lease on `next_attempt_at` | d1c9b8d | PushOutboxClaimTest | Fixed |
| API-MAIL-01 | Medium | API | MailService::processOutbox | No claim before sending | Duplicate emails on overlapping cron | Lease on `available_at` | 75559f8 | MailOutboxClaimTest | Fixed |
| API-PRD-01 | High | API | ProductService::updateProduct | PATCH unvalidated | Price 0/negative/"abc" → free items; negative stock | Validate every present field | 8b0fabc | ProductAdminHardeningTest | Fixed |
| API-PRD-02 | Medium | API | ProductAdminController | Product mutations unaudited | No accountability | Audit with diff | 8b0fabc | ProductAdminHardeningTest | Fixed |
| API-TAX-01/02/03 | Medium | API | Category/BrandAdminController | Delete-in-use and duplicate slug → 500; unaudited | Admin errors, no audit | 409/422, audit | f9ea64b | TaxonomyAdminTest | Fixed |
| API-USR-01 | Medium | API | MeController::delete | Soft delete kept unique email/PII/push | Re-signup 500; PII retained | Anonymise + deactivate push in a transaction | 2013eb4 | AccountDeletionTest | Fixed |
| API-INP-01 | Low | API | 20 admin write paths | `(bool)"false" === true` | Deactivation activated the user (non-JSON clients) | `Input::bool` | 8b237a3 | InputTest, AdminTest | Fixed |
| API-XSS-01 | Low | API | AnnouncementService, SettingService | `javascript:` links accepted (React 19 blocks at render) | Defence in depth | `SafeUrl` | 60834ab | SafeUrlTest + endpoint tests | Fixed |
| API-CONTACT-01 | High | API+Web | SettingsSeeder, SettingsController::shop, emails/ready_for_pickup, DesktopHeader, FaqPage, shopSettings | Placeholder phone/WhatsApp numbers seeded, served, emailed and hard-coded | Customers contacted unknown numbers | Settings-only numbers; links hidden when unset | 539b490, web:087c279 | NoPlaceholderContactTest, contact-numbers.test.jsx | Fixed |
| CON-01 | High | Web+API | AdminRecentPurchasesPage.jsx; RecentPurchasesAdminController | Page sent `enabled`, API reads `ticker_enabled` | Ticker could never be enabled; UI lied | Send the right field; 422 when missing | web:d1025fd, 2dd7816 | contract-check, RecentPurchasesTest | Fixed |
| CORS-01 | Medium | API | Filters/CorsFilter.php | `Date` not exposed | Countdown server clock unavailable cross-origin | Expose `Date` | 02cab4c | PingTest | Fixed |
| API-DEP-01 | Medium | API | composer.json | `php ^8.2` while lock needs 8.3 | `composer install` fails on 8.2 servers | `^8.3` | de444fa | CI | Fixed |
| WEB-CHK-01 | High | Web | CheckoutPage.jsx | Sent `notes`, API reads `customer_notes` | Every customer note silently lost | Correct field | web:5ee6100 | checkout-idempotency.test.js, contract-check | Fixed |
| WEB-CHK-02 | Medium | Web | CheckoutPage.jsx | No idempotency key | Duplicate orders | Key per payload | web:5ee6100 | checkout-idempotency.test.js | Fixed |
| WEB-API-01 | High | Web | api/client.js | `api.put` undefined | Push prefs, admin push settings, image reorder crashed | Added `put` | web:f780384 | api-put.test.js | Fixed |
| WEB-API-02 | Low | Web | api/client.js | Offline refresh shown as "session expired" | Misleading UX | Rethrow non-401 | web:ab970a7 | api-put.test.js | Fixed |
| WEB-AUTH-01 | High | Web | context/AuthContext.jsx | SWR cache kept across logout/login | Next account saw previous account's orders | Clear on logout/login | web:49d6c53 | auth-cache-isolation.test.jsx | Fixed |
| WEB-SEC-01 | Medium | Web | RouteGuard GuestRoute, utils/url.js | `returnTo` unvalidated; `/\evil` bypass | Open redirect | Validate + harden | web:15fe693 | open-redirect.test.jsx | Fixed |
| WEB-SEC-02 | Low | Web | utils/swPushHelpers.js | Push deep link `/\evil` | Cross-origin navigation | Always parse origin | web:a0da085 | open-redirect.test.jsx | Fixed |
| WEB-TIME-01 | High | Web | api/client.js | Server clock taken from SW-cached responses | Price-lock countdown off by up to 12 h | Only `no-store` responses | web:3ff1a8d | server-clock.test.js | Fixed |
| WEB-PERF-01 | High | Web | pages/store/ProductDetailPage.jsx loadData | Effect dep on a cache object → infinite refetch | 346 requests/s per PDP view (API load, data, battery) | Read cache at call time | web:ffd586f | pdp-fetch-loop.test.jsx | Fixed |
| WEB-HOOK-01 | Medium | Web | AdminUsersPage.jsx | Stale `updateParam` in memoised chips | Removing a chip wiped the search | Functional updates | web:218cfbd | hooks-regressions.test.jsx | Fixed |
| WEB-ADM-01 | High | Web | AdminUsersPage.jsx emptyAction | Object passed as React child | Users page crashed on any empty filter result | Button node | web:218cfbd | hooks-regressions.test.jsx | Fixed |
| WEB-HOOK-02 | Medium | Web | AdminOrderDetailPage.jsx | Receipt blob URLs never revoked | Memory leak | Per-run URL | web:218cfbd | hooks-regressions.test.jsx | Fixed |
| WEB-HOOK-03 | Medium | Web | (Admin)OrderDetailPage.jsx | Older response overwrote newer order | Wrong order on screen | Latest-request guard | web:218cfbd | hooks-regressions.test.jsx | Fixed |
| WEB-HOOK-04 | Medium | Web | OrdersPage.jsx | Retry passed click event as AbortSignal | Retry never worked | Arrow wrapper; stable fetcher | web:218cfbd | hooks-regressions.test.jsx | Fixed |
| WEB-HOOK-05 | Low | Web | LoginPage, RecentPurchasesToast, AdminRatesPage | exhaustive-deps violations | Stale callbacks | Refs/stable callbacks | web:218cfbd | lint (0 errors) | Fixed (no behaviour test) |
| WEB-LINT-01 | Medium | Web | eslint.config.js | react-hooks plugin loaded but rules off; ESLint not in CI | Hook bugs undetected | Rules as error; ESLint in ui-check | web:218cfbd, web:44d3fe3 | CI | Fixed |
| WEB-CART-01 | Medium | Web | context/CartContext.jsx | Malformed stored cart crashed every render | Storefront unusable until storage cleared | `sanitizeStoredCart` | web:80f635d | cart-storage.test.js | Fixed |
| WEB-I18N-01 | Low | Web | AdminPushSection, ImageManager | English UI strings | Policy violation | Arabic copy | web:907aaa6 | check-admin-english | Fixed |
| CI-01 | Medium | CI | both repos | API had no CI; ui-check PHP 8.2/Node 20; vitest ran e2e specs; stale tests | Gates never ran | phpunit.yml; versions; excludes; tests updated | c8bf3cd, web:627e24f, web:b90653f, web:462ed0b | CI | Fixed |
| E2E-03 | ? | Web | e2e/tests/03-admin-payments-lifecycle.spec.js:30 | `waitForURL` timeout after clicking first order card (mobile) | Admin journey unproven | — | — | — | UNVERIFIED |
| E2E-07 | ? | Web | e2e/tests/07-quality-gates.spec.js:89 | Home IA order assertion fails on seeded data | — | — | — | — | UNVERIFIED |
| VIS-01 | Low | Web | ProductDetailPage specs tab | Empty specs panel renders a blank card (no empty state) | Looks broken | — | — | screenshot | Deferred |
| VIS-02 | Low | Web | OrderDetailPage countdown | Shows `00:00:00` + "السعر مثبت لمدة" when the lock has passed but status is still pending | Confusing | — | — | screenshot | Deferred |
| VIS-03 | Low | Web | OrderDate | "م 8:00 2026/11/24" token order (bidi) | Hard to read | — | — | screenshot | Deferred |
| VIS-04 | Low | Web | Desktop home | ~1,200 px of empty navy below the footer; footer lists 4 steps vs 3 on home | Polish | — | — | screenshot | Deferred |
| VIS-05 | ? | Web | Admin order detail | Item/subtotal show `0 ج.س` while total is 680,000 | Wrong money display if real | — | — | screenshot (fixture may omit item SDG) | UNVERIFIED |
| VIS-06 | ? | Web | Desktop home | Product skeletons persist after network idle with empty content | Possible stuck loading | — | — | screenshot | UNVERIFIED |

Known but not fixed (Deferred):
- **D-1** (Low): access tokens stay valid for up to 15 minutes after logout-all (stateless JWT).
- **D-2** (Low): `iss` is not validated on access-token decode.
- **D-3** (Medium): the throttle keys on `getIPAddress()`. Behind a proxy without `Config\App::$proxyIPs`, every user shares one bucket. Deployment item.
- **D-4** (Low): the X-Request-Id header is echoed unvalidated.
- **D-5** (Low): CORS/security headers are absent on exception-handler responses.
- **D-6** (Low): the `axios` dependency is unused but still in package.json; removing it needs lockfile regeneration in CI.
- **D-7** (Low): there's no `orders.created_at` index.
- **D-8** (Low): `formatProductListItem` does one image query per product (N+1, 20 per page).
- **D-9** (Low): the AuthContext provider value isn't memoised, so every consumer re-renders.
- **D-10** (Medium): the contract check does not verify **response** fields.
- **D-11** (Low): integrity "Safe Repair 3" auto-approves proofs without an audit entry.

## 3. Top 10 open risks

1. Roughly 70% of API files and roughly 89% of web files never got a human read (see REVIEW_COVERAGE).
2. The e2e suite is red: the admin order lifecycle (03) and home IA (07) are unproven in a real browser.
3. The Phase 4 scenarios don't exist yet: concurrency e2e, authorization e2e, upload e2e, and the admin-route crawler with cross-surface sync.
4. Response-field contract drift isn't checked (D-10); a renamed field degrades silently.
5. A rate-limit bypass or global lockout behind a reverse proxy if `proxyIPs` isn't configured (D-3).
6. Order-detail money display showing 0 for items (VIS-05) needs checking against the real API.
7. The visual snapshot harness has never passed, so visual regressions go unnoticed.
8. The OTP 6-digit space is protected by per-IP and per-email caps only. There's no global anomaly alerting.
9. The service worker caches catalog GETs for 12 hours stale-while-revalidate. Customers can see stale prices or stock before revalidation; the server stays authoritative at checkout.
10. Placeholder or demo data could still exist in DB rows on an already-seeded production DB (the seeder only affects new seeds). Run `scripts/audit-db.sh` and check settings in admin.

## 4. Security checklist

| Item | Result |
|---|---|
| JWT: HS256, secret ≥32 chars enforced in production, 15 min access, rotating refresh with reuse detection, 30-day sliding / 180-day cap | Pass (D-1, D-2 open) |
| Refresh token storage | HttpOnly cookie (SameSite Lax by default); access token in memory only | Pass |
| Admin routes require auth + role | Pass: verified for all routes in SYSTEM_MAP (CI 4.7.6 merges group and route filters; staff→403 test exists) |
| IDOR: orders, receipts, notifications, push subscriptions scoped to owner | Pass by code read (OrderService::getOrder, PaymentProofService::getProofForStream, NotificationService::markAsRead, PushService delete by user). Not proven in e2e |
| Mass assignment | Controllers build explicit arrays; product PATCH now validated |
| SQL injection | No input concatenated into SQL in reviewed code; IntegrityService raw SQL is static |
| Stored XSS | React escapes; no `dangerouslySetInnerHTML`; link fields validated (SafeUrl) |
| CSRF | Bearer auth for mutations; refresh cookie is SameSite=Lax and responses are not CORS-readable cross-site |
| CORS | Allowlist with credentials, no wildcard; LAN/localhost only outside production |
| Rate limiting | Global 120/min; login/OTP/Google/push throttled; per-email OTP cap. Upload per-order 5/h. Depends on correct client IP (D-3) |
| Google token verification | iss, aud (fail closed), exp, email_verified checked |
| OTP | HMAC-SHA256 hashed, 10 min TTL, 5 attempts (atomic), single use (atomic), 60 s cooldown |
| Uploads | finfo MIME, size limit, GD re-encode (EXIF stripped), random names, receipts in `writable/` streamed after an owner/admin check. **Open:** no pixel-count limit (decompression bomb) and a direct-copy fallback if GD fails (Medium, Deferred) |
| SSRF | Push endpoints restricted (fixed) |
| Errors in production | Generic message when `CI_ENVIRONMENT=production`; class/file/line only outside production |
| Open redirects | returnTo and push deep links validated (fixed) |
| Secrets in repo | `.env` git-ignored; VAPID test keys exist in e2e.yml (CI-only fake provider). Rotate if ever reused |

## 5. Performance

- **PDP request loop fixed:** a single product view went from about 346 product fetches per second (plus shop and zones) to exactly 1.
- **Public list N+1:** one image query per product, 20 per page (D-8).
- **Indexes:** present on FKs, slug, status, user_id and lock_expires_at; `orders.created_at` is missing (D-7).
- **Bundle:** the axios re-export removed from the client entry (bundle size UNVERIFIED; check the CI build stats).
- Lighthouse and bundle numbers were not collected (no local builds on the phone, per the rules).

## 6. Production deployment checklist

- **Env:**
  - `CI_ENVIRONMENT=production`
  - `JWT_SECRET` at least 32 random bytes
  - `encryption.key` set
  - `GOOGLE_CLIENT_IDS` set (sign-in is refused otherwise)
  - `CORS_ALLOWED_ORIGINS` exact origins
  - `COOKIE_SECURE=true`, `COOKIE_SAMESITE=Lax` (same-site deployment) or `None` together with Secure
  - `TESTING_LOGIN_ENABLED` unset
  - `MAIL_DRIVER=smtp` plus SMTP creds
  - VAPID keys (`php spark push:keys`)
  - `PUSH_PROVIDER` real, `PUSH_FAKE_LOG` off
- **PHP:** 8.3 or newer with gd, intl, mbstring, sqlite3/mysqli.
- **Migrations:** `php spark migrate --all` (new: `2026-11-26-000001_AddIdempotencyKeyToOrders`).
- **Admin settings:** set real `shop_phone` / `shop_whatsapp` in admin settings. Contact links are hidden until set.
- **Cron:**
  - `php spark orders:expire` every minute
  - `php spark push:work` every minute
  - `php spark push:reminders` every 5 minutes
  - `php spark mail:send` every minute (overlap-safe now)
  - `php spark integrity:check` daily, alerting on violations
- **Proxy:** set `Config\App::$proxyIPs` to the reverse proxy so throttling sees real client IPs.
- **Uploads:** `writable/uploads/proofs` 0700, owned by the PHP user; `public/uploads/products` writable; web server must not serve `writable/`.
- **HTTPS:** enable HSTS (sent automatically in production) and set `X-Forwarded-Proto` at the proxy.
- **Web build:** set `VITE_API_BASE_URL` and `VITE_GOOGLE_CLIENT_ID`; no source maps in production (UNVERIFIED, check `vite.config.js` `build.sourcemap`).
- **Service-worker busting:** each deploy changes the precache manifest, and `cleanupOutdatedCaches` runs. Verify that a new deploy reaches open tabs (the PWA prompt).
- **Backups:** daily DB dump plus `writable/uploads/proofs`, with a restore drill.
- **Logging and monitoring:** collect `writable/logs` with rotation, an error tracker for both apps, and alerts on 5xx rate and on `Audit log write failed`.
- **Data hygiene:** run `scripts/audit-db.sh` and `scripts/cleanup-dev-data.sh` (refuses in production) before go-live on a copied DB.
