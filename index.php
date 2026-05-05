<?php

declare(strict_types=1);

require_once __DIR__ . '/cityslot/bootstrap.php';

$router = new Router();

// Home
$router->get('/', fn () => HomeController::index());

// Auth
$router->get('/login', fn () => AuthController::showLogin());
$router->post('/login', fn () => AuthController::handleLogin($_POST));
$router->get('/register', fn () => AuthController::showRegister());
$router->post('/register', fn () => AuthController::handleRegister($_POST));
$router->post('/logout', fn () => AuthController::logout());

// Driver
$router->get('/driver/dashboard', fn () => DriverController::dashboard());
$router->get('/driver/search', fn () => DriverController::search());
$router->get('/driver/book', fn () => DriverController::book());
$router->post('/driver/book', fn () => DriverController::book());
$router->get('/driver/bookings', fn () => DriverController::bookings());
$router->get('/driver/bookingdetail', fn () => DriverController::bookingDetail());
$router->post('/driver/bookingdetail', fn () => DriverController::bookingDetail());
$router->get('/driver/vehicles', fn () => DriverController::vehicles());
$router->post('/driver/vehicles', fn () => DriverController::vehicles());
$router->get('/driver/favorites', fn () => DriverController::favorites());
$router->post('/driver/favorites', fn () => DriverController::favorites());
$router->get('/driver/notifications', fn () => DriverController::notifications());
$router->post('/driver/notifications', fn () => DriverController::notifications());
$router->get('/driver/fines', fn () => DriverController::fines());
$router->post('/driver/fines', fn () => DriverController::fines());

// Owner
$router->get('/owner/dashboard', fn () => OwnerController::dashboard());
$router->get('/owner/spots', fn () => OwnerController::spots());
$router->post('/owner/spots', fn () => OwnerController::spots());
$router->get('/owner/earnings', fn () => OwnerController::earnings());
$router->post('/owner/earnings', fn () => OwnerController::earnings());
$router->get('/owner/verify', fn () => OwnerController::verify());
$router->post('/owner/verify', fn () => OwnerController::verify());

// Officer
$router->get('/officer/dashboard', fn () => OfficerController::dashboard());
$router->get('/officer/violation', fn () => OfficerController::violation());
$router->post('/officer/violation', fn () => OfficerController::violation());

// Admin
$router->get('/admin/dashboard', fn () => AdminController::dashboard());
$router->get('/admin/spots', fn () => AdminController::spots());
$router->get('/admin/fines', fn () => AdminController::fines());
$router->post('/admin/fines', fn () => AdminController::fines());
$router->get('/admin/appeals', fn () => AdminController::appeals());
$router->post('/admin/appeals', fn () => AdminController::appeals());
$router->get('/admin/zones', fn () => AdminController::zones());
$router->post('/admin/zones', fn () => AdminController::zones());
$router->get('/admin/owners', fn () => AdminController::owners());
$router->post('/admin/owners', fn () => AdminController::owners());
$router->get('/admin/heatmap', fn () => AdminController::heatmap());
$router->get('/admin/view-doc', fn () => AdminController::viewDoc());

$router->dispatch();

