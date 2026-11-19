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
});

// Fallback 404 Override for API JSON envelope
$routes->set404Override('App\Controllers\Api\V1\ErrorController::notFound');
