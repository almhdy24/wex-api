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

    // Public Catalog
    $routes->get('shop', 'SettingsController::shop');
    $routes->get('categories', 'CategoryController::index');
    $routes->get('brands', 'BrandController::index');
    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:segment)', 'ProductController::show/$1');
    $routes->get('shipping-zones', 'ShippingZoneController::index');

    // Admin Catalog Management (Auth + Admin Role)
    $routes->group('admin', ['filter' => ['auth', 'role:admin']], static function (RouteCollection $routes) {
        // Products CRUD
        $routes->get('products', 'Admin\ProductAdminController::index');
        $routes->post('products', 'Admin\ProductAdminController::create');
        $routes->get('products/(:num)', 'Admin\ProductAdminController::show/$1');
        $routes->patch('products/(:num)', 'Admin\ProductAdminController::update/$1');
        $routes->delete('products/(:num)', 'Admin\ProductAdminController::delete/$1');
        $routes->patch('products/(:num)/stock', 'Admin\ProductAdminController::updateStock/$1');
        $routes->post('products/(:num)/accessories', 'Admin\ProductAdminController::linkAccessory/$1');
        $routes->delete('products/(:num)/accessories/(:num)', 'Admin\ProductAdminController::unlinkAccessory/$1/$2');

        // Product Images
        $routes->post('products/(:num)/images', 'Admin\ProductImageAdminController::upload/$1');
        $routes->patch('products/(:num)/images/(:num)', 'Admin\ProductImageAdminController::update/$1/$2');
        $routes->delete('products/(:num)/images/(:num)', 'Admin\ProductImageAdminController::delete/$1/$2');

        // Categories CRUD
        $routes->get('categories', 'Admin\CategoryAdminController::index');
        $routes->post('categories', 'Admin\CategoryAdminController::create');
        $routes->get('categories/(:num)', 'Admin\CategoryAdminController::show/$1');
        $routes->patch('categories/(:num)', 'Admin\CategoryAdminController::update/$1');
        $routes->delete('categories/(:num)', 'Admin\CategoryAdminController::delete/$1');

        // Brands CRUD
        $routes->get('brands', 'Admin\BrandAdminController::index');
        $routes->post('brands', 'Admin\BrandAdminController::create');
        $routes->get('brands/(:num)', 'Admin\BrandAdminController::show/$1');
        $routes->patch('brands/(:num)', 'Admin\BrandAdminController::update/$1');
        $routes->delete('brands/(:num)', 'Admin\BrandAdminController::delete/$1');

        // Settings & Operational Configuration
        $routes->get('settings', 'Admin\SettingAdminController::index');
        $routes->patch('settings', 'Admin\SettingAdminController::update');

        // Exchange Rates (AED -> SDG)
        $routes->get('rates', 'Admin\RateAdminController::index');
        $routes->post('rates', 'Admin\RateAdminController::update');

        // Shipping Zones
        $routes->get('shipping-zones', 'Admin\ShippingZoneAdminController::index');
        $routes->post('shipping-zones', 'Admin\ShippingZoneAdminController::create');
        $routes->patch('shipping-zones/(:num)', 'Admin\ShippingZoneAdminController::update/$1');
        $routes->delete('shipping-zones/(:num)', 'Admin\ShippingZoneAdminController::delete/$1');
    });
});

// Fallback 404 Override for API JSON envelope
$routes->set404Override('App\Controllers\Api\V1\ErrorController::notFound');
