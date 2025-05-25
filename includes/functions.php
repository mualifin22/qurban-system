<?php
// Pastikan session sudah dimulai sebelum fungsi ini dipanggil
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk membersihkan input dari user
function sanitize_input($data) {
    global $conn; // Mengakses koneksi database
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

// Fungsi untuk menghasilkan QR Code
function generate_qr_code($data, $filename) {
    // Membutuhkan library phpqrcode
    require_once __DIR__ . '/../lib/phpqrcode/qrlib.php';
    $filepath = __DIR__ . '/../assets/qrcodes/' . $filename;
    QRcode::png($data, $filepath, QR_ECLEVEL_L, 4, 2); // L = Low error correction, 4 = pixel size, 2 = border
    return $filename; // Mengembalikan nama file saja
}

// Fungsi untuk cek akses berdasarkan role
function check_access($allowed_roles) {
    if (!is_logged_in()) {
        redirect('/qurban_app/login.php');
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        echo "<h2>Akses Ditolak!</h2>";
        echo "<p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>";
        echo "<p><a href=\"/qurban_app/dashboard.php\">Kembali ke Dashboard</a></p>";
        require_once __DIR__ . '/../includes/footer.php';
        exit();
    }
}
?>
