<?php
// includes/functions.php

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

// Fungsi-fungsi berikut TIDAK lagi memanggil session_start()
// Karena kita berasumsi session sudah dimulai di header.php atau auth.php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectToLogin() {
    header("Location: /sistem_qurban/public/index.php"); // Sesuaikan path jika perlu
    exit();
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isPanitia() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'panitia';
}

function isBerqurban() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'berqurban';
}

function isWarga() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'warga';
}

// Fungsi untuk sanitasi input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk format mata uang Rupiah
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Fungsi untuk mendapatkan data user yang sedang login
function getCurrentUser($conn) {
    // Session harus sudah aktif sebelum memanggil fungsi ini
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    return null;
}
?>