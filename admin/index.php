<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect ke halaman login jika belum login
if (!is_logged_in()) {
    redirect('login.php');
} else {
    // Redirect ke dashboard sesuai role
    redirect('dashboard.php');
}
?>
