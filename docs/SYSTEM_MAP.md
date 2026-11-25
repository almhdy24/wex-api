# Wex System Map

_Generated 2026-11-25 from source by the deep-review pass (routes: `app/Config/Routes.php`; schema: PRAGMA introspection of a fully migrated SQLite DB; frontend calls: `wex-web/scripts/contract-check.js --json`). Regenerate rather than hand-edit._

## Contents
1. API routes
2. Database schema
3. Models (`$allowedFields`)
4. Frontend API calls
5. Settings keys
6. State machines (order / payment / price lock)
7. Environment variables
8. Contract check status

## 1. API routes (142)

Base path `/api/v1`. Global filters on every request: `cors` (required), `security`, `forcehttps`, `throttle:120,60,global`. `auth` = Bearer JWT (HS256, 15 min) + active-user check; `role:x,y` = user.role in list (both group and route `role:` filters run). Responses use the envelope `{success, message, data, errors, meta}`.

| method | path | filters | handler | frontend callers |
|---|---|---|---|---|
| GET | `/ping` | public | `PingController::index` | — |
| POST | `/auth/google` | throttle:30,60,google_auth | `AuthController::google` | src/api/auth.js:26 |
| POST | `/auth/email/request` | throttle:10,3600,otp_req | `AuthController::requestEmailOtp` | src/api/auth.js:8 |
| POST | `/auth/email/verify` | throttle:10,600,otp_ver | `AuthController::verifyEmailOtp` | src/api/auth.js:19 |
| POST | `/auth/testing-login` | public | `AuthController::testingLogin` | — |
| POST | `/auth/refresh` | public | `AuthController::refresh` | — |
| POST | `/auth/logout` | public | `AuthController::logout` | src/api/auth.js:45 |
| POST | `/auth/logout-all` | auth | `AuthController::logoutAll` | src/api/auth.js:52 |
| GET | `/me` | auth | `MeController::show` | src/api/auth.js:59 |
| PATCH | `/me` | auth | `MeController::update` | src/api/auth.js:69 |
| PATCH | `/me/preferences` | auth | `MeController::updatePreferences` | src/api/auth.js:76 |
| POST | `/me/link-google` | auth | `MeController::linkGoogle` | src/api/auth.js:85 |
| POST | `/me/unlink-google` | auth | `MeController::unlinkGoogle` | src/api/auth.js:92 |
| DELETE | `/me` | auth | `MeController::delete` | src/api/auth.js:99 |
| GET | `/orders` | auth | `OrderController::index` | src/api/orders.js:15 |
| POST | `/orders` | auth | `OrderController::create` | src/api/orders.js:29, src/pages/store/CheckoutPage.jsx:241 |
| GET | `/orders/:param` | auth | `OrderController::show` | src/api/orders.js:22 |
| POST | `/orders/:param/cancel` | auth | `OrderController::cancel` | src/api/orders.js:36 |
| POST | `/orders/:param/reorder` | auth | `OrderController::reorder` | src/api/orders.js:43 |
| POST | `/orders/:param/payment-proof` | auth | `PaymentProofController::upload` | src/api/orders.js:50 |
| GET | `/orders/:param/payment-proof/:param/image` | auth | `PaymentProofController::streamImage` | src/api/admin.js:49, src/api/orders.js:57 |
| GET | `/notifications` | auth | `NotificationController::index` | src/api/notifications.js:15 |
| GET | `/notifications/unread-count` | auth | `NotificationController::unreadCount` | src/api/notifications.js:22 |
| PATCH | `/notifications/:param/read` | auth | `NotificationController::markRead` | src/api/notifications.js:29 |
| POST | `/notifications/mark-all-read` | auth | `NotificationController::markAllRead` | src/api/notifications.js:36 |
| GET | `/push/config` | auth, throttle:60,60,push | `PushController::config` | src/api/push.js:5 |
| GET | `/push/subscriptions` | auth, throttle:60,60,push | `PushController::listSubscriptions` | src/api/push.js:9 |
| POST | `/push/subscriptions` | auth, throttle:60,60,push | `PushController::subscribe` | src/api/push.js:13 |
| DELETE | `/push/subscriptions` | auth, throttle:60,60,push | `PushController::unsubscribe` | src/api/push.js:17 |
| DELETE | `/push/subscriptions/:param` | auth, throttle:60,60,push | `PushController::deleteSubscription` | src/api/push.js:23 |
| GET | `/push/preferences` | auth, throttle:60,60,push | `PushController::getPreferences` | src/api/push.js:27 |
| PUT | `/push/preferences` | auth, throttle:60,60,push | `PushController::updatePreferences` | src/api/push.js:31 |
| POST | `/push/test` | auth, throttle:60,60,push, throttle:10,60,push_test | `PushController::sendTest` | src/api/push.js:35 |
| GET | `/shop` | public | `SettingsController::shop` | src/api/products.js:43, src/api/shop.js:8 |
| GET | `/categories` | public | `CategoryController::index` | src/api/products.js:29 |
| GET | `/brands` | public | `BrandController::index` | src/api/products.js:36 |
| GET | `/products` | public | `ProductController::index` | src/api/products.js:15 |
| GET | `/products/:param` | public | `ProductController::show` | src/api/products.js:22 |
| GET | `/shipping-zones` | public | `ShippingZoneController::index` | src/api/products.js:50 |
| GET | `/announcements` | public | `AnnouncementController::index` | src/api/products.js:92, src/components/store/AnnouncementBanner.jsx:43, src/components/store/AnnouncementToast.jsx:81 … |
| GET | `/recent-purchases` | public | `RecentPurchasesController::index` | src/api/products.js:99, src/components/store/RecentPurchasesToast.jsx:90 |
| GET | `/hero-slides` | public | `ContentController::heroSlides` | — |
| GET | `/home-sections` | public | `ContentController::homeSections` | — |
| GET | `/faq` | public | `ContentController::faq` | — |
| GET | `/pages` | public | `ContentController::pages` | — |
| GET | `/pages/:param` | public | `ContentController::page` | — |
| GET | `/content/hero-slides` | public | `ContentController::heroSlides` | src/api/products.js:57 |
| GET | `/content/home-sections` | public | `ContentController::homeSections` | src/api/products.js:64 |
| GET | `/content/faq` | public | `ContentController::faq` | src/api/products.js:71 |
| GET | `/content/pages` | public | `ContentController::pages` | src/api/products.js:78 |
| GET | `/content/pages/:param` | public | `ContentController::page` | src/api/products.js:85 |
| GET | `/admin/dashboard` | auth, role:admin,staff | `Admin\DashboardController::index` | src/api/admin.js:6 |
| GET | `/admin/orders` | auth, role:admin,staff | `Admin\OrderAdminController::index` | src/api/admin.js:18 |
| GET | `/admin/orders/:param` | auth, role:admin,staff | `Admin\OrderAdminController::show` | src/api/admin.js:22 |
| PATCH | `/admin/orders/:param/status` | auth, role:admin,staff | `Admin\OrderAdminController::updateStatus` | src/api/admin.js:30 |
| POST | `/admin/orders/:param/lock` | auth, role:admin,staff | `Admin\OrderAdminController::extendLock` | src/api/admin.js:34 |
| POST | `/admin/orders/:param/reset-orphan` | auth, role:admin,staff | `Admin\OrderAdminController::resetOrphan` | src/api/admin.js:38 |
| POST | `/admin/orders/:param/proofs/:param/review` | auth, role:admin,staff | `Admin\PaymentAdminController::review` | src/api/admin.js:42 |
| GET | `/admin/payment-accounts` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::index` | src/pages/admin/AdminDashboardPage.jsx:91, src/pages/admin/AdminPaymentAccountsPage.jsx:68 |
| POST | `/admin/payment-accounts` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::create` | src/pages/admin/AdminPaymentAccountsPage.jsx:138 |
| GET | `/admin/payment-accounts/:param` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::show` | — |
| PATCH | `/admin/payment-accounts/:param` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::update` | src/pages/admin/AdminPaymentAccountsPage.jsx:141 |
| DELETE | `/admin/payment-accounts/:param` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::delete` | src/pages/admin/AdminPaymentAccountsPage.jsx:175 |
| PATCH | `/admin/payment-accounts/:param/toggle` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::toggle` | src/pages/admin/AdminPaymentAccountsPage.jsx:158 |
| POST | `/admin/payment-accounts/reorder` | auth, role:admin,staff, role:admin | `Admin\PaymentAccountAdminController::reorder` | src/pages/admin/AdminPaymentAccountsPage.jsx:198 |
| GET | `/admin/announcements` | auth, role:admin,staff | `Admin\AnnouncementAdminController::index` | src/pages/admin/AdminAnnouncementsPage.jsx:49 |
| POST | `/admin/announcements` | auth, role:admin,staff | `Admin\AnnouncementAdminController::create` | src/pages/admin/AdminAnnouncementsPage.jsx:110 |
| GET | `/admin/announcements/:param` | auth, role:admin,staff | `Admin\AnnouncementAdminController::show` | — |
| PATCH | `/admin/announcements/:param` | auth, role:admin,staff | `Admin\AnnouncementAdminController::update` | src/pages/admin/AdminAnnouncementsPage.jsx:107 |
| DELETE | `/admin/announcements/:param` | auth, role:admin,staff | `Admin\AnnouncementAdminController::delete` | src/pages/admin/AdminAnnouncementsPage.jsx:138 |
| PATCH | `/admin/announcements/:param/toggle` | auth, role:admin,staff | `Admin\AnnouncementAdminController::toggle` | src/pages/admin/AdminAnnouncementsPage.jsx:122 |
| GET | `/admin/recent-purchases` | auth, role:admin,staff | `Admin\RecentPurchasesAdminController::index` | src/pages/admin/AdminRecentPurchasesPage.jsx:32 |
| PATCH | `/admin/recent-purchases/:param/toggle-hide` | auth, role:admin,staff | `Admin\RecentPurchasesAdminController::toggleHide` | src/pages/admin/AdminRecentPurchasesPage.jsx:66 |
| PATCH | `/admin/recent-purchases/settings` | auth, role:admin,staff | `Admin\RecentPurchasesAdminController::settings` | src/pages/admin/AdminRecentPurchasesPage.jsx:53 |
| GET | `/admin/hero-slides` | auth, role:admin,staff | `Admin\HeroSlideAdminController::index` | src/pages/admin/AdminHeroSlidesPage.jsx:49 |
| POST | `/admin/hero-slides` | auth, role:admin,staff | `Admin\HeroSlideAdminController::create` | src/pages/admin/AdminHeroSlidesPage.jsx:99 |
| GET | `/admin/hero-slides/:param` | auth, role:admin,staff | `Admin\HeroSlideAdminController::show` | — |
| PATCH | `/admin/hero-slides/:param` | auth, role:admin,staff | `Admin\HeroSlideAdminController::update` | src/pages/admin/AdminHeroSlidesPage.jsx:96 |
| DELETE | `/admin/hero-slides/:param` | auth, role:admin,staff | `Admin\HeroSlideAdminController::delete` | src/pages/admin/AdminHeroSlidesPage.jsx:147 |
| PATCH | `/admin/hero-slides/:param/toggle` | auth, role:admin,staff | `Admin\HeroSlideAdminController::toggle` | src/pages/admin/AdminHeroSlidesPage.jsx:111 |
| POST | `/admin/hero-slides/reorder` | auth, role:admin,staff | `Admin\HeroSlideAdminController::reorder` | src/pages/admin/AdminHeroSlidesPage.jsx:131 |
| GET | `/admin/home-sections` | auth, role:admin,staff | `Admin\HomeSectionAdminController::index` | src/pages/admin/AdminHomeSectionsPage.jsx:43 |
| POST | `/admin/home-sections` | auth, role:admin,staff | `Admin\HomeSectionAdminController::create` | src/pages/admin/AdminHomeSectionsPage.jsx:93 |
| GET | `/admin/home-sections/:param` | auth, role:admin,staff | `Admin\HomeSectionAdminController::show` | — |
| PATCH | `/admin/home-sections/:param` | auth, role:admin,staff | `Admin\HomeSectionAdminController::update` | src/pages/admin/AdminHomeSectionsPage.jsx:90 |
| DELETE | `/admin/home-sections/:param` | auth, role:admin,staff | `Admin\HomeSectionAdminController::delete` | src/pages/admin/AdminHomeSectionsPage.jsx:141 |
| PATCH | `/admin/home-sections/:param/toggle` | auth, role:admin,staff | `Admin\HomeSectionAdminController::toggle` | src/pages/admin/AdminHomeSectionsPage.jsx:105 |
| POST | `/admin/home-sections/reorder` | auth, role:admin,staff | `Admin\HomeSectionAdminController::reorder` | src/pages/admin/AdminHomeSectionsPage.jsx:125 |
| GET | `/admin/faq` | auth, role:admin,staff | `Admin\FaqAdminController::index` | src/pages/admin/AdminFaqPage.jsx:41 |
| POST | `/admin/faq` | auth, role:admin,staff | `Admin\FaqAdminController::create` | src/pages/admin/AdminFaqPage.jsx:85 |
| GET | `/admin/faq/:param` | auth, role:admin,staff | `Admin\FaqAdminController::show` | — |
| PATCH | `/admin/faq/:param` | auth, role:admin,staff | `Admin\FaqAdminController::update` | src/pages/admin/AdminFaqPage.jsx:82 |
| DELETE | `/admin/faq/:param` | auth, role:admin,staff | `Admin\FaqAdminController::delete` | src/pages/admin/AdminFaqPage.jsx:133 |
| PATCH | `/admin/faq/:param/toggle` | auth, role:admin,staff | `Admin\FaqAdminController::toggle` | src/pages/admin/AdminFaqPage.jsx:97 |
| POST | `/admin/faq/reorder` | auth, role:admin,staff | `Admin\FaqAdminController::reorder` | src/pages/admin/AdminFaqPage.jsx:117 |
| GET | `/admin/pages` | auth, role:admin,staff | `Admin\PageAdminController::index` | src/pages/admin/AdminPagesPage.jsx:41 |
| POST | `/admin/pages` | auth, role:admin,staff | `Admin\PageAdminController::create` | src/pages/admin/AdminPagesPage.jsx:87 |
| GET | `/admin/pages/:param` | auth, role:admin,staff | `Admin\PageAdminController::show` | — |
| PATCH | `/admin/pages/:param` | auth, role:admin,staff | `Admin\PageAdminController::update` | src/pages/admin/AdminPagesPage.jsx:84 |
| DELETE | `/admin/pages/:param` | auth, role:admin,staff | `Admin\PageAdminController::delete` | src/pages/admin/AdminPagesPage.jsx:115 |
| PATCH | `/admin/pages/:param/toggle` | auth, role:admin,staff | `Admin\PageAdminController::toggle` | src/pages/admin/AdminPagesPage.jsx:99 |
| GET | `/admin/products` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::index` | src/api/admin.js:61 |
| POST | `/admin/products` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::create` | src/api/admin.js:65 |
| GET | `/admin/products/:param` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::show` | src/api/admin.js:69 |
| PATCH | `/admin/products/:param` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::update` | src/api/admin.js:73 |
| DELETE | `/admin/products/:param` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::delete` | src/api/admin.js:77 |
| PATCH | `/admin/products/:param/stock` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::updateStock` | src/api/admin.js:81 |
| POST | `/admin/products/:param/accessories` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::linkAccessory` | src/api/admin.js:85 |
| DELETE | `/admin/products/:param/accessories/:param` | auth, role:admin,staff, role:admin | `Admin\ProductAdminController::unlinkAccessory` | src/api/admin.js:89 |
| POST | `/admin/products/:param/images` | auth, role:admin,staff, role:admin | `Admin\ProductImageAdminController::upload` | src/api/admin.js:93 |
| PUT | `/admin/products/:param/images/order` | auth, role:admin,staff, role:admin | `Admin\ProductImageAdminController::reorder` | src/api/admin.js:101 |
| PATCH | `/admin/products/:param/images/:param` | auth, role:admin,staff, role:admin | `Admin\ProductImageAdminController::update` | src/api/admin.js:97 |
| DELETE | `/admin/products/:param/images/:param` | auth, role:admin,staff, role:admin | `Admin\ProductImageAdminController::delete` | src/api/admin.js:105 |
| GET | `/admin/categories` | auth, role:admin,staff, role:admin | `Admin\CategoryAdminController::index` | src/api/admin.js:110 |
| POST | `/admin/categories` | auth, role:admin,staff, role:admin | `Admin\CategoryAdminController::create` | src/api/admin.js:114 |
| GET | `/admin/categories/:param` | auth, role:admin,staff, role:admin | `Admin\CategoryAdminController::show` | src/api/admin.js:118 |
| PATCH | `/admin/categories/:param` | auth, role:admin,staff, role:admin | `Admin\CategoryAdminController::update` | src/api/admin.js:122 |
| DELETE | `/admin/categories/:param` | auth, role:admin,staff, role:admin | `Admin\CategoryAdminController::delete` | src/api/admin.js:126 |
| GET | `/admin/brands` | auth, role:admin,staff, role:admin | `Admin\BrandAdminController::index` | src/api/admin.js:131 |
| POST | `/admin/brands` | auth, role:admin,staff, role:admin | `Admin\BrandAdminController::create` | src/api/admin.js:135 |
| GET | `/admin/brands/:param` | auth, role:admin,staff, role:admin | `Admin\BrandAdminController::show` | src/api/admin.js:139 |
| PATCH | `/admin/brands/:param` | auth, role:admin,staff, role:admin | `Admin\BrandAdminController::update` | src/api/admin.js:143 |
| DELETE | `/admin/brands/:param` | auth, role:admin,staff, role:admin | `Admin\BrandAdminController::delete` | src/api/admin.js:147 |
| GET | `/admin/settings` | auth, role:admin,staff, role:admin | `Admin\SettingAdminController::index` | src/api/admin.js:161, src/pages/admin/AdminPaymentAccountsPage.jsx:69, src/pages/admin/AdminRecentPurchasesPage.jsx:31 |
| PATCH | `/admin/settings` | auth, role:admin,staff, role:admin | `Admin\SettingAdminController::update` | src/api/admin.js:165, src/pages/admin/AdminPaymentAccountsPage.jsx:210 |
| POST | `/admin/push/broadcast` | auth, role:admin,staff, role:admin | `Admin\PushAdminController::broadcast` | src/api/push.js:49 |
| GET | `/admin/push/stats` | auth, role:admin,staff, role:admin | `Admin\PushAdminController::stats` | src/api/push.js:40 |
| GET | `/admin/push/log` | auth, role:admin,staff, role:admin | `Admin\PushAdminController::log` | src/api/push.js:45 |
| PUT | `/admin/push/settings` | auth, role:admin,staff, role:admin | `Admin\PushAdminController::updateSettings` | src/api/push.js:53 |
| GET | `/admin/push/fake-log` | auth, role:admin,staff, role:admin | `Admin\PushAdminController::fakeLog` | — |
| DELETE | `/admin/push/fake-log` | auth, role:admin,staff, role:admin | `Admin\PushAdminController::fakeLog` | — |
| GET | `/admin/rates` | auth, role:admin,staff, role:admin | `Admin\RateAdminController::index` | src/api/admin.js:153 |
| POST | `/admin/rates` | auth, role:admin,staff, role:admin | `Admin\RateAdminController::update` | src/api/admin.js:157 |
| GET | `/admin/shipping-zones` | auth, role:admin,staff, role:admin | `Admin\ShippingZoneAdminController::index` | src/api/admin.js:170 |
| POST | `/admin/shipping-zones` | auth, role:admin,staff, role:admin | `Admin\ShippingZoneAdminController::create` | src/api/admin.js:174 |
| PATCH | `/admin/shipping-zones/:param` | auth, role:admin,staff, role:admin | `Admin\ShippingZoneAdminController::update` | src/api/admin.js:178 |
| DELETE | `/admin/shipping-zones/:param` | auth, role:admin,staff, role:admin | `Admin\ShippingZoneAdminController::delete` | src/api/admin.js:182 |
| GET | `/admin/users` | auth, role:admin,staff, role:admin | `Admin\UserAdminController::index` | src/api/admin.js:194 |
| GET | `/admin/users/:param` | auth, role:admin,staff, role:admin | `Admin\UserAdminController::show` | src/api/admin.js:198 |
| PATCH | `/admin/users/:param/role` | auth, role:admin,staff, role:admin | `Admin\UserAdminController::updateRole` | src/api/admin.js:202 |
| PATCH | `/admin/users/:param/status` | auth, role:admin,staff, role:admin | `Admin\UserAdminController::updateStatus` | src/api/admin.js:206 |
| GET | `/admin/audit-logs` | auth, role:admin,staff, role:admin | `Admin\AuditAdminController::index` | src/api/admin.js:218 |

## 2. Database schema

Money is stored as integers (`*_aed`, `*_sdg`). Timestamps are written as UTC (`gmdate`). FKs are enforced on SQLite (`foreignKeys => true`) and MySQL.

### `announcements`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| kind | VARCHAR | NO | 'info' |
| title | VARCHAR | NO |  |
| body | TEXT | NO |  |
| link_label | VARCHAR | yes |  |
| link_url | VARCHAR | yes |  |
| placement | VARCHAR | NO | 'banner' |
| starts_at | DATETIME | yes |  |
| ends_at | DATETIME | yes |  |
| is_active | TINYINT | NO | 1 |
| is_dismissible | TINYINT | NO | 1 |
| sort_order | INT | NO | 0 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: —
Foreign keys: —

### `audit_logs`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| user_id | INT | yes |  |
| action | VARCHAR | NO |  |
| entity | VARCHAR | NO |  |
| entity_id | INT | yes |  |
| details | TEXT | yes |  |
| ip | VARCHAR | yes |  |
| created_at | DATETIME | yes |  |

Indexes: (created_at); (entity, entity_id); (action); (user_id)
Foreign keys: user_id → users.id (ON DELETE SET NULL)

### `brands`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| name | VARCHAR | NO |  |
| slug | VARCHAR | NO |  |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: (is_active); UNIQUE (slug)
Foreign keys: —

### `categories`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| name_ar | VARCHAR | NO |  |
| name_en | VARCHAR | yes |  |
| slug | VARCHAR | NO |  |
| sort_order | INT | NO | 0 |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: (is_active); (sort_order); UNIQUE (slug)
Foreign keys: —

### `email_outbox`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| user_id | INT | yes |  |
| to_email | VARCHAR | NO |  |
| subject | VARCHAR | NO |  |
| template | VARCHAR | NO |  |
| payload | TEXT | yes |  |
| status | VARCHAR | NO | 'pending' |
| attempts | INT | NO | 0 |
| last_error | TEXT | yes |  |
| available_at | DATETIME | yes |  |
| sent_at | DATETIME | yes |  |
| created_at | DATETIME | yes |  |

Indexes: (status, available_at)
Foreign keys: user_id → users.id (ON DELETE SET NULL)

### `exchange_rates`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| rate_sdg | INT | NO |  |
| changed_by | INT | yes |  |
| created_at | DATETIME | yes |  |

Indexes: (created_at); (changed_by)
Foreign keys: changed_by → users.id (ON DELETE SET NULL)

### `faq_items`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| question | TEXT | NO |  |
| answer | TEXT | NO |  |
| sort_order | INT | NO | 0 |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: —
Foreign keys: —

### `hero_slides`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| title | VARCHAR | NO |  |
| subtitle | VARCHAR | yes |  |
| image | VARCHAR | yes |  |
| button_label | VARCHAR | yes |  |
| link | VARCHAR | yes |  |
| sort_order | INT | NO | 0 |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: —
Foreign keys: —

### `home_sections`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| key | VARCHAR | NO |  |
| title | VARCHAR | NO |  |
| source | VARCHAR | NO | 'newest' |
| source_value | VARCHAR | yes |  |
| limit | INT | NO | 8 |
| sort_order | INT | NO | 0 |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: —
Foreign keys: —

### `notification_preferences`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| user_id | INT | NO |  |
| order_updates | TINYINT | NO | 1 |
| payment_updates | TINYINT | NO | 1 |
| price_alerts | TINYINT | NO | 1 |
| announcements | TINYINT | NO | 1 |
| new_orders | TINYINT | NO | 1 |
| receipts | TINYINT | NO | 1 |
| low_stock | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: UNIQUE (user_id)
Foreign keys: user_id → users.id (ON DELETE CASCADE)

### `notifications`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| user_id | INT | NO |  |
| type | VARCHAR | NO |  |
| title | VARCHAR | NO |  |
| body | TEXT | NO |  |
| order_id | INT | yes |  |
| read_at | DATETIME | yes |  |
| created_at | DATETIME | yes |  |
| data_json | TEXT | yes |  |
| channel_flags | VARCHAR | yes |  |

Indexes: (user_id, read_at)
Foreign keys: order_id → orders.id (ON DELETE SET NULL); user_id → users.id (ON DELETE CASCADE)

### `order_events`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| order_id | INT | NO |  |
| from_status | VARCHAR | yes |  |
| to_status | VARCHAR | NO |  |
| actor_user_id | INT | yes |  |
| note | TEXT | yes |  |
| created_at | DATETIME | yes |  |
| event_type | VARCHAR | yes | 'status_changed' |
| meta | TEXT | yes |  |

Indexes: (actor_user_id); (order_id)
Foreign keys: actor_user_id → users.id (ON DELETE SET NULL); order_id → orders.id (ON DELETE CASCADE)

### `order_items`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| order_id | INT | NO |  |
| product_id | INT | NO |  |
| product_name_snapshot | VARCHAR | NO |  |
| product_type_snapshot | VARCHAR | NO |  |
| unit_price_aed | INT | NO |  |
| quantity | INT | NO | 1 |
| line_total_aed | INT | NO |  |
| unit_price_sdg | INT | yes | 0 |
| line_total_sdg | INT | yes | 0 |

Indexes: (product_id); (order_id)
Foreign keys: product_id → products.id (ON DELETE RESTRICT); order_id → orders.id (ON DELETE CASCADE)

### `orders`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| order_number | VARCHAR | NO |  |
| user_id | INT | NO |  |
| status | VARCHAR | NO |  |
| delivery_type | VARCHAR | NO |  |
| shipping_zone_id | INT | yes |  |
| delivery_address | TEXT | yes |  |
| delivery_phone | VARCHAR | NO |  |
| customer_note | TEXT | yes |  |
| rate_sdg_snapshot | INT | NO |  |
| app_discount_percent_snapshot | INT | NO | 0 |
| subtotal_aed | INT | NO |  |
| subtotal_sdg | INT | NO |  |
| discount_sdg | INT | NO | 0 |
| delivery_fee_sdg | INT | NO | 0 |
| total_sdg | INT | NO |  |
| lock_expires_at | DATETIME | NO |  |
| courier_name | VARCHAR | yes |  |
| courier_phone | VARCHAR | yes |  |
| cancelled_reason | VARCHAR | yes |  |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |
| ticker_hidden | TINYINT | yes | 0 |
| idempotency_key | VARCHAR | yes |  |

Indexes: UNIQUE (user_id, idempotency_key); (lock_expires_at); (status); (user_id); UNIQUE (order_number)
Foreign keys: shipping_zone_id → shipping_zones.id (ON DELETE SET NULL); user_id → users.id (ON DELETE RESTRICT)

### `otp_codes`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| email | VARCHAR | NO |  |
| code_hash | VARCHAR | NO |  |
| attempts | INT | NO | 0 |
| expires_at | DATETIME | NO |  |
| consumed_at | DATETIME | yes |  |
| ip | VARCHAR | yes |  |
| created_at | DATETIME | yes |  |

Indexes: (created_at); (email)
Foreign keys: —

### `pages`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| slug | VARCHAR | NO |  |
| title | VARCHAR | NO |  |
| body | TEXT | NO |  |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: UNIQUE (slug)
Foreign keys: —

### `payment_accounts`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| bank_name | VARCHAR | NO |  |
| account_name | VARCHAR | NO |  |
| account_number | VARCHAR | NO |  |
| branch | VARCHAR | yes |  |
| notes | TEXT | yes |  |
| is_active | TINYINT | NO | 1 |
| sort_order | INT | NO | 0 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: —
Foreign keys: —

### `payment_proofs`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| order_id | INT | NO |  |
| file_path | VARCHAR | NO |  |
| sender_name | VARCHAR | yes |  |
| reference_number | VARCHAR | yes |  |
| status | VARCHAR | NO | 'pending' |
| review_note | TEXT | yes |  |
| reviewed_by | INT | yes |  |
| reviewed_at | DATETIME | yes |  |
| created_at | DATETIME | yes |  |

Indexes: (status); (order_id)
Foreign keys: reviewed_by → users.id (ON DELETE SET NULL); order_id → orders.id (ON DELETE CASCADE)

### `product_accessories`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| product_id | INT | NO |  |
| accessory_id | INT | NO |  |
| sort_order | INT | NO | 0 |

Indexes: UNIQUE (product_id, accessory_id)
Foreign keys: accessory_id → products.id (ON DELETE CASCADE); product_id → products.id (ON DELETE CASCADE)

### `product_images`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| product_id | INT | NO |  |
| path | VARCHAR | NO |  |
| thumb_path | VARCHAR | yes |  |
| sort_order | INT | NO | 0 |
| is_primary | TINYINT | NO | 0 |
| created_at | DATETIME | yes |  |
| alt | VARCHAR | yes |  |

Indexes: (sort_order); (product_id)
Foreign keys: product_id → products.id (ON DELETE CASCADE)

### `products`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| type | VARCHAR | NO |  |
| category_id | INT | NO |  |
| brand_id | INT | yes |  |
| name | VARCHAR | NO |  |
| slug | VARCHAR | NO |  |
| short_description | VARCHAR | yes |  |
| description | TEXT | yes |  |
| condition | VARCHAR | NO |  |
| price_aed | INT | NO |  |
| specs | TEXT | yes |  |
| warranty_text | VARCHAR | yes |  |
| stock_quantity | INT | NO | 0 |
| is_active | TINYINT | NO | 1 |
| is_featured | TINYINT | NO | 0 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |
| deleted_at | DATETIME | yes |  |

Indexes: (price_aed); (is_featured); (is_active); (brand_id); (category_id); (type); UNIQUE (slug)
Foreign keys: brand_id → brands.id (ON DELETE SET NULL); category_id → categories.id (ON DELETE RESTRICT)

### `push_outbox`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| notification_id | INT | yes |  |
| subscription_id | INT | NO |  |
| status | VARCHAR | NO | 'pending' |
| attempts | INT | NO | 0 |
| next_attempt_at | DATETIME | yes |  |
| error | TEXT | yes |  |
| payload_json | TEXT | yes |  |
| created_at | DATETIME | yes |  |
| sent_at | DATETIME | yes |  |

Indexes: (status, next_attempt_at)
Foreign keys: subscription_id → push_subscriptions.id (ON DELETE CASCADE); notification_id → notifications.id (ON DELETE CASCADE)

### `push_subscriptions`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| user_id | INT | yes |  |
| platform | VARCHAR | NO | 'web' |
| provider | VARCHAR | NO | 'webpush' |
| endpoint_or_token | TEXT | NO |  |
| endpoint_hash | VARCHAR | NO |  |
| keys_json | TEXT | yes |  |
| device_label | VARCHAR | yes |  |
| user_agent | VARCHAR | yes |  |
| locale | VARCHAR | NO | 'ar' |
| app_version | VARCHAR | yes |  |
| topics_json | TEXT | yes |  |
| is_active | TINYINT | NO | 1 |
| last_success_at | DATETIME | yes |  |
| last_failure_at | DATETIME | yes |  |
| failure_count | INT | NO | 0 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: (user_id, is_active); UNIQUE (endpoint_hash)
Foreign keys: user_id → users.id (ON DELETE CASCADE)

### `refresh_tokens`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| user_id | INT | NO |  |
| family_id | VARCHAR | NO |  |
| token_hash | VARCHAR | NO |  |
| user_agent | VARCHAR | yes |  |
| ip | VARCHAR | yes |  |
| expires_at | DATETIME | NO |  |
| revoked_at | DATETIME | yes |  |
| replaced_by_id | INT | yes |  |
| created_at | DATETIME | yes |  |
| revoke_reason | VARCHAR | yes |  |
| family_created_at | DATETIME | yes |  |

Indexes: (family_id); (user_id); UNIQUE (token_hash)
Foreign keys: user_id → users.id (ON DELETE CASCADE)

### `settings`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| key | VARCHAR | NO |  |
| value | TEXT | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: UNIQUE (key)
Foreign keys: —

### `shipping_zones`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| state_name_ar | VARCHAR | NO |  |
| fee_sdg | INT | NO | 0 |
| is_active | TINYINT | NO | 1 |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |

Indexes: (is_active); UNIQUE (state_name_ar)
Foreign keys: —

### `users`

| column | type | null | default |
|---|---|---|---|
| id (PK) | INTEGER | yes |  |
| name | VARCHAR | NO |  |
| email | VARCHAR | NO |  |
| phone | VARCHAR | yes |  |
| google_id | VARCHAR | yes |  |
| avatar_url | VARCHAR | yes |  |
| role | VARCHAR | NO | 'customer' |
| email_verified_at | DATETIME | yes |  |
| email_notifications | TINYINT | NO | 1 |
| is_active | TINYINT | NO | 1 |
| last_login_at | DATETIME | yes |  |
| created_at | DATETIME | yes |  |
| updated_at | DATETIME | yes |  |
| deleted_at | DATETIME | yes |  |

Indexes: (role); UNIQUE (google_id); UNIQUE (email)
Foreign keys: —


## 3. Models (`$allowedFields`)

- **AnnouncementModel** (`announcements`): kind, title, body, link_label, link_url, placement, starts_at, ends_at, is_active, is_dismissible, sort_order, created_at, updated_at
- **AuditLogModel** (`audit_logs`): user_id, action, entity, entity_id, details, ip, created_at
- **BrandModel** (`brands`): name, slug, is_active
- **CategoryModel** (`categories`): name_ar, name_en, slug, sort_order, is_active
- **EmailOutboxModel** (`email_outbox`): user_id, to_email, subject, template, payload, status, attempts, last_error, available_at, sent_at, created_at
- **ExchangeRateModel** (`exchange_rates`): rate_sdg, changed_by, created_at
- **FaqItemModel** (`faq_items`): question, answer, sort_order, is_active, created_at, updated_at
- **HeroSlideModel** (`hero_slides`): title, subtitle, image, button_label, link, sort_order, is_active, created_at, updated_at
- **HomeSectionModel** (`home_sections`): key, title, source, source_value, limit, sort_order, is_active, created_at, updated_at
- **NotificationModel** (`notifications`): user_id, type, title, body, order_id, data_json, channel_flags, read_at, created_at
- **NotificationPreferenceModel** (`notification_preferences`): user_id, order_updates, payment_updates, price_alerts, announcements, new_orders, receipts, low_stock, created_at, updated_at
- **OrderEventModel** (`order_events`): order_id, from_status, to_status, actor_user_id, event_type, meta, note, created_at
- **OrderItemModel** (`order_items`): order_id, product_id, product_name_snapshot, product_type_snapshot, unit_price_aed, quantity, line_total_aed, unit_price_sdg, line_total_sdg
- **OrderModel** (`orders`): order_number, user_id, status, delivery_type, shipping_zone_id, delivery_address, delivery_phone, customer_note, rate_sdg_snapshot, app_discount_percent_snapshot, subtotal_aed, subtotal_sdg, discount_sdg, delivery_fee_sdg, total_sdg, lock_expires_at, courier_name, courier_phone, cancelled_reason, ticker_hidden, idempotency_key
- **OtpCodeModel** (`otp_codes`): email, code_hash, attempts, expires_at, consumed_at, ip, created_at
- **PageModel** (`pages`): slug, title, body, is_active, created_at, updated_at
- **PaymentAccountModel** (`payment_accounts`): bank_name, account_name, account_number, branch, notes, is_active, sort_order, created_at, updated_at
- **PaymentProofModel** (`payment_proofs`): order_id, file_path, sender_name, reference_number, status, review_note, reviewed_by, reviewed_at, created_at
- **ProductAccessoryModel** (`product_accessories`): product_id, accessory_id, sort_order
- **ProductImageModel** (`product_images`): product_id, path, thumb_path, sort_order, is_primary, alt, created_at
- **ProductModel** (`products`, soft deletes): type, category_id, brand_id, name, slug, short_description, description, condition, price_aed, specs, warranty_text, stock_quantity, is_active, is_featured
- **PushOutboxModel** (`push_outbox`): notification_id, subscription_id, status, attempts, next_attempt_at, error, payload_json, created_at, sent_at
- **PushSubscriptionModel** (`push_subscriptions`): user_id, platform, provider, endpoint_or_token, endpoint_hash, keys_json, device_label, user_agent, locale, app_version, topics_json, is_active, last_success_at, last_failure_at, failure_count, created_at, updated_at
- **RefreshTokenModel** (`refresh_tokens`): user_id, family_id, token_hash, user_agent, ip, expires_at, revoked_at, replaced_by_id, created_at, revoke_reason, family_created_at
- **SettingModel** (`settings`): key, value
- **ShippingZoneModel** (`shipping_zones`): state_name_ar, fee_sdg, is_active
- **UserModel** (`users`, soft deletes): name, email, phone, google_id, avatar_url, role, email_verified_at, email_notifications, is_active, last_login_at

## 4. Frontend API calls (137)

| file:line | method | path | request body keys |
|---|---|---|---|
| src/api/admin.js:6 | GET | `/admin/dashboard` | — |
| src/api/admin.js:18 | GET | `/admin/orders` | — |
| src/api/admin.js:22 | GET | `/admin/orders/:param` | — |
| src/api/admin.js:30 | PATCH | `/admin/orders/:param/status` | status, note, courier_name, courier_phone |
| src/api/admin.js:34 | POST | `/admin/orders/:param/lock` | hours |
| src/api/admin.js:38 | POST | `/admin/orders/:param/reset-orphan` | — |
| src/api/admin.js:42 | POST | `/admin/orders/:param/proofs/:param/review` | action, note |
| src/api/admin.js:49 | GET | `/orders/:param/payment-proof/:param/image` | — |
| src/api/admin.js:61 | GET | `/admin/products` | — |
| src/api/admin.js:65 | POST | `/admin/products` | — |
| src/api/admin.js:69 | GET | `/admin/products/:param` | — |
| src/api/admin.js:73 | PATCH | `/admin/products/:param` | — |
| src/api/admin.js:77 | DELETE | `/admin/products/:param` | — |
| src/api/admin.js:81 | PATCH | `/admin/products/:param/stock` | stock_quantity |
| src/api/admin.js:85 | POST | `/admin/products/:param/accessories` | accessory_id |
| src/api/admin.js:89 | DELETE | `/admin/products/:param/accessories/:param` | — |
| src/api/admin.js:93 | POST | `/admin/products/:param/images` | — |
| src/api/admin.js:97 | PATCH | `/admin/products/:param/images/:param` | — |
| src/api/admin.js:101 | PUT | `/admin/products/:param/images/order` | order |
| src/api/admin.js:105 | DELETE | `/admin/products/:param/images/:param` | — |
| src/api/admin.js:110 | GET | `/admin/categories` | — |
| src/api/admin.js:114 | POST | `/admin/categories` | — |
| src/api/admin.js:118 | GET | `/admin/categories/:param` | — |
| src/api/admin.js:122 | PATCH | `/admin/categories/:param` | — |
| src/api/admin.js:126 | DELETE | `/admin/categories/:param` | — |
| src/api/admin.js:131 | GET | `/admin/brands` | — |
| src/api/admin.js:135 | POST | `/admin/brands` | — |
| src/api/admin.js:139 | GET | `/admin/brands/:param` | — |
| src/api/admin.js:143 | PATCH | `/admin/brands/:param` | — |
| src/api/admin.js:147 | DELETE | `/admin/brands/:param` | — |
| src/api/admin.js:153 | GET | `/admin/rates` | — |
| src/api/admin.js:157 | POST | `/admin/rates` | rate_sdg |
| src/api/admin.js:161 | GET | `/admin/settings` | — |
| src/api/admin.js:165 | PATCH | `/admin/settings` | — |
| src/api/admin.js:170 | GET | `/admin/shipping-zones` | — |
| src/api/admin.js:174 | POST | `/admin/shipping-zones` | — |
| src/api/admin.js:178 | PATCH | `/admin/shipping-zones/:param` | — |
| src/api/admin.js:182 | DELETE | `/admin/shipping-zones/:param` | — |
| src/api/admin.js:194 | GET | `/admin/users` | — |
| src/api/admin.js:198 | GET | `/admin/users/:param` | — |
| src/api/admin.js:202 | PATCH | `/admin/users/:param/role` | role |
| src/api/admin.js:206 | PATCH | `/admin/users/:param/status` | is_active |
| src/api/admin.js:218 | GET | `/admin/audit-logs` | — |
| src/api/auth.js:8 | POST | `/auth/email/request` | email |
| src/api/auth.js:19 | POST | `/auth/email/verify` | email, code, name |
| src/api/auth.js:26 | POST | `/auth/google` | id_token |
| src/api/auth.js:45 | POST | `/auth/logout` | — |
| src/api/auth.js:52 | POST | `/auth/logout-all` | — |
| src/api/auth.js:59 | GET | `/me` | — |
| src/api/auth.js:69 | PATCH | `/me` | name, phone |
| src/api/auth.js:76 | PATCH | `/me/preferences` | email_notifications |
| src/api/auth.js:85 | POST | `/me/link-google` | id_token |
| src/api/auth.js:92 | POST | `/me/unlink-google` | — |
| src/api/auth.js:99 | DELETE | `/me` | — |
| src/api/notifications.js:15 | GET | `/notifications` | — |
| src/api/notifications.js:22 | GET | `/notifications/unread-count` | — |
| src/api/notifications.js:29 | PATCH | `/notifications/:param/read` | — |
| src/api/notifications.js:36 | POST | `/notifications/mark-all-read` | — |
| src/api/orders.js:15 | GET | `/orders` | — |
| src/api/orders.js:22 | GET | `/orders/:param` | — |
| src/api/orders.js:29 | POST | `/orders` | — |
| src/api/orders.js:36 | POST | `/orders/:param/cancel` | — |
| src/api/orders.js:43 | POST | `/orders/:param/reorder` | — |
| src/api/orders.js:50 | POST | `/orders/:param/payment-proof` | — |
| src/api/orders.js:57 | GET | `/orders/:param/payment-proof/:param/image` | — |
| src/api/products.js:15 | GET | `/products` | — |
| src/api/products.js:22 | GET | `/products/:param` | — |
| src/api/products.js:29 | GET | `/categories` | — |
| src/api/products.js:36 | GET | `/brands` | — |
| src/api/products.js:43 | GET | `/shop` | — |
| src/api/products.js:50 | GET | `/shipping-zones` | — |
| src/api/products.js:57 | GET | `/content/hero-slides` | — |
| src/api/products.js:64 | GET | `/content/home-sections` | — |
| src/api/products.js:71 | GET | `/content/faq` | — |
| src/api/products.js:78 | GET | `/content/pages` | — |
| src/api/products.js:85 | GET | `/content/pages/:param` | — |
| src/api/products.js:92 | GET | `/announcements` | — |
| src/api/products.js:99 | GET | `/recent-purchases` | — |
| src/api/push.js:5 | GET | `/push/config` | — |
| src/api/push.js:9 | GET | `/push/subscriptions` | — |
| src/api/push.js:13 | POST | `/push/subscriptions` | — |
| src/api/push.js:17 | DELETE | `/push/subscriptions` | — |
| src/api/push.js:23 | DELETE | `/push/subscriptions/:param` | — |
| src/api/push.js:27 | GET | `/push/preferences` | — |
| src/api/push.js:31 | PUT | `/push/preferences` | — |
| src/api/push.js:35 | POST | `/push/test` | — |
| src/api/push.js:40 | GET | `/admin/push/stats` | — |
| src/api/push.js:45 | GET | `/admin/push/log` | — |
| src/api/push.js:49 | POST | `/admin/push/broadcast` | — |
| src/api/push.js:53 | PUT | `/admin/push/settings` | — |
| src/api/shop.js:8 | GET | `/shop` | — |
| src/components/store/AnnouncementBanner.jsx:43 | GET | `/announcements` | — |
| src/components/store/AnnouncementToast.jsx:81 | GET | `/announcements` | — |
| src/components/store/RecentPurchasesToast.jsx:90 | GET | `/recent-purchases` | — |
| src/layouts/StoreLayout.jsx:191 | GET | `/announcements` | — |
| src/pages/admin/AdminAnnouncementsPage.jsx:49 | GET | `/admin/announcements` | — |
| src/pages/admin/AdminAnnouncementsPage.jsx:107 | PATCH | `/admin/announcements/:param` | — |
| src/pages/admin/AdminAnnouncementsPage.jsx:110 | POST | `/admin/announcements` | — |
| src/pages/admin/AdminAnnouncementsPage.jsx:122 | PATCH | `/admin/announcements/:param/toggle` | — |
| src/pages/admin/AdminAnnouncementsPage.jsx:138 | DELETE | `/admin/announcements/:param` | — |
| src/pages/admin/AdminDashboardPage.jsx:91 | GET | `/admin/payment-accounts` | — |
| src/pages/admin/AdminFaqPage.jsx:41 | GET | `/admin/faq` | — |
| src/pages/admin/AdminFaqPage.jsx:82 | PATCH | `/admin/faq/:param` | — |
| src/pages/admin/AdminFaqPage.jsx:85 | POST | `/admin/faq` | — |
| src/pages/admin/AdminFaqPage.jsx:97 | PATCH | `/admin/faq/:param/toggle` | — |
| src/pages/admin/AdminFaqPage.jsx:117 | POST | `/admin/faq/reorder` | ids |
| src/pages/admin/AdminFaqPage.jsx:133 | DELETE | `/admin/faq/:param` | — |
| src/pages/admin/AdminHeroSlidesPage.jsx:49 | GET | `/admin/hero-slides` | — |
| src/pages/admin/AdminHeroSlidesPage.jsx:96 | PATCH | `/admin/hero-slides/:param` | — |
| src/pages/admin/AdminHeroSlidesPage.jsx:99 | POST | `/admin/hero-slides` | — |
| src/pages/admin/AdminHeroSlidesPage.jsx:111 | PATCH | `/admin/hero-slides/:param/toggle` | — |
| src/pages/admin/AdminHeroSlidesPage.jsx:131 | POST | `/admin/hero-slides/reorder` | ids |
| src/pages/admin/AdminHeroSlidesPage.jsx:147 | DELETE | `/admin/hero-slides/:param` | — |
| src/pages/admin/AdminHomeSectionsPage.jsx:43 | GET | `/admin/home-sections` | — |
| src/pages/admin/AdminHomeSectionsPage.jsx:90 | PATCH | `/admin/home-sections/:param` | — |
| src/pages/admin/AdminHomeSectionsPage.jsx:93 | POST | `/admin/home-sections` | — |
| src/pages/admin/AdminHomeSectionsPage.jsx:105 | PATCH | `/admin/home-sections/:param/toggle` | — |
| src/pages/admin/AdminHomeSectionsPage.jsx:125 | POST | `/admin/home-sections/reorder` | ids |
| src/pages/admin/AdminHomeSectionsPage.jsx:141 | DELETE | `/admin/home-sections/:param` | — |
| src/pages/admin/AdminPagesPage.jsx:41 | GET | `/admin/pages` | — |
| src/pages/admin/AdminPagesPage.jsx:84 | PATCH | `/admin/pages/:param` | — |
| src/pages/admin/AdminPagesPage.jsx:87 | POST | `/admin/pages` | — |
| src/pages/admin/AdminPagesPage.jsx:99 | PATCH | `/admin/pages/:param/toggle` | — |
| src/pages/admin/AdminPagesPage.jsx:115 | DELETE | `/admin/pages/:param` | — |
| src/pages/admin/AdminPaymentAccountsPage.jsx:68 | GET | `/admin/payment-accounts` | — |
| src/pages/admin/AdminPaymentAccountsPage.jsx:69 | GET | `/admin/settings` | — |
| src/pages/admin/AdminPaymentAccountsPage.jsx:138 | POST | `/admin/payment-accounts` | — |
| src/pages/admin/AdminPaymentAccountsPage.jsx:141 | PATCH | `/admin/payment-accounts/:param` | — |
| src/pages/admin/AdminPaymentAccountsPage.jsx:158 | PATCH | `/admin/payment-accounts/:param/toggle` | confirm |
| src/pages/admin/AdminPaymentAccountsPage.jsx:175 | DELETE | `/admin/payment-accounts/:param` | — |
| src/pages/admin/AdminPaymentAccountsPage.jsx:198 | POST | `/admin/payment-accounts/reorder` | ids, confirm |
| src/pages/admin/AdminPaymentAccountsPage.jsx:210 | PATCH | `/admin/settings` | payment_general_notes |
| src/pages/admin/AdminRecentPurchasesPage.jsx:31 | GET | `/admin/settings` | — |
| src/pages/admin/AdminRecentPurchasesPage.jsx:32 | GET | `/admin/recent-purchases` | — |
| src/pages/admin/AdminRecentPurchasesPage.jsx:53 | PATCH | `/admin/recent-purchases/settings` | ticker_enabled |
| src/pages/admin/AdminRecentPurchasesPage.jsx:66 | PATCH | `/admin/recent-purchases/:param/toggle-hide` | — |
| src/pages/store/CheckoutPage.jsx:241 | POST | `/orders` | order_type, contact_phone, customer_notes, items, shipping_zone_id, delivery_address |

## 5. Settings keys

Admin-writable via `PATCH /admin/settings` (whitelist `SettingService::ALLOWED_SETTING_KEYS`): `shop_name`, `shop_address`, `shop_phone`, `shop_whatsapp`, `working_hours`, `bank_name`, `bank_account_name`, `bank_account_number`, `lock_hours`, `app_discount_percent`, `rounding_step_sdg`, `low_stock_threshold`, `payment_general_notes`, `orders_enabled`, `orders_disabled_message`, `ticker_enabled`, `shop_map_link`, `social_facebook`, `social_instagram`, `footer_text`, `delivery_notes`, `policy_inspection`, `policy_inspection_title`, `policy_pickup`, `policy_return`, `inspection_note`, `returns_note`, `extra_delivery_note`, `push_enabled`.

Read in code:

- `bank_account_name` — app/Services/OrderService.php
- `bank_account_number` — app/Services/OrderService.php
- `bank_name` — app/Services/OrderService.php
- `lock_hours` — app/Controllers/Api/V1/Admin/DashboardController.php, app/Services/OrderService.php
- `low_stock_threshold` — app/Controllers/Api/V1/Admin/DashboardController.php, app/Services/OrderService.php
- `orders_disabled_message` — app/Services/OrderService.php
- `orders_enabled` — app/Services/OrderService.php
- `payment_general_notes` — app/Services/OrderService.php
- `push_enabled` — app/Services/PushService.php
- `shop_phone` — app/Services/OrderService.php
- `ticker_enabled` — app/Services/RecentPurchasesService.php

Public exposure: `GET /shop` (SettingsController::shop) maps an explicit subset; exchange rate, bank details and rounding step are not exposed to customers.

## 6. State machines

### Order status (single source: `app/Services/OrderStateMachine.php`)

- `pending_payment` → `payment_review`, `cancelled`, `expired`
- `payment_review` → `confirmed`, `pending_payment`, `expired`, `cancelled`
- `confirmed` → `ready_for_pickup`, `out_for_delivery`, `completed`, `cancelled`
- `ready_for_pickup` → `completed`, `cancelled`
- `out_for_delivery` → `completed`, `cancelled`
- `completed` → (terminal)
- `cancelled` → (terminal)
- `expired` → (terminal)

Rules enforced server-side (after this review):
- Every status write is compare-and-set on the status that was validated (`OrderService::casStatus`); a concurrent change returns 409.
- `ready_for_pickup` only for pickup orders, `out_for_delivery` only for delivery orders.
- Stock is reserved at creation (conditional `UPDATE ... WHERE stock_quantity >= ?`) and restored exactly once on cancel/expire from any non-terminal state.
- `pending_payment` → `expired` happens lazily on read and via `php spark orders:expire` (same `refreshExpiry` path, transactional).
- Customers may cancel only in `pending_payment`; reorder creates a fresh order at current prices.
- Order creation accepts `Idempotency-Key` (unique per user).

### Payment proof
`pending` → `approved` | `rejected` (admin review, only while the order is `payment_review`), `pending` → `replaced` (customer re-upload during review). Approve: order `payment_review` → `confirmed`. Reject: back to `pending_payment` if the lock is still valid, otherwise `expired` with stock restored.

### Price lock
`lock_expires_at = created_at + lock_hours` (setting, default 4h, server clock, UTC). Admin may extend 1–72h while `pending_payment`/`payment_review`. The web countdown uses the server clock offset taken from `Cache-Control: no-store` responses only (cached SW responses are ignored); the API exposes `Date` via CORS.

### Web mirror
`wex-web/src/utils/orderState.js` derives UI state only (labels, next action, countdown); it is display logic and never authoritative.

## 7. Environment variables

### wex-api (`env()` reads)

- `ADMIN_EMAIL` — app/Database/Seeds/AdminSeeder.php
- `APP_ENV` — app/Controllers/Api/V1/Admin/PushAdminController.php, app/Services/Push/FakeProvider.php
- `APP_URL` — app/Services/MailService.php, app/Services/TokenService.php
- `CI_ENVIRONMENT` — app/Commands/IntegrityRepair.php, app/Controllers/Api/V1/AuthController.php, app/Database/Seeds/DatabaseSeeder.php, app/Database/Seeds/DevSeeder.php, app/Services/IntegrityService.php, app/Services/Push/FakeProvider.php
- `COOKIE_SAMESITE` — app/Controllers/Api/V1/AuthController.php
- `COOKIE_SECURE` — app/Controllers/Api/V1/AuthController.php
- `CORS_ALLOWED_ORIGINS` — app/Filters/CorsFilter.php
- `FCM_ENABLED` — app/Services/Push/FcmProvider.php
- `FCM_PROJECT_ID` — app/Services/Push/FcmProvider.php
- `FCM_SERVICE_ACCOUNT_PATH` — app/Services/Push/FcmProvider.php
- `GOOGLE_CLIENT_IDS` — app/Services/GoogleAuthService.php
- `JWT_ACCESS_TTL_MINUTES` — app/Services/TokenService.php
- `JWT_REFRESH_MAX_LIFETIME_DAYS` — app/Services/TokenService.php
- `JWT_REFRESH_TTL_DAYS` — app/Services/TokenService.php
- `JWT_SECRET` — app/Services/OtpService.php, app/Services/TokenService.php
- `MAIL_DRIVER` — app/Services/MailService.php
- `MAIL_FROM_EMAIL` — app/Services/MailService.php
- `MAIL_FROM_NAME` — app/Services/MailService.php
- `PUSH_ENDPOINT_EXTRA_HOSTS` — app/Libraries/PushEndpointPolicy.php
- `PUSH_FAKE_LOG` — app/Services/Push/FakeProvider.php
- `PUSH_PROVIDER` — app/Services/PushService.php
- `REFRESH_REUSE_GRACE_SECONDS` — app/Services/TokenService.php
- `SHOP_NOTIFY_EMAIL` — app/Services/PaymentAccountService.php
- `TESTING_LOGIN_ENABLED` — app/Controllers/Api/V1/AuthController.php
- `UPLOAD_MAX_MB` — app/Services/ImageService.php
- `VAPID_PRIVATE_KEY` — app/Services/Push/WebPushProvider.php
- `VAPID_PUBLIC_KEY` — app/Services/Push/WebPushProvider.php, app/Services/PushService.php
- `VAPID_SUBJECT` — app/Services/Push/WebPushProvider.php
- `encryption.key` — app/Services/OtpService.php

Also: `CI_ENVIRONMENT` (must be `production` in prod), `TESTING_LOGIN_ENABLED` (CI only), `database.*`, `app.baseURL`.

### wex-web (build-time, public)

- `VITE_API_BASE_URL`
- `VITE_AUTH_STORAGE_FALLBACK`
- `VITE_DEV_SW`
- `VITE_GOOGLE_CLIENT_ID`

## 8. Contract check

`wex-web/scripts/contract-check.js` (in `npm run check:all`, CI): 142 routes, 137 calls, **0 errors**. Checks route existence, HTTP method and request-body keys. Response fields are **not** statically checked (UNVERIFIED; covered only by e2e).

API routes without a frontend caller (expected: health, refresh via fetch, content aliases, show-by-id endpoints, fake-log debug):

- GET /ping -> PingController::index (no frontend caller)
- POST /auth/testing-login -> AuthController::testingLogin (no frontend caller)
- POST /auth/refresh -> AuthController::refresh (no frontend caller)
- GET /hero-slides -> ContentController::heroSlides (no frontend caller)
- GET /home-sections -> ContentController::homeSections (no frontend caller)
- GET /faq -> ContentController::faq (no frontend caller)
- GET /pages -> ContentController::pages (no frontend caller)
- GET /pages/:param -> ContentController::page (no frontend caller)
- GET /admin/payment-accounts/:param -> Admin\PaymentAccountAdminController::show (no frontend caller)
- GET /admin/announcements/:param -> Admin\AnnouncementAdminController::show (no frontend caller)
- GET /admin/hero-slides/:param -> Admin\HeroSlideAdminController::show (no frontend caller)
- GET /admin/home-sections/:param -> Admin\HomeSectionAdminController::show (no frontend caller)
- GET /admin/faq/:param -> Admin\FaqAdminController::show (no frontend caller)
- GET /admin/pages/:param -> Admin\PageAdminController::show (no frontend caller)
- GET /admin/push/fake-log -> Admin\PushAdminController::fakeLog (no frontend caller)
- DELETE /admin/push/fake-log -> Admin\PushAdminController::fakeLog (no frontend caller)
