<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$jenis = '';
$keterangan = '';
$jumlah = '';
$tanggal = date('Y-m-d');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = sanitizeInput($_POST['jenis']);
    $keterangan = sanitizeInput($_POST['keterangan']);
    $jumlah = sanitizeInput($_POST['jumlah']);
    $tanggal = sanitizeInput($_POST['tanggal']);

    // Validasi
    if (empty($jenis) || !in_array($jenis, ['pemasukan', 'pengeluaran'])) {
        $errors[] = "Jenis transaksi tidak valid.";
    }
    if (empty($keterangan)) {
        $errors[] = "Keterangan transaksi wajib diisi.";
    }
    if (!is_numeric($jumlah) || $jumlah <= 0) {
        $errors[] = "Jumlah harus angka positif.";
    }
    if (empty($tanggal)) {
        $errors[] = "Tanggal transaksi wajib diisi.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
        // Tipe data: string, string, double, string
        $stmt->bind_param("ssds", $jenis, $keterangan, $jumlah, $tanggal);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Transaksi keuangan berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php"); // Redirect ke halaman daftar keuangan
            exit();
        } else {
            $errors[] = "Gagal menambahkan transaksi keuangan: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <h2>Tambah Transaksi Keuangan Manual</h2>
    <?php
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    ?>
    <form action="" method="POST">
        <div class="form-group">
            <label for="jenis">Jenis Transaksi:</label>
            <select id="jenis" name="jenis" required>
                <option value="">-- Pilih Jenis --</option>
                <option value="pemasukan" <?php echo ($jenis == 'pemasukan') ? 'selected' : ''; ?>>Pemasukan</option>
                <option value="pengeluaran" <?php echo ($jenis == 'pengeluaran') ? 'selected' : ''; ?>>Pengeluaran</option>
            </select>
        </div>
        <div class="form-group">
            <label for="keterangan">Keterangan:</label>
            <textarea id="keterangan" name="keterangan" required><?php echo htmlspecialchars($keterangan); ?></textarea>
        </div>
        <div class="form-group">
            <label for="jumlah">Jumlah:</label>
            <input type="number" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($jumlah); ?>" step="1000" required>
        </div>
        <div class="form-group">
            <label for="tanggal">Tanggal:</label>
            <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>