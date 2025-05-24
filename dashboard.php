<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Cek apakah sudah login
if (!is_logged_in()) {
    redirect('login.php');
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<h2>Selamat Datang, <?php echo $_SESSION['username']; ?>!</h2>
<p>Anda login sebagai: <strong><?php echo ucfirst($_SESSION['role']); ?></strong></p>

<h3>Informasi Umum Qurban RT 001</h3>
<p>Total Warga: <?php
    $result = $conn->query("SELECT COUNT(*) AS total_warga FROM warga");
    echo $result->fetch_assoc()['total_warga'];
?></p>
<p>Jumlah Panitia: <?php
    $result = $conn->query("SELECT COUNT(*) AS total_panitia FROM warga WHERE is_panitia = TRUE");
    echo $result->fetch_assoc()['total_panitia'];
?></p>
<p>Jumlah Peserta Qurban: <?php
    $result = $conn->query("SELECT COUNT(*) AS total_berqurban FROM warga WHERE is_berqurban = TRUE");
    echo $result->fetch_assoc()['total_berqurban'];
?></p>

<?php
// Tampilkan informasi spesifik berdasarkan role
if (has_role('admin')) {
    echo "<h3>Akses Admin:</h3>";
    echo "<ul>";
    echo "<li>Manajemen Pengguna</li>";
    echo "<li>Manajemen Data Warga</li>";
    echo "<li>Manajemen Hewan Qurban</li>";
    echo "<li>Rekapitulasi Keuangan</li>";
    echo "<li>Manajemen Pembagian Daging</li>";
    echo "</ul>";
} elseif (has_role('panitia')) {
    echo "<h3>Akses Panitia:</h3>";
    echo "<ul>";
    echo "<li>Pencatatan Keuangan</li>";
    echo "<li>Pencatatan Pembagian Daging</li>";
    echo "<li>Verifikasi Pengambilan Daging</li>";
    echo "</ul>";
} elseif (has_role('berqurban')) {
    echo "<h3>Akses Peserta Qurban:</h3>";
    echo "<ul>";
    echo "<li>Lihat Detail Qurban Anda</li>";
    echo "<li>Download Kartu Pengambilan Daging</li>";
    echo "</ul>";
} elseif (has_role('warga')) {
    echo "<h3>Akses Warga:</h3>";
    echo "<ul>";
    echo "<li>Download Kartu Pengambilan Daging</li>";
    echo "</ul>";
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
