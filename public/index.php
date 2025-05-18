<?php
// Include application configuration
require_once '../app/config/config.php';

// Simple routing
$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/sistem-qurban', '', $request);

// Default route
if ($request == '/' || $request == '') {
    require '../app/views/auth/login.php';
    exit;
}

// Authentication routes
if ($request == '/login') {
    require '../app/controllers/AuthController.php';
    (new AuthController())->login();
    exit;
}

if ($request == '/register') {
    require '../app/controllers/AuthController.php';
    (new AuthController())->register();
    exit;
}

if ($request == '/logout') {
    require '../app/controllers/AuthController.php';
    (new AuthController())->logout();
    exit;
}

// Protected routes - check authentication
if (!isLoggedIn() && $request != '/login' && $request != '/register') {
    redirect('/login');
    exit;
}

// Route based on user role
$role = $_SESSION['user_role'] ?? '';

// Admin routes
if (strpos($request, '/admin') === 0 && hasRole('admin')) {
    // Admin controllers and views
    if ($request == '/admin/dashboard') {
        require '../app/controllers/AdminController.php';
        (new AdminController())->dashboard();
        exit;
    }
    // Add more admin routes as needed
}

// Panitia routes
else if (strpos($request, '/panitia') === 0 && hasRole('panitia')) {
    // Panitia controllers and views
    if ($request == '/panitia/dashboard') {
        require '../app/controllers/PanitiaController.php';
        (new PanitiaController())->dashboard();
        exit;
    }
    // Add more panitia routes as needed
}

// Warga routes
else if (strpos($request, '/warga') === 0 && hasRole('warga')) {
    // Warga controllers and views
    if ($request == '/warga/dashboard') {
        require '../app/controllers/WargaController.php';
        (new WargaController())->dashboard();
        exit;
    }
    // Add more warga routes as needed
}

// Berqurban routes
else if (strpos($request, '/berqurban') === 0 && hasRole('berqurban')) {
    // Berqurban controllers and views
    if ($request == '/berqurban/dashboard') {
        require '../app/controllers/BerqurbanController.php';
        (new BerqurbanController())->dashboard();
        exit;
    }
    // Add more berqurban routes as needed
}

// Route not found
require '../app/views/errors/404.php';
