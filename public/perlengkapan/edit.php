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

$id_perlengkapan = ''; // ID perlengkapan yang akan diedit (dari GET atau POST)
$nama_barang = '';
$jumlah = '';
$harga_satuan = '';
$tanggal_beli = '';
$errors = [];

// Ambil ID perlengkapan dari URL (GET) jika ini request awal
if (isset($_GET['id'])) {
    $id_perlengkapan = sanitizeInput($_GET['id']);
}
// Jika ini adalah POST request (form disubmit), ambil ID dari hidden field
elseif (isset($_POST['id'])) {
    $id_perlengkapan = sanitizeInput($_POST['id']);
}
// Jika tidak ada ID sama sekali, redirect
else {
    $_SESSION['message'] = "ID perlengkapan tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Proses form jika ada data yang dikirim (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_barang = sanitizeInput($_POST['nama_barang'] ?? '');
    $jumlah = sanitizeInput($_POST['jumlah'] ?? '');
    $harga_satuan = sanitizeInput($_POST['harga_satuan'] ?? '');
    $tanggal_beli = sanitizeInput($_POST['tanggal_beli'] ?? '');

    // Validasi
    if (empty($id_perlengkapan) || !is_numeric($id_perlengkapan)) { $errors[] = "ID perlengkapan tidak valid."; }
    if (empty($nama_barang)) { $errors[] = "Nama barang wajib diisi."; }
    if (!is_numeric($jumlah) || $jumlah <= 0) { $errors[] = "Jumlah harus angka positif."; }
    if (!is_numeric($harga_satuan) || $harga_satuan < 0) { $errors[] = "Harga satuan harus angka positif atau nol."; }
    if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }

    // Jika ada error validasi, simpan data POST ke session dan tampilkan di form
    if (!empty($errors)) {
        $_SESSION['form_data'] = $_POST;
        header("Location: edit.php?id=" . urlencode($id_perlengkapan));
        exit();
    }

    // --- Proses Update (jika validasi berhasil) ---
    $conn->begin_transaction();
    $old_perlengkapan_data = []; // Untuk rollback data perlengkapan
    $old_keuangan_data = []; // Untuk rollback data keuangan

    try {
        // 1. Ambil data perlengkapan lama untuk rollback
        $stmt_get_old_perlengkapan = $conn->prepare("SELECT nama_barang, jumlah, harga_satuan, tanggal_beli FROM perlengkapan WHERE id = ?");
        $stmt_get_old_perlengkapan->bind_param("i", $id_perlengkapan);
        $stmt_get_old_perlengkapan->execute();
        $old_perlengkapan_data = $stmt_get_old_perlengkapan->get_result()->fetch_assoc();
        $stmt_get_old_perlengkapan->close();

        // 2. Update data perlengkapan di tabel `perlengkapan`
        $stmt_update_perlengkapan = $conn->prepare("UPDATE perlengkapan SET nama_barang = ?, jumlah = ?, harga_satuan = ?, tanggal_beli = ? WHERE id = ?");
        $stmt_update_perlengkapan->bind_param("sidsi", $nama_barang, $jumlah, $harga_satuan, $tanggal_beli, $id_perlengkapan);
        $stmt_update_perlengkapan->execute();
        if ($stmt_update_perlengkapan->error) {
            throw new mysqli_sql_exception("Error saat memperbarui perlengkapan: " . $stmt_update_perlengkapan->error);
        }
        $stmt_update_perlengkapan->close();

        // 3. Update transaksi keuangan terkait perlengkapan ini
        // Ambil ID transaksi keuangan yang terkait dengan perlengkapan ini
        $stmt_get_keuangan_id = $conn->prepare("SELECT id, jenis, keterangan, jumlah, tanggal FROM keuangan WHERE keterangan LIKE CONCAT('Pembelian Perlengkapan: ', ?, '%') ORDER BY id DESC LIMIT 1");
        $stmt_get_keuangan_id->bind_param("s", $old_perlengkapan_data['nama_barang']); // Menggunakan nama barang lama untuk mencari
        $stmt_get_keuangan_id->execute();
        $keuangan_data = $stmt_get_keuangan_id->get_result()->fetch_assoc();
        $stmt_get_keuangan_id->close();

        if ($keuangan_data) {
            $old_keuangan_data = $keuangan_data; // Simpan untuk rollback
            $total_biaya_item_baru = $jumlah * $harga_satuan;
            $keterangan_keuangan_baru = "Pembelian Perlengkapan: " . $nama_barang . " (ID: " . $id_perlengkapan . ") - Diperbarui";
            $stmt_update_keuangan = $conn->prepare("UPDATE keuangan SET keterangan = ?, jumlah = ?, tanggal = ? WHERE id = ?");
            $stmt_update_keuangan->bind_param("sdsi", $keterangan_keuangan_baru, $total_biaya_item_baru, $tanggal_beli, $keuangan_data['id']);
            $stmt_update_keuangan->execute();
            if ($stmt_update_keuangan->error) {
                throw new mysqli_sql_exception("Error saat memperbarui pengeluaran di keuangan: " . $stmt_update_keuangan->error);
            }
            $stmt_update_keuangan->close();
        } else {
            // Jika tidak ada transaksi keuangan terkait sebelumnya, buat yang baru
            $total_biaya_item_baru = $jumlah * $harga_satuan;
            $keterangan_keuangan_baru = "Pembelian Perlengkapan: " . $nama_barang . " (ID: " . $id_perlengkapan . ")";
            $jenis_keuangan = 'pengeluaran';
            $stmt_insert_keuangan = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
            $stmt_insert_keuangan->bind_param("ssds", $jenis_keuangan, $keterangan_keuangan_baru, $total_biaya_item_baru, $tanggal_beli);
            $stmt_insert_keuangan->execute();
            if ($stmt_insert_keuangan->error) {
                throw new mysqli_sql_exception("Error saat mencatat pengeluaran perlengkapan baru di keuangan: " . $stmt_insert_keuangan->error);
            }
            $stmt_insert_keuangan->close();
        }

        $conn->commit();
        $_SESSION['message'] = "Perlengkapan '" . htmlspecialchars($nama_barang) . "' berhasil diperbarui.";
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        // Rollback data perlengkapan
        if (!empty($old_perlengkapan_data)) {
            $stmt_rollback_perlengkapan = $conn->prepare("UPDATE perlengkapan SET nama_barang = ?, jumlah = ?, harga_satuan = ?, tanggal_beli = ? WHERE id = ?");
            $stmt_rollback_perlengkapan->bind_param("sidsi",
                $old_perlengkapan_data['nama_barang'],
                $old_perlengkapan_data['jumlah'],
                $old_perlengkapan_data['harga_satuan'],
                $old_perlengkapan_data['tanggal_beli'],
                $id_perlengkapan
            );
            $stmt_rollback_perlengkapan->execute();
            $stmt_rollback_perlengkapan->close();
        }
        // Rollback data keuangan jika ada
        if (!empty($old_keuangan_data)) {
            $stmt_rollback_keuangan = $conn->prepare("UPDATE keuangan SET jenis = ?, keterangan = ?, jumlah = ?, tanggal = ? WHERE id = ?");
            $stmt_rollback_keuangan->bind_param("ssdsi",
                $old_keuangan_data['jenis'],
                $old_keuangan_data['keterangan'],
                $old_keuangan_data['jumlah'],
                $old_keuangan_data['tanggal'],
                $old_keuangan_data['id']
            );
            $stmt_rollback_keuangan->execute();
            $stmt_rollback_keuangan->close();
        }

        $errors[] = "Gagal memperbarui perlengkapan: " . $e->getMessage();
        $_SESSION['message'] = "Gagal memperbarui perlengkapan: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        $_SESSION['form_data'] = $_POST;
        header("Location: edit.php?id=" . urlencode($id_perlengkapan));
        exit();
    }
}

