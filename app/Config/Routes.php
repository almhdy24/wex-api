<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// API Version 1 Routes
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes) {
    // Health check
    $routes->get('ping', 'PingController::index');

    // Authentication (Public)
    $routes->group('auth', static function (RouteCollection $routes) {
        $routes->post('google', 'AuthController::google', ['filter' => 'throttle:30,60,google_auth']);
        $routes->post('email/request', 'AuthController::requestEmailOtp', ['filter' => 'throttle:10,3600,otp_req']);
        $routes->post('email/verify', 'AuthController::verifyEmailOtp', ['filter' => 'throttle:10,600,otp_ver']);
        $routes->post('refresh', 'AuthController::refresh');
        $routes->post('logout', 'AuthController::logout');
        $routes->post('logout-all', 'AuthController::logoutAll', ['filter' => 'auth']);
    });

    // Current User Profile & Preferences (Authenticated)
    $routes->group('me', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->get('/', 'MeController::show');
        $routes->patch('/', 'MeController::update');
        $routes->patch('preferences', 'MeController::updatePreferences');
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

    // Public Catalog
    $routes->get('shop', 'SettingsController::shop');
    $routes->get('categories', 'CategoryController::index');
    $routes->get('brands', 'BrandController::index');
    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:segment)', 'ProductController::show/$1');
    $routes->get('shipping-zones', 'ShippingZoneController::index');

    // Admin & Staff Operations (Auth + Role Filter)
    $routes->group('admin', ['filter' => ['auth', 'role:admin,staff']], static function (RouteCollection $routes) {
        // Orders & Payment Reviews (Staff & Admin)
        $routes->get('orders', 'Admin\OrderAdminController::index');
        $routes->get('orders/(:segment)', 'Admin\OrderAdminController::show/$1');
        $routes->patch('orders/(:segment)/status', 'Admin\OrderAdminController::updateStatus/$1');
        $routes->post('orders/(:segment)/lock', 'Admin\OrderAdminController::extendLock/$1');
        $routes->post('orders/(:segment)/proofs/(:num)/review', 'Admin\PaymentAdminController::review/$1/$2');

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

        // Exchange Rates (AED -> SDG) (Admin only)
        $routes->get('rates', 'Admin\RateAdminController::index', ['filter' => 'role:admin']);
        $routes->post('rates', 'Admin\RateAdminController::update', ['filter' => 'role:admin']);

        // Shipping Zones (Admin only)
        $routes->get('shipping-zones', 'Admin\ShippingZoneAdminController::index', ['filter' => 'role:admin']);
        $routes->post('shipping-zones', 'Admin\ShippingZoneAdminController::create', ['filter' => 'role:admin']);
        $routes->patch('shipping-zones/(:num)', 'Admin\ShippingZoneAdminController::update/$1', ['filter' => 'role:admin']);
        $routes->delete('shipping-zones/(:num)', 'Admin\ShippingZoneAdminController::delete/$1', ['filter' => 'role:admin']);
    });
});

// Fallback 404 Override for API JSON envelope
$routes->set404Override('App\Controllers\Api\V1\ErrorController::notFound');
