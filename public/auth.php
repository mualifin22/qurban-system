<?php
// public/auth.php
session_start();

include '../includes/db.php';
include '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = sanitizeInput($_POST['password']);

    // Query untuk mengambil user dari database
    $stmt = $conn->prepare("SELECT id, username, password, role, nik_warga FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Verifikasi password
        if (verifyPassword($password, $user['password'])) {
            // Password benar, set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nik_warga'] = $user['nik_warga']; // Simpan NIK jika ada

            // Redirect ke dashboard sesuai role
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Username atau password salah.";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Username atau password salah.";
        header("Location: index.php");
        exit();
    }
} else {
    // Jika diakses langsung tanpa POST, redirect ke halaman login
    redirectToLogin();
}
// public/auth.php
// ... kode sebelumnya ...

if (isset($_GET['logout'])) {
    // Hapus semua variabel session
    $_SESSION = array();

    // Hapus session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Hancurkan session
    session_destroy();

    // Redirect ke halaman login
    header("Location: index.php");
    exit();
}
// ... kode selanjutnya ...
?>