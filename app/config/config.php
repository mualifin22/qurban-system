<?php
// Application Configuration
define('APP_NAME', 'Sistem Manajemen Qurban');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(dirname(__DIR__)));
define('UPLOAD_DIR', BASE_PATH . '/public/uploads');

// Session Configuration
session_start();

// Error reporting (set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database Configuration
require_once BASE_PATH . '/config/database.php';

// Function to redirect
function redirect($path) {
    header("Location: " . APP_URL . $path);
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// check user rule 
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Flash messages 
function setFlash($type, $message) {
  $_SESSION['flash'] = [
    'type' => $type,
    'message' => $message
  ];
}

function getFlashMessage() {
  if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
  }
  return null;
}
?>
