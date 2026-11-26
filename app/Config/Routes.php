<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Browser installer for hosts without SSH (404 unless INSTALL_TOKEN is set; see InstallController)
$routes->match(['GET', 'POST'], 'api/install', '\App\Controllers\InstallController::index', ['filter' => 'throttle:20,600,install_ui']);

// API Version 1 Routes
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes) {
    // Health check
    $routes->get('ping', 'PingController::index');

    // Shared-hosting internals (404 unless CRON_TOKEN / INSTALL_TOKEN are configured)
    $routes->match(['get', 'post'], 'internal/cron', 'InternalController::cron', ['filter' => 'throttle:12,60,cron']);
    $routes->post('internal/install', 'InternalController::install', ['filter' => 'throttle:5,600,install']);

    // Authentication (Public)
    $routes->group('auth', static function (RouteCollection $routes) {
        $routes->post('google', 'AuthController::google', ['filter' => 'throttle:30,60,google_auth']);
        $routes->post('email/request', 'AuthController::requestEmailOtp', ['filter' => 'throttle:10,3600,otp_req']);
        $routes->post('email/verify', 'AuthController::verifyEmailOtp', ['filter' => 'throttle:10,600,otp_ver']);
        // Test-only: not even registered in production (the controller also refuses).
        if (ENVIRONMENT !== 'production') {
            $routes->post('testing-login', 'AuthController::testingLogin');
        }
        $routes->post('refresh', 'AuthController::refresh');
        $routes->post('logout', 'AuthController::logout');
        $routes->post('logout-all', 'AuthController::logoutAll', ['filter' => 'auth']);
    });

    // Current User Profile & Preferences (Authenticated)
    $routes->group('me', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->get('/', 'MeController::show');
        $routes->patch('/', 'MeController::update');
        $routes->patch('preferences', 'MeController::updatePreferences');
        $routes->post('link-google', 'MeController::linkGoogle');
        $routes->post('unlink-google', 'MeController::unlinkGoogle');
        $routes->delete('/', 'MeController::delete');
    });

    // Customer Orders & Payment Proofs (Authenticated)
    $routes->group('orders', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->get('/', 'OrderController::index');
        $routes->post('/', 'OrderController::create');
        $routes->get('(:segment)', 'OrderController::show/$1');
        $routes->post('(:segment)/cancel', 'OrderController::cancel/$1');
        $routes->post('(:segment)/reorder', 'OrderController::reorder/$1');
        $routes->post('(:segment)/payment-proof', 'PaymentProofController::upload/$1');
        $routes->get('(:segment)/payment-proof/(:num)/image', 'PaymentProofController::streamImage/$1/$2');
    });

    // In-App Notifications (Authenticated)
    $routes->group('notifications', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->get('/', 'NotificationController::index');
        $routes->get('unread-count', 'NotificationController::unreadCount');
        $routes->patch('(:num)/read', 'NotificationController::markRead/$1');
        $routes->post('mark-all-read', 'NotificationController::markAllRead');
    });

    // Push Notifications (Authenticated + Rate Limited)
    $routes->group('push', ['filter' => ['auth', 'throttle:60,60,push']], static function (RouteCollection $routes) {
        $routes->get('config', 'PushController::config');
        $routes->get('subscriptions', 'PushController::listSubscriptions');
        $routes->post('subscriptions', 'PushController::subscribe');
        $routes->delete('subscriptions', 'PushController::unsubscribe');
        $routes->delete('subscriptions/(:num)', 'PushController::deleteSubscription/$1');
        $routes->get('preferences', 'PushController::getPreferences');
        $routes->put('preferences', 'PushController::updatePreferences');
        $routes->post('test', 'PushController::sendTest', ['filter' => ['auth', 'throttle:10,60,push_test']]);
    });

    // Public Catalog
    $routes->get('shop', 'SettingsController::shop');
    $routes->get('categories', 'CategoryController::index');
    $routes->get('brands', 'BrandController::index');
    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:segment)', 'ProductController::show/$1');
    $routes->get('shipping-zones', 'ShippingZoneController::index');

    // Public Announcements
    $routes->get('announcements', 'AnnouncementController::index');

    // Public Recent Purchases (Storefront Ticker)
    $routes->get('recent-purchases', 'RecentPurchasesController::index');

    // Public Content (Direct + Content prefix aliases)
    $routes->get('hero-slides', 'ContentController::heroSlides');
    $routes->get('home-sections', 'ContentController::homeSections');
    $routes->get('faq', 'ContentController::faq');
    $routes->get('pages', 'ContentController::pages');
    $routes->get('pages/(:segment)', 'ContentController::page/$1');

    $routes->group('content', static function (RouteCollection $routes) {
        $routes->get('hero-slides', 'ContentController::heroSlides');
        $routes->get('home-sections', 'ContentController::homeSections');
        $routes->get('faq', 'ContentController::faq');
        $routes->get('pages', 'ContentController::pages');
        $routes->get('pages/(:segment)', 'ContentController::page/$1');
    });

    // Admin & Staff Operations (Auth + Role Filter)
    $routes->group('admin', ['filter' => ['auth', 'role:admin,staff']], static function (RouteCollection $routes) {
        // Operational Dashboard (Admin & Staff)
        $routes->get('dashboard', 'Admin\DashboardController::index');

        // Orders & Payment Reviews (Staff & Admin)
        $routes->get('orders', 'Admin\OrderAdminController::index');
        $routes->get('orders/(:segment)', 'Admin\OrderAdminController::show/$1');
        $routes->patch('orders/(:segment)/status', 'Admin\OrderAdminController::updateStatus/$1');
        $routes->post('orders/(:segment)/lock', 'Admin\OrderAdminController::extendLock/$1');
        $routes->post('orders/(:segment)/reset-orphan', 'Admin\OrderAdminController::resetOrphan/$1');
        $routes->post('orders/(:segment)/proofs/(:num)/review', 'Admin\PaymentAdminController::review/$1/$2');

        // Payment Accounts CRUD (Admin only)
        $routes->get('payment-accounts', 'Admin\PaymentAccountAdminController::index', ['filter' => 'role:admin']);
        $routes->post('payment-accounts', 'Admin\PaymentAccountAdminController::create', ['filter' => 'role:admin']);
        $routes->get('payment-accounts/(:num)', 'Admin\PaymentAccountAdminController::show/$1', ['filter' => 'role:admin']);
        $routes->patch('payment-accounts/(:num)', 'Admin\PaymentAccountAdminController::update/$1', ['filter' => 'role:admin']);
        $routes->delete('payment-accounts/(:num)', 'Admin\PaymentAccountAdminController::delete/$1', ['filter' => 'role:admin']);
        $routes->patch('payment-accounts/(:num)/toggle', 'Admin\PaymentAccountAdminController::toggle/$1', ['filter' => 'role:admin']);
        $routes->post('payment-accounts/reorder', 'Admin\PaymentAccountAdminController::reorder', ['filter' => 'role:admin']);

        // Announcements Management (Admin & Staff)
        $routes->get('announcements', 'Admin\AnnouncementAdminController::index');
        $routes->post('announcements', 'Admin\AnnouncementAdminController::create');
        $routes->get('announcements/(:num)', 'Admin\AnnouncementAdminController::show/$1');
        $routes->patch('announcements/(:num)', 'Admin\AnnouncementAdminController::update/$1');
        $routes->delete('announcements/(:num)', 'Admin\AnnouncementAdminController::delete/$1');
        $routes->patch('announcements/(:num)/toggle', 'Admin\AnnouncementAdminController::toggle/$1');

        // Recent Purchases Ticker Management (Admin & Staff)
        $routes->get('recent-purchases', 'Admin\RecentPurchasesAdminController::index');
        $routes->patch('recent-purchases/(:num)/toggle-hide', 'Admin\RecentPurchasesAdminController::toggleHide/$1');
        $routes->patch('recent-purchases/settings', 'Admin\RecentPurchasesAdminController::settings');

        // Content Management - Hero Slides (Admin & Staff)
        $routes->get('hero-slides', 'Admin\HeroSlideAdminController::index');
        $routes->post('hero-slides', 'Admin\HeroSlideAdminController::create');
        $routes->get('hero-slides/(:num)', 'Admin\HeroSlideAdminController::show/$1');
        $routes->patch('hero-slides/(:num)', 'Admin\HeroSlideAdminController::update/$1');
        $routes->delete('hero-slides/(:num)', 'Admin\HeroSlideAdminController::delete/$1');
        $routes->patch('hero-slides/(:num)/toggle', 'Admin\HeroSlideAdminController::toggle/$1');
        $routes->post('hero-slides/reorder', 'Admin\HeroSlideAdminController::reorder');

        // Content Management - Home Sections (Admin & Staff)
        $routes->get('home-sections', 'Admin\HomeSectionAdminController::index');
        $routes->post('home-sections', 'Admin\HomeSectionAdminController::create');
        $routes->get('home-sections/(:num)', 'Admin\HomeSectionAdminController::show/$1');
        $routes->patch('home-sections/(:num)', 'Admin\HomeSectionAdminController::update/$1');
        $routes->delete('home-sections/(:num)', 'Admin\HomeSectionAdminController::delete/$1');
        $routes->patch('home-sections/(:num)/toggle', 'Admin\HomeSectionAdminController::toggle/$1');
        $routes->post('home-sections/reorder', 'Admin\HomeSectionAdminController::reorder');

        // Content Management - FAQ Items (Admin & Staff)
        $routes->get('faq', 'Admin\FaqAdminController::index');
        $routes->post('faq', 'Admin\FaqAdminController::create');
        $routes->get('faq/(:num)', 'Admin\FaqAdminController::show/$1');
        $routes->patch('faq/(:num)', 'Admin\FaqAdminController::update/$1');
        $routes->delete('faq/(:num)', 'Admin\FaqAdminController::delete/$1');
        $routes->patch('faq/(:num)/toggle', 'Admin\FaqAdminController::toggle/$1');
        $routes->post('faq/reorder', 'Admin\FaqAdminController::reorder');

        // Content Management - Pages (Admin & Staff)
        $routes->get('pages', 'Admin\PageAdminController::index');
        $routes->post('pages', 'Admin\PageAdminController::create');
        $routes->get('pages/(:num)', 'Admin\PageAdminController::show/$1');
        $routes->patch('pages/(:num)', 'Admin\PageAdminController::update/$1');
        $routes->delete('pages/(:num)', 'Admin\PageAdminController::delete/$1');
        $routes->patch('pages/(:num)/toggle', 'Admin\PageAdminController::toggle/$1');

        // Products CRUD (Admin only)
        $routes->get('products', 'Admin\ProductAdminController::index', ['filter' => 'role:admin']);
        $routes->post('products', 'Admin\ProductAdminController::create', ['filter' => 'role:admin']);
        $routes->get('products/(:num)', 'Admin\ProductAdminController::show/$1', ['filter' => 'role:admin']);
        $routes->patch('products/(:num)', 'Admin\ProductAdminController::update/$1', ['filter' => 'role:admin']);
        $routes->delete('products/(:num)', 'Admin\ProductAdminController::delete/$1', ['filter' => 'role:admin']);
        $routes->patch('products/(:num)/stock', 'Admin\ProductAdminController::updateStock/$1', ['filter' => 'role:admin']);
        $routes->post('products/(:num)/accessories', 'Admin\ProductAdminController::linkAccessory/$1', ['filter' => 'role:admin']);
        $routes->delete('products/(:num)/accessories/(:num)', 'Admin\ProductAdminController::unlinkAccessory/$1/$2', ['filter' => 'role:admin']);

        // Product Images (Admin only)
        $routes->post('products/(:num)/images', 'Admin\ProductImageAdminController::upload/$1', ['filter' => 'role:admin']);
        $routes->put('products/(:num)/images/order', 'Admin\ProductImageAdminController::reorder/$1', ['filter' => 'role:admin']);
        $routes->patch('products/(:num)/images/(:num)', 'Admin\ProductImageAdminController::update/$1/$2', ['filter' => 'role:admin']);
        $routes->delete('products/(:num)/images/(:num)', 'Admin\ProductImageAdminController::delete/$1/$2', ['filter' => 'role:admin']);

        // Categories CRUD (Admin only)
        $routes->get('categories', 'Admin\CategoryAdminController::index', ['filter' => 'role:admin']);
        $routes->post('categories', 'Admin\CategoryAdminController::create', ['filter' => 'role:admin']);
        $routes->get('categories/(:num)', 'Admin\CategoryAdminController::show/$1', ['filter' => 'role:admin']);
        $routes->patch('categories/(:num)', 'Admin\CategoryAdminController::update/$1', ['filter' => 'role:admin']);
        $routes->delete('categories/(:num)', 'Admin\CategoryAdminController::delete/$1', ['filter' => 'role:admin']);

        // Brands CRUD (Admin only)
        $routes->get('brands', 'Admin\BrandAdminController::index', ['filter' => 'role:admin']);
        $routes->post('brands', 'Admin\BrandAdminController::create', ['filter' => 'role:admin']);
        $routes->get('brands/(:num)', 'Admin\BrandAdminController::show/$1', ['filter' => 'role:admin']);
        $routes->patch('brands/(:num)', 'Admin\BrandAdminController::update/$1', ['filter' => 'role:admin']);
        $routes->delete('brands/(:num)', 'Admin\BrandAdminController::delete/$1', ['filter' => 'role:admin']);

        // Settings & Operational Configuration (Admin only)
        $routes->get('settings', 'Admin\SettingAdminController::index', ['filter' => 'role:admin']);
        $routes->patch('settings', 'Admin\SettingAdminController::update', ['filter' => 'role:admin']);
        $routes->get('setup-status', 'Admin\SettingAdminController::setupStatus', ['filter' => 'role:admin']);

        // Push Notifications Admin (Admin only)
        $routes->post('push/broadcast', 'Admin\PushAdminController::broadcast', ['filter' => 'role:admin']);
        $routes->get('push/stats', 'Admin\PushAdminController::stats', ['filter' => 'role:admin']);
        $routes->get('push/log', 'Admin\PushAdminController::log', ['filter' => 'role:admin']);
        $routes->put('push/settings', 'Admin\PushAdminController::updateSettings', ['filter' => 'role:admin']);
        if (ENVIRONMENT !== 'production') { // fake push provider log: test-only
            $routes->get('push/fake-log', 'Admin\PushAdminController::fakeLog', ['filter' => 'role:admin']);
            $routes->delete('push/fake-log', 'Admin\PushAdminController::fakeLog', ['filter' => 'role:admin']);
        }

        // Exchange Rates (AED -> SDG) (Admin only)
        $routes->get('rates', 'Admin\RateAdminController::index', ['filter' => 'role:admin']);
        $routes->post('rates', 'Admin\RateAdminController::update', ['filter' => 'role:admin']);

        // Shipping Zones (Admin only)
        $routes->get('shipping-zones', 'Admin\ShippingZoneAdminController::index', ['filter' => 'role:admin']);
        $routes->post('shipping-zones', 'Admin\ShippingZoneAdminController::create', ['filter' => 'role:admin']);
        $routes->patch('shipping-zones/(:num)', 'Admin\ShippingZoneAdminController::update/$1', ['filter' => 'role:admin']);
        $routes->delete('shipping-zones/(:num)', 'Admin\ShippingZoneAdminController::delete/$1', ['filter' => 'role:admin']);

        // User Management (Admin only)
        $routes->get('users', 'Admin\UserAdminController::index', ['filter' => 'role:admin']);
        $routes->get('users/(:num)', 'Admin\UserAdminController::show/$1', ['filter' => 'role:admin']);
        $routes->patch('users/(:num)/role', 'Admin\UserAdminController::updateRole/$1', ['filter' => 'role:admin']);
        $routes->patch('users/(:num)/status', 'Admin\UserAdminController::updateStatus/$1', ['filter' => 'role:admin']);

        // Audit Logs (Admin only)
        $routes->get('audit-logs', 'Admin\AuditAdminController::index', ['filter' => 'role:admin']);
    });
});

// Public storefront pages rendered with server-side meta (crawlers / WhatsApp previews).
// The SPA takes over in the browser; see App\Services\SeoService.
$routes->get('/', 'SeoController::page');
$routes->get('catalog', 'SeoController::page/catalog');
$routes->get('faq', 'SeoController::page/faq');
$routes->get('products/(:segment)', 'SeoController::page/products/$1');
$routes->get('pages/(:segment)', 'SeoController::page/pages/$1');
$routes->get('sitemap.xml', 'SeoController::sitemap');
$routes->get('robots.txt', 'SeoController::robots');

// Fallback 404 Override for API JSON envelope
$routes->set404Override('App\Controllers\Api\V1\ErrorController::notFound');
