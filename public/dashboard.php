<?php
// public/dashboard.php
session_start(); // Pastikan session dimulai di setiap halaman yang membutuhkan session

include '../includes/db.php';
include '../includes/functions.php';

// Cek apakah user sudah login, jika belum redirect ke halaman login
if (!isLoggedIn()) {
    redirectToLogin();
}

$userRole = $_SESSION['role'];
$username = $_SESSION['username'];

// Ambil data user lengkap jika diperlukan
$currentUser = getCurrentUser($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Qurban RT 001</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .header nav ul li {
            display: inline;
            margin-left: 20px;
        }
        .header nav ul li a {
            color: white;
            text-decoration: none;
        }
        .header nav ul li a:hover {
            text-decoration: underline;
        }
        .container {
            padding: 20px;
        }
        .welcome-message {
            background-color: #e0ffe0;
            border: 1px solid #a0ffa0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #333;
        }
        .role-info {
            font-weight: bold;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistem Qurban RT 001</h1>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/users.php">Manajemen User</a></li>
                <?php endif; ?>
                <?php if (isAdmin() || isPanitia()): ?>
                    <li><a href="warga/index.php">Data Warga</a></li>
                    <li><a href="qurban/index.php">Data Qurban</a></li>
                    <li><a href="keuangan/index.php">Keuangan</a></li>
                    <li><a href="qurban/pembagian.php">Pembagian Daging</a></li>
                <?php endif; ?>
                <?php if (isBerqurban()): ?>
                    <li><a href="qurban/my_qurban.php">Qurban Saya</a></li>
                <?php endif; ?>
                <?php if (isWarga() || isBerqurban() || isPanitia()): ?>
                    <li><a href="warga/qrcode.php">Kartu Qurban</a></li>
                <?php endif; ?>
                <li><a href="auth.php?logout=true">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="welcome-message">
            <h2>Selamat datang, <?php echo htmlspecialchars($username); ?>!</h2>
            <p>Anda login sebagai: <span class="role-info"><?php echo htmlspecialchars($userRole); ?></span>.</p>
        </div>

        <h3>Informasi Umum</h3>
        <p>Ini adalah halaman dashboard. Konten di sini akan bervariasi sesuai dengan peran Anda.</p>

        <?php if (isAdmin()): ?>
            <p>Sebagai Admin, Anda memiliki akses penuh ke semua fitur sistem.</p>
            <ul>
                <li><a href="admin/users.php">Kelola Pengguna</a></li>
                <li><a href="warga/index.php">Kelola Data Warga</a></li>
                <li><a href="qurban/index.php">Kelola Data Hewan Qurban</a></li>
                <li><a href="keuangan/index.php">Kelola Keuangan</a></li>
                <li><a href="qurban/pembagian.php">Atur Pembagian Daging</a></li>
            </ul>
        <?php elseif (isPanitia()): ?>
            <p>Sebagai Panitia, Anda bertanggung jawab untuk mengelola data qurban, keuangan, dan proses pembagian daging.</p>
            <ul>
                <li><a href="warga/index.php">Lihat Data Warga</a></li>
                <li><a href="qurban/index.php">Lihat Data Hewan Qurban</a></li>
                <li><a href="keuangan/index.php">Lihat Rekapan Keuangan</a></li>
                <li><a href="qurban/pembagian.php">Kelola Pembagian Daging</a></li>
                <li><a href="warga/qrcode.php">Cetak/Scan QR Code Warga</a></li>
            </ul>
        <?php elseif (isBerqurban()): ?>
            <p>Terima kasih telah berpartisipasi dalam qurban. Anda dapat melihat detail qurban Anda di sini.</p>
            <ul>
                <li><a href="qurban/my_qurban.php">Lihat Detail Qurban Saya</a></li>
                <li><a href="warga/qrcode.php">Unduh Kartu Qurban Digital</a></li>
            </ul>
        <?php elseif (isWarga()): ?>
            <p>Selamat datang warga RT 001. Anda dapat mengunduh kartu pengambilan daging di sini.</p>
            <ul>
                <li><a href="warga/qrcode.php">Unduh Kartu Pengambilan Daging</a></li>
            </ul>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Sistem Qurban RT 001. Dibuat oleh Mahasiswa Informatika.</p>
    </div>
</body>
</html>