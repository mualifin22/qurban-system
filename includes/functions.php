<?php
// Fungsi untuk membersihkan input dari user
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Fungsi untuk memeriksa apakah user sudah login
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk memeriksa peran user
function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Fungsi untuk redirect
function redirect($location) {
    header("Location: " . $location);
    exit();
}

// Fungsi untuk menghasilkan QR Code (membutuhkan library phpqrcode)
function generate_qr_code($data, $filename) {
    require_once __DIR__ . '/../lib/phpqrcode/qrlib.php'; // Sesuaikan path jika perlu
    $filepath = __DIR__ . '/../assets/qrcodes/' . $filename;
    QRcode::png($data, $filepath, QR_ECLEVEL_L, 4); // L = Low error correction
    return $filepath;
}
?>
