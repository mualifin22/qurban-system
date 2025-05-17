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

// Authentication route
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

if ($request == 'logout') {
  require '../app/controllers/AuthController.php';
  (new AuthController())->logout();
  exit;
}

// Protected routes - check Authentication
if (!isLoggedIn() && $request != '/login' && $request != '/register') {
  redirect ('/login');
  exit;
}

// Route based on user role 
$role = $_SESSION['user_role'] ?? '';

// Admin routes
if (strpos($request, '/admin') === 0 && hasrole('admin')) {

}
?>  