// =========================================================================
// Bagian Pengambilan Data untuk Tampilan Form (Jika bukan POST atau ada error POST)
// =========================================================================

// Jika ini GET request atau POST request dengan error, ambil data dari DB atau session
if (!isset($_POST['id']) || !empty($errors)) {
    // Ambil data perlengkapan dari database berdasarkan ID
    $stmt_get_data = $conn->prepare("SELECT id, nama_barang, jumlah, harga_satuan, tanggal_beli FROM perlengkapan WHERE id = ?");
    $stmt_get_data->bind_param("i", $id_perlengkapan);
    $stmt_get_data->execute();
    $result_get_data = $stmt_get_data->get_result();

    if ($result_get_data->num_rows > 0) {
        $perlengkapan_data = $result_get_data->fetch_assoc();
        $nama_barang = $perlengkapan_data['nama_barang'];
        $jumlah = $perlengkapan_data['jumlah'];
        $harga_satuan = $perlengkapan_data['harga_satuan'];
        $tanggal_beli = $perlengkapan_data['tanggal_beli'];
    } else {
        $_SESSION['message'] = "Data perlengkapan tidak ditemukan.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
    $stmt_get_data->close();
}

// Jika ada data form dari session (setelah redirect karena error validasi), gunakan data itu
if (isset($_SESSION['form_data'])) {
    $nama_barang = $_SESSION['form_data']['nama_barang'] ?? $nama_barang;
    $jumlah = $_SESSION['form_data']['jumlah'] ?? $jumlah;
    $harga_satuan = $_SESSION['form_data']['harga_satuan'] ?? $harga_satuan;
    $tanggal_beli = $_SESSION['form_data']['tanggal_beli'] ?? $tanggal_beli;
    unset($_SESSION['form_data']);
}
// Ambil pesan error dari session jika ada
if (isset($_SESSION['errors'])) {
    $errors = array_merge($errors, $_SESSION['errors']);
    unset($_SESSION['errors']);
}
?>

<?php include '../../includes/header.php'; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Perlengkapan</h1>
</div>

<?php
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
        <h6 class="m-0 font-weight-bold text-primary">Form Edit Perlengkapan</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_perlengkapan); ?>">
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
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
