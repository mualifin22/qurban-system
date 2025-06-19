<?php
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectToLogin() {
    header("Location: /sistem_qurban/public/index.php"); 
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

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function formatRupiah($amount) {
    if ($amount === null || !is_numeric($amount)) {
        $amount = 0; // Ubah NULL atau non-numerik menjadi 0
    }
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}


function getCurrentUser($conn) {
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
