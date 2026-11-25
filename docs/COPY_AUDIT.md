# Copy Audit (Arabic, fusha)

Standard: `docs/GLOSSARY.ar.md`. Automated gates:
- `wex-web/scripts/check-copy.mjs`: glossary synonyms, dialect, Latin punctuation inside Arabic, double spaces, and AED/exchange-rate wording on customer surfaces. It scans 254 files across web and API.
- `wex-web/scripts/check-claims.mjs` and `wex-api/tests/unit/ClaimsScanTest.php`: warranty, testing, inspection, authenticity and popularity claims, placeholders.
- `wex-web/scripts/check-admin-english.mjs`: English words in the admin UI.

All of these run in CI.

**Scope honesty:** every string was scanned by the automated rules above. A *human* line-by-line language edit of every string, as Phase 3.1 asks, was **not** completed. The table lists every change made in this pass. Strings not listed passed the automated rules but were not hand-edited.

| # | Key / location | Before | After | Reason |
|---|---|---|---|---|
| 1 | web `src/utils/orderState.js` (customer status label, 2×) | جاهز للاستلام / الشحن | جاهز للاستلام أو التوصيل | Glossary: التوصيل, not الشحن; no slash in sentences |
| 2 | web `src/pages/store/AccountPage.jsx:442` | استعرض تفاصيل الشراء وإيصالات السداد وحالة التوصيل | تفاصيل طلباتك وإشعارات التحويل وحالة التوصيل | Glossary: إشعار التحويل; concise |
| 3 | web `src/pages/store/HomePage.jsx` section title | الأكثر طلباً | مختارات المتجر | Popularity claim without data (the row lists featured items) |
| 4 | api migration hero slide 3 subtitle (+ data migration) | حقائب، شواحن أصلية، ومحولات متوافقة بأسعار مدروسة | حقائب وشواحن ومحولات متوافقة مع جهازك | Unverifiable authenticity claim |
| 5 | api `ProductsSeeder` (demo) | شاحن لابتوب أصلي 65 واط | شاحن لابتوب 65 واط | Authenticity claim |
| 6 | api `ProductsSeeder` (demo) | شاحن أصلي لحماية بطارية الجهاز من تذبذب التيار. | شاحن بقدرة 65 واط متوافق مع أغلب أجهزة اللابتوب ذات المنفذ المطابق. | Claim + unverifiable benefit |
| 7 | api `ProductsSeeder` (demo) | شريحة رام أصلية لرفع كفاءة اللابتوب… | شريحة ذاكرة عشوائية لزيادة سعة الذاكرة في اللابتوب. | Claim; Arabic term for RAM |
| 8 | web `AdminRatesPage.jsx` unit | جنيه سوداني | ج.س لكل درهم | Currency label |
| 9 | web `AdminSettingsPage.jsx` placeholder | …عبر شركات الشحن... | …والولايات المحددة. | Glossary; no ellipsis |
| 10 | web `AdminUsersPage.jsx` staff role | …فحص إشعارات التحويل البنكي، وتحديث حالات الشحن. | …مراجعة الطلبات وإشعارات التحويل البنكي، وتحديث حالات الطلبات. | Glossary; "فحص" implies an inspection claim |
| 11 | web `src/content/admin.ar.js` | مناطق التوصيل ورسوم الشحن | مناطق التوصيل ورسومها | Glossary |
| 12 | api `ShippingZoneController` (public message) | مناطق التوصيل ورسوم الشحن | مناطق التوصيل ورسومها | Glossary |
| 13 | api `SettingService` validation | …بين 1 و 1000 جنيه. | …بين 1 و 1000 ج.س. | Currency label |
| 14 | api `SettingService` validation | …جنيه سوداني للدرهم. | …ج.س للدرهم. | Currency label |
| 15 | web `AdminPushSection.jsx` heading | نظام الإشعارات الفورية (Push) | نظام الإشعارات الفورية | No English in UI |
| 16 | web `ImageManager.jsx` placeholder | وصف الصورة (Alt)... | وصف الصورة النصي لقارئات الشاشة... | No English in UI |
| 17 | api email `ready_for_pickup` | هاتف المحل: +249 9XX XXX XXX (fallback) | row hidden when unset | No placeholder data |
| 18 | api upload errors (new) | — | تعذّرت قراءة الصورة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP. / أبعاد الصورة كبيرة جداً، يرجى رفع صورة أصغر. | What happened + what to do |
| 19 | api installer/cron messages (new) | — | رمز التثبيت غير صحيح. / تم التثبيت مسبقاً… / غير مصرّح بتنفيذ المهام المجدولة. | Fusha, actionable |
| 20 | web PDP / order page (new) | — | أرسله عبر واتساب / استفسر عن هذا الطلب عبر واتساب | Verb-first actions |

Count: **20 copy changes** (14 edits of existing strings, 6 new strings).

Open items (not done): a full hand edit of push templates, all email bodies, the FAQ/pages seed content and every admin form label against the glossary, plus a `pluralAr` audit of count strings. These are listed as risks in `docs/DELIVERY_READINESS.md`.
