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

<h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
<p>Anda login sebagai: <strong><?php echo ucfirst($_SESSION['role']); ?></strong></p>

<h3>Informasi Umum Qurban RT 001</h3>
<?php
$total_warga = $conn->query("SELECT COUNT(*) AS total FROM warga")->fetch_assoc()['total'];
$total_panitia = $conn->query("SELECT COUNT(*) AS total FROM warga WHERE is_panitia = TRUE")->fetch_assoc()['total'];
$total_berqurban = $conn->query("SELECT COUNT(*) AS total FROM warga WHERE is_berqurban = TRUE")->fetch_assoc()['total'];
$total_hewan_kambing = $conn->query("SELECT COUNT(*) AS total FROM hewan_qurban WHERE jenis = 'kambing'")->fetch_assoc()['total'];
$total_hewan_sapi = $conn->query("SELECT COUNT(*) AS total FROM hewan_qurban WHERE jenis = 'sapi'")->fetch_assoc()['total'];

$total_daging_kambing = $conn->query("SELECT SUM(total_daging_kg) AS total FROM hewan_qurban WHERE jenis = 'kambing'")->fetch_assoc()['total'] ?? 0;
$total_daging_sapi = $conn->query("SELECT SUM(total_daging_kg) AS total FROM hewan_qurban WHERE jenis = 'sapi'")->fetch_assoc()['total'] ?? 0;
$total_daging_qurban = $total_daging_kambing + $total_daging_sapi;

$total_daging_terdistribusi = $conn->query("SELECT SUM(jumlah_kg) AS total FROM pembagian_daging WHERE status_pengambilan = 'sudah diambil'")->fetch_assoc()['total'] ?? 0;
$sisa_daging = $total_daging_qurban - $total_daging_terdistribusi;
?>

<p>Total Warga Terdaftar: <strong><?php echo $total_warga; ?></strong></p>
<p>Jumlah Panitia Aktif: <strong><?php echo $total_panitia; ?></strong></p>
<p>Jumlah Peserta Qurban: <strong><?php echo $total_berqurban; ?></strong></p>
<br>
<p>Jumlah Kambing Qurban: <strong><?php echo $total_hewan_kambing; ?> ekor</strong></p>
<p>Jumlah Sapi Qurban: <strong><?php echo $total_hewan_sapi; ?> ekor</strong></p>
<p>Estimasi Total Daging Qurban: <strong><?php echo $total_daging_qurban; ?> kg</strong></p>
<p>Daging Sudah Terdistribusi: <strong><?php echo $total_daging_terdistribusi; ?> kg</strong></p>
<p>Sisa Daging Belum Terdistribusi: <strong><?php echo $sisa_daging; ?> kg</strong></p>


<?php
// Tampilkan informasi spesifik berdasarkan role atau tautan cepat
if (has_role('admin')) {
    echo "<h3>Tindakan Cepat Admin:</h3>";
    echo "<ul>";
    echo "<li><a href=\"/qurban_app/admin/keuangan.php\">Lihat/Kelola Rekap Keuangan</a></li>";
    echo "<li><a href=\"/qurban_app/admin/pembagian.php\">Kelola Pembagian Daging</a></li>";
    echo "<li><a href=\"/qurban_app/admin/warga.php\">Kelola Data Warga</a></li>";
    echo "</ul>";
} elseif (has_role('panitia')) {
    echo "<h3>Tindakan Cepat Panitia:</h3>";
    echo "<ul>";
    echo "<li><a href=\"/qurban_app/panitia/keuangan.php\">Catat Transaksi Keuangan</a></li>";
    echo "<li><a href=\"/qurban_app/panitia/pembagian.php\">Catat Pembagian Daging</a></li>";
    echo "<li><a href=\"/qurban_app/panitia/qr_scan.php\">Verifikasi Pengambilan Daging</a></li>";
    echo "</ul>";
} elseif (has_role('berqurban')) {
    echo "<h3>Tindakan Cepat Peserta Qurban:</h3>";
    echo "<ul>";
    echo "<li><a href=\"/qurban_app/berqurban/my_qurban.php\">Lihat Detail Qurban Saya</a></li>";
    echo "<li><a href=\"/qurban_app/warga/my_card.php\">Download Kartu Pengambilan Daging</a></li>";
    echo "</ul>";
} elseif (has_role('warga')) {
    echo "<h3>Tindakan Cepat Warga:</h3>";
    echo "<ul>";
    echo "<li><a href=\"/qurban_app/warga/my_card.php\">Download Kartu Pengambilan Daging</a></li>";
    echo "</ul>";
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
