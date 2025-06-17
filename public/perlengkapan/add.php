<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================================
// Bagian Pemrosesan Logika PHP (Harus di atas, sebelum output HTML dimulai)
// =========================================================================

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hanya Admin atau Panitia yang bisa mengakses halaman ini
if (!isAdmin() && !isPanitia()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

$nama_barang = '';
$jumlah = '';
$harga_satuan = '';
$tanggal_beli = date('Y-m-d');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_barang = sanitizeInput($_POST['nama_barang'] ?? '');
    $jumlah = sanitizeInput($_POST['jumlah'] ?? '');
    $harga_satuan = sanitizeInput($_POST['harga_satuan'] ?? '');
    $tanggal_beli = sanitizeInput($_POST['tanggal_beli'] ?? '');

    // Validasi
    if (empty($nama_barang)) { $errors[] = "Nama barang wajib diisi."; }
    if (!is_numeric($jumlah) || $jumlah <= 0) { $errors[] = "Jumlah harus angka positif."; }
    if (!is_numeric($harga_satuan) || $harga_satuan < 0) { $errors[] = "Harga satuan harus angka positif atau nol."; }
    if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            $stmt_insert_perlengkapan = $conn->prepare("INSERT INTO perlengkapan (nama_barang, jumlah, harga_satuan, tanggal_beli) VALUES (?, ?, ?, ?)");
            $stmt_insert_perlengkapan->bind_param("sids", $nama_barang, $jumlah, $harga_satuan, $tanggal_beli);
            $stmt_insert_perlengkapan->execute();

            if ($stmt_insert_perlengkapan->error) {
                throw new mysqli_sql_exception("Error saat menambahkan perlengkapan: " . $stmt_insert_perlengkapan->error);
            }
            $id_perlengkapan = $conn->insert_id;
            $stmt_insert_perlengkapan->close();

            // Tambahkan transaksi pengeluaran ke tabel keuangan
            $total_biaya_item = $jumlah * $harga_satuan;
            $keterangan_keuangan = "Pembelian Perlengkapan: " . $nama_barang . " (ID: " . $id_perlengkapan . ")";
            $jenis_keuangan = 'pengeluaran';
            $stmt_keuangan = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
            $stmt_keuangan->bind_param("ssds", $jenis_keuangan, $keterangan_keuangan, $total_biaya_item, $tanggal_beli);
            $stmt_keuangan->execute();
            if ($stmt_keuangan->error) {
                throw new mysqli_sql_exception("Error saat mencatat pengeluaran perlengkapan: " . $stmt_keuangan->error);
            }
            $stmt_keuangan->close();

            $conn->commit();
            $_SESSION['message'] = "Perlengkapan '" . htmlspecialchars($nama_barang) . "' berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $errors[] = "Gagal menambahkan perlengkapan: " . $e->getMessage();
            $_SESSION['message'] = "Gagal menambahkan perlengkapan: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: add.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add.php");
        exit();
    }
} else {
    // Jika bukan POST, ambil data form dari session jika ada
    if (isset($_SESSION['form_data'])) {
        $nama_barang = $_SESSION['form_data']['nama_barang'] ?? '';
        $jumlah = $_SESSION['form_data']['jumlah'] ?? '';
        $harga_satuan = $_SESSION['form_data']['harga_satuan'] ?? '';
        $tanggal_beli = $_SESSION['form_data']['tanggal_beli'] ?? date('Y-m-d');
        unset($_SESSION['form_data']);
    }
    if (isset($_SESSION['errors'])) {
        $errors = $_SESSION['errors'];
        unset($_SESSION['errors']);
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Tambah Perlengkapan Baru</h1>
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
// Tampilkan pesan error validasi (dari $errors array)
if (!empty($errors)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<strong>Error!</strong> Mohon perbaiki kesalahan berikut:<ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
}
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Tambah Perlengkapan</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST">
            <div class="form-group">
                <label for="nama_barang">Nama Barang:</label>
                <input type="text" class="form-control" id="nama_barang" name="nama_barang" value="<?php echo htmlspecialchars($nama_barang); ?>" required>
            </div>
            <div class="form-group">
                <label for="jumlah">Jumlah:</label>
                <input type="number" class="form-control" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($jumlah); ?>" required min="1">
            </div>
            <div class="form-group">
                <label for="harga_satuan">Harga Satuan:</label>
                <input type="number" class="form-control" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($harga_satuan); ?>" step="100" required min="0">
            </div>
            <div class="form-group">
                <label for="tanggal_beli">Tanggal Beli:</label>
                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
