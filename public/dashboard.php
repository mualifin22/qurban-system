<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include '../includes/db.php';
include '../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    redirectToLogin();
}

$userRole = $_SESSION['role'];
$username = $_SESSION['username'];
$currentUser = getCurrentUser($conn);

// --- Ambil Data untuk Ringkasan Dashboard ---
// Total Hewan Qurban
$total_hewan_qurban = 0;
$result_hewan = $conn->query("SELECT COUNT(*) AS total FROM hewan_qurban");
if ($result_hewan) {
    $total_hewan_qurban = $result_hewan->fetch_assoc()['total'];
}

// Total Pemasukan Keuangan
$total_pemasukan_keuangan = 0;
$result_pemasukan = $conn->query("SELECT SUM(jumlah) AS total FROM keuangan WHERE jenis = 'pemasukan'");
if ($result_pemasukan) {
    $total_pemasukan_keuangan = $result_pemasukan->fetch_assoc()['total'];
}

// Total Pengeluaran Keuangan
$total_pengeluaran_keuangan = 0;
$result_pengeluaran = $conn->query("SELECT SUM(jumlah) AS total FROM keuangan WHERE jenis = 'pengeluaran'");
if ($result_pengeluaran) {
    $total_pengeluaran_keuangan = $result_pengeluaran->fetch_assoc()['total'];
}
$saldo_keuangan = $total_pemasukan_keuangan - $total_pengeluaran_keuangan;

// Total Warga Terdaftar
$total_warga = 0;
$result_warga = $conn->query("SELECT COUNT(*) AS total FROM warga");
if ($result_warga) {
    $total_warga = $result_warga->fetch_assoc()['total'];
}

// Total Penerima Daging (dari rencana pembagian)
$total_penerima_daging = 0;
$result_penerima_daging = $conn->query("SELECT COUNT(DISTINCT nik_warga) AS total FROM pembagian_daging");
if ($result_penerima_daging) {
    $total_penerima_daging = $result_penerima_daging->fetch_assoc()['total'];
}

// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../includes/header.php'; // HEADER BARU
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
</div>

<?php
// Tampilkan pesan sukses/error/info (yang kita simpan di $_SESSION)
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . ($_SESSION['message_type'] == 'error' ? 'danger' : ($_SESSION['message_type'] == 'info' ? 'info' : 'success')) . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['message']);
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h6>
    </div>
    <div class="card-body">
        <p class="mb-0">Anda login sebagai: <span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span>.</p>
        <p class="mb-0">Di sini Anda bisa melihat ringkasan data dan mengakses fitur-fitur utama sistem.</p>
    </div>
</div>

<?php if (isAdmin() || isPanitia()): ?>
<div class="row">

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Hewan Qurban</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_hewan_qurban); ?> Ekor</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-sheep fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Pemasukan Keuangan</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($total_pemasukan_keuangan); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Saldo Keuangan
                        </div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto">
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo formatRupiah($saldo_keuangan); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-balance-scale-left fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Penerima Daging (Rencana)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_penerima_daging); ?> Orang</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Akses Cepat</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <?php if (isAdmin()): ?>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-user-cog"></i> Admin Panel</h5>
                    <ul class="list-unstyled">
                        <li><a href="admin/users.php">Kelola Pengguna Sistem</a></li>
                        <li><a href="warga/index.php">Lihat & Kelola Semua Data Warga</a></li>
                        <li><a href="qurban/index.php">Lihat & Kelola Data Hewan Qurban</a></li>
                        <li><a href="keuangan/index.php">Lihat & Kelola Rekapan Keuangan</a></li>
                        <li><a href="qurban/pembagian.php">Atur & Pantau Pembagian Daging</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isPanitia()): ?>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-user-tie"></i> Tugas Panitia</h5>
                    <ul class="list-unstyled">
                        <li><a href="warga/index.php">Data Warga (Lihat)</a></li>
                        <li><a href="qurban/index.php">Data Hewan Qurban (Lihat)</a></li>
                        <li><a href="keuangan/index.php">Rekapan Keuangan (Lihat)</a></li>
                        <li><a href="qurban/pembagian.php">Kelola Status Pembagian Daging</a></li>
                        <li><a href="warga/qrcode.php">Cetak / Scan Kartu QR Code</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isBerqurban()): ?>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-hand-holding-heart"></i> Informasi Qurban Anda</h5>
                    <ul class="list-unstyled">
                        <li><a href="qurban/my_qurban.php">Lihat Detail Qurban yang Anda Ikuti</a></li>
                        <li><a href="warga/qrcode.php">Unduh Kartu Qurban Digital Anda</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isWarga()): ?>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-home"></i> Informasi Warga</h5>
                    <ul class="list-unstyled">
                        <li><a href="warga/qrcode.php">Unduh Kartu Pengambilan Daging Anda</a></li>
                        <li><a href="keuangan/index.php">Lihat Transaksi Keuangan (Jika Diizinkan)</a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>