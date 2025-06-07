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

$id_transaksi = '';
if (isset($_GET['id'])) {
    $id_transaksi = sanitizeInput($_GET['id']);
} elseif (isset($_POST['id'])) {
    $id_transaksi = sanitizeInput($_POST['id']);
} else {
    $_SESSION['message'] = "ID transaksi tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$jenis = '';
$keterangan = '';
$jumlah = '';
$tanggal = date('Y-m-d');
$errors = [];

// Ambil data transaksi yang akan diedit
$stmt_get = $conn->prepare("SELECT id, jenis, keterangan, jumlah, tanggal, id_hewan_qurban FROM keuangan WHERE id = ?");
$stmt_get->bind_param("i", $id_transaksi);
$stmt_get->execute();
$result_get = $stmt_get->get_result();

if ($result_get->num_rows > 0) {
    $transaksi_data = $result_get->fetch_assoc();
    // Jika transaksi ini terkait dengan hewan qurban, mungkin tidak boleh diedit manual
    // atau hanya sebagian field yang boleh diedit.
    // Untuk saat ini, kita akan biarkan semua bisa diedit, tapi tambahkan validasi.
    if (!empty($transaksi_data['id_hewan_qurban'])) {
        $_SESSION['message'] = "Transaksi ini terkait dengan pembelian/iuran hewan qurban dan tidak dapat diubah secara manual di sini. Silakan ubah melalui menu Data Qurban jika diperlukan.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }

    $jenis = $transaksi_data['jenis'];
    $keterangan = $transaksi_data['keterangan'];
    $jumlah = $transaksi_data['jumlah'];
    $tanggal = $transaksi_data['tanggal'];
} else {
    $_SESSION['message'] = "Data transaksi tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$stmt_get->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_transaksi = sanitizeInput($_POST['id']); // Pastikan ID ada
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
        $stmt = $conn->prepare("UPDATE keuangan SET jenis = ?, keterangan = ?, jumlah = ?, tanggal = ? WHERE id = ?");
        // Tipe data: string, string, double, string, integer
        $stmt->bind_param("ssdsi", $jenis, $keterangan, $jumlah, $tanggal, $id_transaksi);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Transaksi keuangan berhasil diperbarui.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Gagal memperbarui transaksi keuangan: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <h2>Edit Transaksi Keuangan</h2>
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
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_transaksi); ?>">
        <div class="form-group">
            <label for="jenis">Jenis Transaksi:</label>
            <select id="jenis" name="jenis" required>
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
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>