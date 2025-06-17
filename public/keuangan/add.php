<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================================
// Bagian Pemrosesan Logika PHP (Struktur disesuaikan dengan referensi)
// =========================================================================

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hanya Admin yang bisa mengakses halaman ini (Sama seperti referensi)
if (!isAdmin()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php"); // Sesuaikan jika perlu
    exit();
}

// Inisialisasi variabel
$jenis = '';
$keterangan = '';
$jumlah = '';
$tanggal = date('Y-m-d');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitasi data dari form
    $jenis = sanitizeInput($_POST['jenis'] ?? '');
    $keterangan = sanitizeInput($_POST['keterangan'] ?? '');
    $jumlah = sanitizeInput($_POST['jumlah'] ?? '');
    $tanggal = sanitizeInput($_POST['tanggal'] ?? '');

    // Validasi
    if (empty($jenis) || !in_array($jenis, ['pemasukan', 'pengeluaran'])) {
        $errors[] = "Jenis transaksi tidak valid.";
    }
    if (empty($keterangan)) {
        $errors[] = "Keterangan transaksi wajib diisi.";
    }
    // Mengizinkan angka 0, tapi tidak negatif. Sesuaikan jika perlu.
    if (!is_numeric($jumlah) || $jumlah < 0) {
        $errors[] = "Jumlah harus berupa angka non-negatif.";
    }
    if (empty($tanggal)) {
        $errors[] = "Tanggal transaksi wajib diisi.";
    }

    // Jika tidak ada error, proses ke database
    if (empty($errors)) {
        // Menggunakan transaksi database seperti referensi
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
            // Tipe data: string, string, double, string
            $stmt->bind_param("ssds", $jenis, $keterangan, $jumlah, $tanggal);
            $stmt->execute();

            if ($stmt->error) {
                // Jika ada error dari statement, lempar exception untuk ditangkap
                throw new mysqli_sql_exception("Error saat menyimpan transaksi: " . $stmt->error);
            }
            $stmt->close();

            // Jika semua berhasil, commit transaksi
            $conn->commit();

            $_SESSION['message'] = "Transaksi keuangan berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php"); // Redirect ke halaman daftar keuangan
            exit();

        } catch (mysqli_sql_exception $e) {
            // Jika terjadi error, batalkan semua perubahan
            $conn->rollback();
            
            // Simpan pesan error dan data form ke session untuk ditampilkan setelah redirect
            $_SESSION['message'] = "Gagal menambahkan transaksi: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: add_keuangan.php"); // Redirect kembali ke form tambah
            exit();
        }
    } else {
        // Jika ada error validasi, simpan error dan data form ke session
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add_keuangan.php"); // Redirect kembali ke form tambah
        exit();
    }
} else {
    // Jika bukan request POST, cek apakah ada data form/error dari redirect sebelumnya
    if (isset($_SESSION['form_data'])) {
        $jenis = $_SESSION['form_data']['jenis'] ?? '';
        $keterangan = $_SESSION['form_data']['keterangan'] ?? '';
        $jumlah = $_SESSION['form_data']['jumlah'] ?? '';
        $tanggal = $_SESSION['form_data']['tanggal'] ?? date('Y-m-d');
        unset($_SESSION['form_data']);
    }
    if (isset($_SESSION['errors'])) {
        $errors = $_SESSION['errors'];
        unset($_SESSION['errors']);
    }
}

// =========================================================================
// Bagian Tampilan HTML (Struktur disesuaikan dengan referensi)
// =========================================================================
?>
<?php include '../../includes/header.php'; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Tambah Transaksi Keuangan</h1>
</div>

<?php
// Tampilkan pesan sukses/error/info dari $_SESSION (Sesuai Referensi)
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . ($_SESSION['message_type'] == 'error' ? 'danger' : 'success') . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['message']);
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Tampilkan pesan error validasi dari array $errors (Sesuai Referensi)
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
        <h6 class="m-0 font-weight-bold text-primary">Form Transaksi Keuangan</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST">
            <div class="form-group">
                <label for="jenis">Jenis Transaksi:</label>
                <select class="form-control" id="jenis" name="jenis" required>
                    <option value="">-- Pilih Jenis --</option>
                    <option value="pemasukan" <?php echo ($jenis == 'pemasukan') ? 'selected' : ''; ?>>Pemasukan</option>
                    <option value="pengeluaran" <?php echo ($jenis == 'pengeluaran') ? 'selected' : ''; ?>>Pengeluaran</option>
                </select>
            </div>
            <div class="form-group">
                <label for="keterangan">Keterangan:</label>
                 <textarea class="form-control" id="keterangan" name="keterangan" required rows="3"><?php echo htmlspecialchars($keterangan); ?></textarea>
            </div>
            <div class="form-group">
                <label for="jumlah">Jumlah (Rp):</label>
                 <input type="number" class="form-control" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($jumlah); ?>" step="1" placeholder="Contoh: 50000" required>
            </div>
            <div class="form-group">
                <label for="tanggal">Tanggal:</label>
                 <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>