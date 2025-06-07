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

if (!isLoggedIn()) {
    redirectToLogin();
}

$nik_to_edit = '';
// Dapatkan NIK dari URL (GET). Jika tidak ada, redirect.
if (isset($_GET['nik'])) {
    $nik_to_edit = sanitizeInput($_GET['nik']);
} else {
    $_SESSION['message'] = "NIK warga tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$nik = $nama = $alamat = $no_hp = $status_qurban = '';
$status_panitia = 0; // Data dari DB
$errors = []; // Untuk menampilkan error jika ada redirect balik dari update_warga.php

// Ambil data warga yang akan diedit
$stmt_get = $conn->prepare("SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga WHERE nik = ?");
$stmt_get->bind_param("s", $nik_to_edit);
$stmt_get->execute();
$result_get = $stmt_get->get_result();

if ($result_get->num_rows > 0) {
    $warga_data = $result_get->fetch_assoc();
    $nik = $warga_data['nik'];
    $nama = $warga_data['nama'];
    $alamat = $warga_data['alamat'];
    $no_hp = $warga_data['no_hp'];
    $status_qurban = $warga_data['status_qurban'];
    $status_panitia = $warga_data['status_panitia'];
} else {
    $_SESSION['message'] = "Data warga dengan NIK tersebut tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$stmt_get->close();

// Cek keterikatan qurban untuk menentukan apakah status_qurban bisa diubah
$can_change_status_qurban = true;
$keterangan_keterikatan = '';

// Cek apakah NIK ini masih menjadi peserta di hewan qurban lain (kambing)
$stmt_check_kambing_qurban = $conn->prepare("SELECT COUNT(*) FROM hewan_qurban WHERE nik_peserta_tunggal = ?");
$stmt_check_kambing_qurban->bind_param("s", $nik);
$stmt_check_kambing_qurban->execute();
$is_kambing_participant = ($stmt_check_kambing_qurban->get_result()->fetch_row()[0] > 0);
$stmt_check_kambing_qurban->close();

// Cek apakah NIK ini masih menjadi peserta di hewan qurban lain (sapi)
$stmt_check_sapi_qurban = $conn->prepare("SELECT COUNT(*) FROM peserta_sapi WHERE nik_warga = ?");
$stmt_check_sapi_qurban->bind_param("s", $nik);
$stmt_check_sapi_qurban->execute();
$is_sapi_participant = ($stmt_check_sapi_qurban->get_result()->fetch_row()[0] > 0);
$stmt_check_sapi_qurban->close();

if ($is_kambing_participant || $is_sapi_participant) {
    $can_change_status_qurban = false;
    $keterangan_keterikatan = "Warga ini terdaftar sebagai peserta Qurban. Status Qurban tidak dapat diubah secara manual.";
}


// Ambil pesan error/sukses dari session jika ada (dari update_warga.php)
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['form_data'])) {
    // Jika ada data form yang dikirim balik (misal karena error), gunakan data itu
    $nik = $_SESSION['form_data']['nik'] ?? '';
    $nama = $_SESSION['form_data']['nama'] ?? '';
    $alamat = $_SESSION['form_data']['alamat'] ?? '';
    $no_hp = $_SESSION['form_data']['no_hp'] ?? '';
    $status_qurban_form_value = $_SESSION['form_data']['status_qurban'] ?? $status_qurban; // Ambil dari form_data atau dari DB
    unset($_SESSION['form_data']);
} else {
    $status_qurban_form_value = $status_qurban; // Jika tidak ada form_data, gunakan data dari DB
}

?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <h2>Edit Data Warga</h2>
    <?php
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <form action="update_warga.php" method="POST">
        <input type="hidden" name="nik_original" value="<?php echo htmlspecialchars($nik_to_edit); ?>">
        <div class="form-group">
            <label for="nik">NIK:</label>
            <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($nik); ?>" required>
        </div>
        <div class="form-group">
            <label for="nama">Nama:</label>
            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required>
        </div>
        <div class="form-group">
            <label for="alamat">Alamat:</label>
            <textarea id="alamat" name="alamat"><?php echo htmlspecialchars($alamat); ?></textarea>
        </div>
        <div class="form-group">
            <label for="no_hp">No. HP:</label>
            <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>">
        </div>
        <!-- <div class="form-group">
            <label for="status_qurban">Status Qurban:</label>
            <select id="status_qurban" name="status_qurban" <?php echo $can_change_status_qurban ? '' : 'disabled'; ?>>
                <option value="penerima" <?php echo ($status_qurban_form_value == 'penerima') ? 'selected' : ''; ?>>Penerima Daging</option>
                <option value="tidak_ikut" <?php echo ($status_qurban_form_value == 'tidak_ikut') ? 'selected' : ''; ?>>Tidak Ikut</option>
            </select>
            <?php if (!$can_change_status_qurban): ?>
                <input type="hidden" name="status_qurban" value="<?php echo htmlspecialchars($status_qurban); ?>">
                <small style="color: orange; display: block; margin-top: 5px;"><?php echo htmlspecialchars($keterangan_keterikatan); ?> Status ini otomatis diatur oleh sistem berdasarkan partisipasi Qurban.</small>
            <?php else: ?>
                <small style="display: block; margin-top: 5px;">Status ini akan diatur otomatis jika warga berpartisipasi Qurban.</small>
            <?php endif; ?>
        </div> -->
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php include '../../includes/footer.php'; ?>