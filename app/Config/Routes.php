<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// API Version 1 Routes
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes) {
    // Health check
    $routes->get('ping', 'PingController::index');
});

// Fallback 404 Override for API JSON envelope
$routes->set404Override('App\Controllers\Api\V1\ErrorController::notFound');
