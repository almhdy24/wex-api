# Review Coverage

_Updated 2026-11-25. Honest accounting: "yes" = every line read; "partial" = the security/logic-relevant functions named were read; "no" = not read by a human pass in this session, covered only by automated gates._

**The goal was 100% line-by-line coverage. It was NOT reached in this pass.** Review was prioritised by risk (auth, orders/stock/payments, uploads, push, admin writes, API client, routing/redirects, hook correctness). The "no" rows are the remaining work.

| repo | files | full read | partial | not read | coverage (full+partial) |
|---|---|---|---|---|---|
| wex-api | 296 | 58 | 32 | 206 | 30% |
| wex-web | 432 | 29 | 19 | 384 | 11% |

Automated coverage that applies to every file: wex-api — `php -l` on all PHP + full PHPUnit (CI `phpunit.yml`); wex-web — ESLint over `src` with `react-hooks/rules-of-hooks` + `exhaustive-deps` as errors (0 errors), `contract-check` (all 137 API calls vs 142 routes), full vitest, static style/i18n/claims checks (CI `ui-check.yml`).

Findings per file are listed in `docs/CODE_REVIEW.md` (findings table, file:line).

## wex-api

| file | reviewed | scope |
|---|---|---|
| `.github/workflows/phpunit.yml` | yes | full read |
| `app/.htaccess` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/AdminCreate.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/IntegrityCheck.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/IntegrityRepair.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/MailSend.php` | yes | full read |
| `app/Commands/OrdersExpire.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/PushFcmTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/PushKeys.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/PushReminders.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Commands/PushWork.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Common.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/App.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Autoload.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Boot/development.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Boot/production.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Boot/testing.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/CURLRequest.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Cache.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Constants.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/ContentSecurityPolicy.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Cookie.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Cors.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Database.php` | partial | foreignKeys |
| `app/Config/DocTypes.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Email.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Encryption.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Events.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Exceptions.php` | partial | handler |
| `app/Config/Feature.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Filters.php` | yes | full read |
| `app/Config/ForeignCharacters.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Format.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Generators.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Honeypot.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Hostnames.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Images.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Kint.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Logger.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Migrations.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Mimes.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Modules.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Optimize.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Pager.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Paths.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Publisher.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/PushTemplates.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Routes.php` | yes | full read |
| `app/Config/Routing.php` | yes | full read |
| `app/Config/Security.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Services.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Session.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Toolbar.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/UserAgents.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Validation.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/View.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/Wex.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Config/WorkerMode.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/AnnouncementAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/AuditAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/BrandAdminController.php` | partial | create/update/delete |
| `app/Controllers/Api/V1/Admin/CategoryAdminController.php` | yes | full read |
| `app/Controllers/Api/V1/Admin/DashboardController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/FaqAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/HeroSlideAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/HomeSectionAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/OrderAdminController.php` | yes | full read |
| `app/Controllers/Api/V1/Admin/PageAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/PaymentAccountAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/PaymentAdminController.php` | yes | full read |
| `app/Controllers/Api/V1/Admin/ProductAdminController.php` | yes | full read |
| `app/Controllers/Api/V1/Admin/ProductImageAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/PushAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/RateAdminController.php` | partial | update |
| `app/Controllers/Api/V1/Admin/RecentPurchasesAdminController.php` | partial | settings |
| `app/Controllers/Api/V1/Admin/SettingAdminController.php` | partial | update |
| `app/Controllers/Api/V1/Admin/ShippingZoneAdminController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/Admin/UserAdminController.php` | partial | show/role/status |
| `app/Controllers/Api/V1/AnnouncementController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/AuthController.php` | yes | full read |
| `app/Controllers/Api/V1/BaseApiController.php` | yes | full read |
| `app/Controllers/Api/V1/BrandController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/CategoryController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/ContentController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/ErrorController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/MeController.php` | yes | full read |
| `app/Controllers/Api/V1/NotificationController.php` | yes | full read |
| `app/Controllers/Api/V1/OrderController.php` | yes | full read |
| `app/Controllers/Api/V1/PaymentProofController.php` | yes | full read |
| `app/Controllers/Api/V1/PingController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/ProductController.php` | partial | index/show |
| `app/Controllers/Api/V1/PushController.php` | yes | full read |
| `app/Controllers/Api/V1/RecentPurchasesController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Api/V1/SettingsController.php` | partial | shop |
| `app/Controllers/Api/V1/ShippingZoneController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/BaseController.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Controllers/Home.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000001_CreateUsersTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000002_CreateRefreshTokensTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000003_CreateOtpCodesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000004_CreateCategoriesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000005_CreateBrandsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000006_CreateProductsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000007_CreateProductImagesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000008_CreateProductAccessoriesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000009_CreateShippingZonesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000010_CreateOrdersTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000011_CreateOrderItemsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000012_CreateOrderEventsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000013_CreatePaymentProofsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000014_CreateNotificationsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000015_CreateEmailOutboxTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000016_CreateSettingsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000017_CreateExchangeRatesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-19-000018_CreateAuditLogsTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-20-000001_AddPricesUpdatedAtAndOrderItemSdgColumns.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-20-000002_CreatePaymentAccountsAnnouncementsAndContentTables.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-21-000001_AddRevokeReasonAndFamilyCreatedAtToRefreshTokens.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-21-000002_AddEventTypeAndMetaToOrderEvents.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-24-000001_AddAltToProductImagesTable.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-25-000001_CreatePushNotificationTables.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Migrations/2026-11-26-000001_AddIdempotencyKeyToOrders.php` | yes | full read |
| `app/Database/Seeds/AdminSeeder.php` | partial | admin phone |
| `app/Database/Seeds/BrandsSeeder.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Seeds/CategoriesSeeder.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Seeds/DatabaseSeeder.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Seeds/DevSeeder.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Seeds/ProductsSeeder.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Database/Seeds/SettingsSeeder.php` | yes | full read |
| `app/Database/Seeds/ShippingZonesSeeder.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Announcement.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/AuditLog.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Brand.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Category.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/EmailOutbox.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/ExchangeRate.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/FaqItem.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/HeroSlide.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/HomeSection.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Notification.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Order.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/OrderEvent.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/OrderItem.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/OtpCode.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Page.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/PaymentAccount.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/PaymentProof.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Product.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/ProductAccessory.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/ProductImage.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/PushSubscription.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/RefreshToken.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/Setting.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/ShippingZone.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Entities/User.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/ApiException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/ConflictException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/EntityTooLargeException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/ForbiddenException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/NotFoundException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/TooManyRequestsException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/UnauthorizedException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Exceptions/ValidationFailedException.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Filters/AuthFilter.php` | yes | full read |
| `app/Filters/CorsFilter.php` | yes | full read |
| `app/Filters/ForceHttpsFilter.php` | yes | full read |
| `app/Filters/RoleFilter.php` | yes | full read |
| `app/Filters/SecurityHeadersFilter.php` | yes | full read |
| `app/Filters/ThrottleFilter.php` | yes | full read |
| `app/Language/ar/Api.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Language/ar/Validation.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Language/en/Api.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Language/en/Validation.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Libraries/AdminAudit.php` | yes | full read |
| `app/Libraries/ApiExceptionHandler.php` | yes | full read |
| `app/Libraries/ApiResponse.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Libraries/Input.php` | yes | full read |
| `app/Libraries/PhoneValidator.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Libraries/PushEndpointPolicy.php` | yes | full read |
| `app/Libraries/SafeUrl.php` | yes | full read |
| `app/Models/AnnouncementModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/AuditLogModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/BrandModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/CategoryModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/EmailOutboxModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/ExchangeRateModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/FaqItemModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/HeroSlideModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/HomeSectionModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/NotificationModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/NotificationPreferenceModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/OrderEventModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/OrderItemModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/OrderModel.php` | yes | full read |
| `app/Models/OtpCodeModel.php` | yes | full read |
| `app/Models/PageModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/PaymentAccountModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/PaymentProofModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/ProductAccessoryModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/ProductImageModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/ProductModel.php` | partial | fields |
| `app/Models/PushOutboxModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/PushSubscriptionModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/RefreshTokenModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/SettingModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/ShippingZoneModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Models/UserModel.php` | partial | fields/soft delete |
| `app/Presenters/AdminOrderPresenter.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Presenters/AdminProductPresenter.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Presenters/CustomerOrderPresenter.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Presenters/PublicProductPresenter.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/AnnouncementService.php` | partial | create/update |
| `app/Services/AuditLogService.php` | yes | full read |
| `app/Services/AuthService.php` | yes | full read |
| `app/Services/ContentService.php` | partial | pages create/update; bool casts |
| `app/Services/GoogleAuthService.php` | yes | full read |
| `app/Services/ImageService.php` | yes | full read |
| `app/Services/IntegrityService.php` | partial | repair paths + raw SQL |
| `app/Services/MailService.php` | partial | enqueue/outbox |
| `app/Services/NotificationService.php` | partial | markAsRead |
| `app/Services/OrderService.php` | yes | full read |
| `app/Services/OrderStateMachine.php` | yes | full read |
| `app/Services/OtpService.php` | yes | full read |
| `app/Services/PaymentAccountService.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/PaymentProofService.php` | yes | full read |
| `app/Services/PaymentStateService.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/PricingService.php` | yes | full read |
| `app/Services/ProductService.php` | partial | public list/detail, CRUD |
| `app/Services/Push/FakeProvider.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/Push/FcmProvider.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/Push/PushProviderInterface.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/Push/PushResult.php` | no | automated only (php -l / PHPUnit in CI) |
| `app/Services/Push/WebPushProvider.php` | partial | send guard |
| `app/Services/PushService.php` | partial | register/list/deliver/claim |
| `app/Services/RecentPurchasesService.php` | yes | full read |
| `app/Services/SettingService.php` | partial | public/update/rate |
| `app/Services/TokenService.php` | yes | full read |
| `app/Views/emails/layout.php` | partial | one template |
| `app/Views/emails/lock_warning.php` | partial | one template |
| `app/Views/emails/order_completed.php` | partial | one template |
| `app/Views/emails/order_created.php` | partial | one template |
| `app/Views/emails/order_expired.php` | partial | one template |
| `app/Views/emails/otp.php` | partial | one template |
| `app/Views/emails/out_for_delivery.php` | partial | one template |
| `app/Views/emails/payment_accounts_changed.php` | partial | one template |
| `app/Views/emails/payment_approved.php` | partial | one template |
| `app/Views/emails/payment_received.php` | partial | one template |
| `app/Views/emails/payment_rejected.php` | partial | one template |
| `app/Views/emails/ready_for_pickup.php` | yes | full read |
| `app/Views/welcome_message.php` | no | automated only (php -l / PHPUnit in CI) |
| `scripts/audit-db.sh` | no | automated only (php -l / PHPUnit in CI) |
| `scripts/cleanup-dev-data.sh` | no | automated only (php -l / PHPUnit in CI) |
| `scripts/generate-product-images.php` | no | automated only (php -l / PHPUnit in CI) |
| `scripts/generate-receipt-image.php` | no | automated only (php -l / PHPUnit in CI) |
| `scripts/system-map/genmap.py` | yes | new (written in review) |
| `scripts/system-map/schema.php` | yes | new (written in review) |
| `tests/.htaccess` | no | automated only (php -l / PHPUnit in CI) |
| `tests/_support/Libraries/ConfigReader.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/_support/Models/ExampleModel.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/AccountDeletionTest.php` | yes | new (written in review) |
| `tests/feature/AdminTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/AnnouncementsTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/AuthHardeningTest.php` | yes | new (written in review) |
| `tests/feature/AuthTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/CatalogTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/DataConsistencyFlowTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/DatabaseTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/HideRateTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/IntegrityRepairRaceTest.php` | yes | new (written in review) |
| `tests/feature/MailOutboxClaimTest.php` | yes | new (written in review) |
| `tests/feature/NotificationTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/OrderIntegrityTest.php` | yes | new (written in review) |
| `tests/feature/OrderTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/PaymentAccountsTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/PaymentProofFeatureTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/PersistentLoginFeatureTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/PingTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/ProductAdminHardeningTest.php` | yes | new (written in review) |
| `tests/feature/ProductImageFeatureTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/PushNotificationTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/PushOutboxClaimTest.php` | yes | new (written in review) |
| `tests/feature/RecentPurchasesTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/SettingsTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/feature/TaxonomyAdminTest.php` | yes | new (written in review) |
| `tests/unit/ClaimsScanTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/unit/ClaimsScannerTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/unit/HealthTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/unit/InputTest.php` | yes | new (written in review) |
| `tests/unit/IntegrityCheckTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/unit/NoPlaceholderContactTest.php` | yes | new (written in review) |
| `tests/unit/OrderNumberTest.php` | yes | new (written in review) |
| `tests/unit/PaymentProofServiceTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/unit/PricingServiceTest.php` | no | automated only (php -l / PHPUnit in CI) |
| `tests/unit/PushEndpointPolicyTest.php` | yes | new (written in review) |
| `tests/unit/SafeUrlTest.php` | yes | new (written in review) |
| `tests/unit/ShippingZonesTest.php` | no | automated only (php -l / PHPUnit in CI) |

## wex-web

| file | reviewed | scope |
|---|---|---|
| `.github/workflows/e2e.yml` | yes | full read |
| `.github/workflows/ui-check.yml` | no | automated only (lint / contract-check / CI tests) |
| `.github/workflows/ui-snapshots.yml` | no | automated only (lint / contract-check / CI tests) |
| `e2e/package.json` | no | automated only (lint / contract-check / CI tests) |
| `e2e/playwright.config.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/01-storefront-browse-pdp.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/02-cart-checkout-order.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/03-admin-payments-lifecycle.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/04-settings-content-sync.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/05-regression-bugs.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/06-push-notifications.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/07-quality-gates.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/admin-audit.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/admin-crawler.spec.js` | no | automated only (lint / contract-check / CI tests) |
| `e2e/tests/helpers.js` | no | automated only (lint / contract-check / CI tests) |
| `eslint.config.js` | yes | full read |
| `index.html` | no | automated only (lint / contract-check / CI tests) |
| `public/.htaccess` | no | automated only (lint / contract-check / CI tests) |
| `public/_redirects` | no | automated only (lint / contract-check / CI tests) |
| `public/admin-manifest.webmanifest` | no | automated only (lint / contract-check / CI tests) |
| `public/manifest.webmanifest` | no | automated only (lint / contract-check / CI tests) |
| `public/robots.txt` | no | automated only (lint / contract-check / CI tests) |
| `scripts/audit-db.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/audit.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-admin-english.mjs` | yes | full read |
| `scripts/check-api-surface.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-claims.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-contrast.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-css-classes.js` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-css-modules.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-duplicate-messages.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-i18n.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-layout-contract.js` | no | automated only (lint / contract-check / CI tests) |
| `scripts/check-layout.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci-ui-check.mjs` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/capture-snapshots.mjs` | partial | API mock |
| `scripts/ci/fixtures/admin-dashboard.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/brands.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/categories.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/order-confirmed.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/order-expired.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/order-pending.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/orders-list.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/product-detail.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/products.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/shop.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/fixtures/user-profile.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/ci/package.json` | no | automated only (lint / contract-check / CI tests) |
| `scripts/codemod_phase1.py` | no | automated only (lint / contract-check / CI tests) |
| `scripts/contract-check.js` | yes | full read |
| `scripts/e2e.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/generate_admin_screenshots.py` | no | automated only (lint / contract-check / CI tests) |
| `scripts/generate_screenshots.py` | no | automated only (lint / contract-check / CI tests) |
| `scripts/local-https/nginx.conf.template` | no | automated only (lint / contract-check / CI tests) |
| `scripts/local-https/setup.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/local-https/start.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/local-https/status.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/local-https/stop.sh` | no | automated only (lint / contract-check / CI tests) |
| `scripts/record-fixtures.sh` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-carryover.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-features.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-payments-gap-bug5.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-phase2.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-phase3.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-phase4.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-phase5.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-phase6.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-phase7.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-primitives.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin-products-card-bug4.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/admin.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/api-client.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/api-put.test.js` | yes | new (written in review) |
| `src/__tests__/auth-cache-isolation.test.jsx` | yes | new (written in review) |
| `src/__tests__/auth-pages.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/bug6-polish.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/button-contract.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/cart-checkout-rescue.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/cart-checkout.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/cart-storage.test.js` | yes | new (written in review) |
| `src/__tests__/cart.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/catalog-rescue.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/checkout-idempotency.test.js` | yes | new (written in review) |
| `src/__tests__/compare-favorites.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/compare-feature.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/contact-numbers.test.jsx` | yes | new (written in review) |
| `src/__tests__/countdown.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/datalist-cards-bug3.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/design-system.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/desktop-shell.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/errorMapper.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/focus-trap-bug1.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/formatters.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/hide-rate.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/home-rescue.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/hooks-regressions.test.jsx` | yes | new (written in review) |
| `src/__tests__/i18n-integrity.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/image-manager.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/layering-contract.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/mobile-regression.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/motion.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/open-redirect.test.jsx` | yes | new (written in review) |
| `src/__tests__/order-detail-faq.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/order-number-date.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/order-state-machine.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/orders.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/payment-proof-viewer.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/payment-states.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/pdp-fetch-loop.test.jsx` | yes | new (written in review) |
| `src/__tests__/persistent-auth.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/price-formatting.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/product-routes-bug2.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/push-notifications.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/pwa-workbox.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/quality-phase4.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/receipt-compression.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/server-clock.test.js` | yes | new (written in review) |
| `src/__tests__/shop-settings.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/smoke.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/spec-value-bidi.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/splash-screen.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/stepper.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/storefront-features.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/storefront-flow.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/toast-system.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/utils.test.js` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/viewport.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/__tests__/wishlist-account.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/api/admin.js` | no | automated only (lint / contract-check / CI tests) |
| `src/api/auth.js` | no | automated only (lint / contract-check / CI tests) |
| `src/api/client.js` | yes | full read |
| `src/api/errorMapper.js` | no | automated only (lint / contract-check / CI tests) |
| `src/api/notifications.js` | no | automated only (lint / contract-check / CI tests) |
| `src/api/orders.js` | yes | full read |
| `src/api/products.js` | no | automated only (lint / contract-check / CI tests) |
| `src/api/push.js` | yes | full read |
| `src/api/shop.js` | no | automated only (lint / contract-check / CI tests) |
| `src/app/App.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/app/adminRoutes.js` | no | automated only (lint / contract-check / CI tests) |
| `src/app/router.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/account/PushPreferencesSection.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/account/PushPreferencesSection.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/AdminPushSection.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/AdminPushSection.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/AdminShell.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/AdminShell.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/ConfirmDialog.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/ConfirmDialog.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/DataList.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/DataList.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/FilterBar.jsx` | partial | chips |
| `src/components/admin/FilterBar.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/FilterDraftContext.js` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/ImageManager.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/ImageManager.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/ImageViewer.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/ImageViewer.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/StatusChip.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/StatusChip.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/Stepper.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/Stepper.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/StickyActionBar.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/admin/StickyActionBar.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/auth/AuthSheet.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/auth/AuthSheet.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/AppBar.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/AppBar.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/BottomTabBar.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/BottomTabBar.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/BrandRibbons.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/BrandRibbons.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Button.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Button.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Card.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Card.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Chip.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Chip.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/CopyField.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/CopyField.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Countdown.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Countdown.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/DarkCard.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/DarkCard.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Dialog.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Drawer.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Drawer.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/EmptyState.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/EmptyState.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ErrorBoundary.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ErrorBoundary.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ErrorState.jsx` | yes | full read |
| `src/components/common/ErrorState.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Input.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Input.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/LaptopSvg.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/LatinText.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Lightbox.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Lightbox.module.css` | partial | z-index |
| `src/components/common/Modal.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Modal.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/NoticeLine.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/NoticeLine.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/OrderDate.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/OrderDate.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/OrderNumber.jsx` | yes | full read |
| `src/components/common/OrderNumber.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Page.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Page.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PageHeader.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PageHeader.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PendingOrderBanner.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PendingOrderBanner.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Popover.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Popover.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Price.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Price.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PriceNotice.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PriceNotice.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ProductImage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ProductImage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ProductTypeSvg.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PushPermissionSheet.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PushPermissionSheet.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PwaPrompt.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/PwaPrompt.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/RouteGuard.jsx` | yes | full read |
| `src/components/common/RouteGuard.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ScrollToTop.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/ScrollToTop.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Section.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Section.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Select.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Select.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Sheet.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Sheet.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Skeleton.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Skeleton.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/SplashScreen.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/SplashScreen.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/StickyBar.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/StickyBar.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Tabs.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Tabs.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Toast.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/Toast.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/common/index.js` | no | automated only (lint / contract-check / CI tests) |
| `src/components/compare/CompareTray.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/compare/CompareTray.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/desktop/CartDrawer.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/desktop/CartDrawer.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/desktop/DesktopHeader.jsx` | yes | full read |
| `src/components/desktop/DesktopHeader.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/products/ProductCard.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/products/ProductCard.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/AnnouncementBanner.jsx` | partial | link handling |
| `src/components/store/AnnouncementBanner.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/AnnouncementCard.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/AnnouncementCard.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/AnnouncementToast.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/AnnouncementToast.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/OrdersDisabledBanner.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/OrdersDisabledBanner.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/PaymentAccountsDisplay.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/PaymentAccountsDisplay.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/RecentPurchasesToast.jsx` | partial | session count |
| `src/components/store/RecentPurchasesToast.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/StoreFooter.jsx` | yes | full read |
| `src/components/store/StoreFooter.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/components/store/index.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/admin.ar.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/ar.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/actions.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/breadcrumbs.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/buttons.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/errors.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/index.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/offline.js` | no | automated only (lint / contract-check / CI tests) |
| `src/content/common/pagination.js` | no | automated only (lint / contract-check / CI tests) |
| `src/context/AuthContext.jsx` | yes | full read |
| `src/context/CartContext.jsx` | partial | hydration/totals |
| `src/context/CompareContext.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/context/FavoritesContext.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/context/PushContext.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/context/ToastContext.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/hooks/useIdleTimer.js` | no | automated only (lint / contract-check / CI tests) |
| `src/hooks/useMediaQuery.js` | no | automated only (lint / contract-check / CI tests) |
| `src/hooks/useServerCountdown.js` | no | automated only (lint / contract-check / CI tests) |
| `src/hooks/useViewport.js` | no | automated only (lint / contract-check / CI tests) |
| `src/layouts/AdminLayout.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/layouts/AdminLayout.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/layouts/StoreLayout.jsx` | partial | shop info / outlet |
| `src/layouts/StoreLayout.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/main.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/Reveal.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/Reveal.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/Ripple.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/Ripple.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/Stagger.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/Stagger.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/index.js` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/motionTokens.js` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/useCountUp.js` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/useMotionMode.js` | no | automated only (lint / contract-check / CI tests) |
| `src/motion/useParallax.js` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminAnnouncementsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminAnnouncementsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminAuditLogsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminAuditLogsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminBrandsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminCategoriesPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminCommon.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminDashboardPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminDashboardPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminFaqPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminFaqPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminHeroSlidesPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminHeroSlidesPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminHomeSectionsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminHomeSectionsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminOrderDetailPage.jsx` | partial | thumbnails/load |
| `src/pages/admin/AdminOrderDetailPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminOrdersPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminOrdersPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminPagesPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminPagesPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminPaymentAccountsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminPaymentAccountsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminPaymentsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminPaymentsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminProductFormPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminProductFormPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminProductsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminProductsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminRatesPage.jsx` | partial | fetch |
| `src/pages/admin/AdminRatesPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminRecentPurchasesPage.jsx` | partial | toggle |
| `src/pages/admin/AdminRecentPurchasesPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminSettingsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminSettingsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminShippingZonesPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminShippingZonesPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/admin/AdminUsersPage.jsx` | partial | filters/empty state |
| `src/pages/admin/AdminUsersPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/auth/Auth.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/auth/LoginPage.jsx` | partial | returnTo/Google callback |
| `src/pages/auth/VerifyOtpPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/errors/NotFoundPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/errors/NotFoundPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/errors/OfflinePage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/errors/OfflinePage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/errors/RouteErrorPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/AccountPage.jsx` | partial | support link |
| `src/pages/store/AccountPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/CartPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/CartPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/CatalogPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/CatalogPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/CheckoutPage.jsx` | partial | submit flow |
| `src/pages/store/CheckoutPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/ComparePage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/ComparePage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/FaqPage.jsx` | yes | full read |
| `src/pages/store/FaqPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/FavoritesPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/FavoritesPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/HomePage.jsx` | partial | contact block |
| `src/pages/store/HomePage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/NotificationsPage.jsx` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/NotificationsPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/OrderDetailPage.jsx` | partial | load/poll |
| `src/pages/store/OrderDetailPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/OrdersPage.jsx` | partial | fetch/retry |
| `src/pages/store/OrdersPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/pages/store/ProductDetailPage.jsx` | partial | data load |
| `src/pages/store/ProductDetailPage.module.css` | no | automated only (lint / contract-check / CI tests) |
| `src/styles/admin.css` | no | automated only (lint / contract-check / CI tests) |
| `src/styles/global.css` | no | automated only (lint / contract-check / CI tests) |
| `src/styles/tokens.css` | no | automated only (lint / contract-check / CI tests) |
| `src/sw.js` | yes | full read |
| `src/utils/errorMapper.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/formatSpecValue.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/formatters.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/haptics.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/idempotency.js` | yes | full read |
| `src/utils/imageCompressor.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/index.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/orderState.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/pluralAr.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/prefetch.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/productUrl.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/push.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/relativeTimeAr.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/safeFormatters.js` | no | automated only (lint / contract-check / CI tests) |
| `src/utils/shopSettings.js` | yes | full read |
| `src/utils/swPushHelpers.js` | partial | URL validation |
| `src/utils/swrCache.js` | yes | full read |
| `src/utils/url.js` | yes | full read |
| `tests/fixtures/admin-audit-logs.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-brands.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-categories.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-dashboard.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-faq.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-hero-slides.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-home-sections.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-orders.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-pages.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-payment-accounts.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-products.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-rates.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-recent-purchases.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-settings.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-shipping-zones.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/admin-users.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/announcements.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/brands.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/categories.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/customer-orders.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/faq.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/hero-slides.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/home-sections.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/me-admin.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/me-customer.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/notifications.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/pages.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/product-detail.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/products.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/recent-purchases.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/shipping-zones.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/fixtures/shop.json` | no | automated only (lint / contract-check / CI tests) |
| `tests/journeys.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `tests/route-smoke.test.jsx` | no | automated only (lint / contract-check / CI tests) |
| `vite.config.js` | yes | full read |
