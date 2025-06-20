<?php
session_start();

include '../includes/db.php';
include '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = sanitizeInput($_POST['password']);

    $stmt = $conn->prepare("SELECT id, username, password, role, nik_warga FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nik_warga'] = $user['nik_warga']; // Simpan NIK jika ada

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
    redirectToLogin();
}

if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    header("Location: index.php");
    exit();
}
?>