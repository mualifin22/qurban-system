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
$status_panitia = 0;
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

// Ambil pesan error/sukses dari session jika ada (dari update_warga.php)
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['form_data'])) {
    // Jika ada data form yang dikirim balik (misal karena error), gunakan data itu
    $nik = $_SESSION['form_data']['nik'];
    $nama = $_SESSION['form_data']['nama'];
    $alamat = $_SESSION['form_data']['alamat'];
    $no_hp = $_SESSION['form_data']['no_hp'];
    $status_qurban = $_SESSION['form_data']['status_qurban'];
    $status_panitia = isset($_SESSION['form_data']['status_panitia']) ? 1 : 0;
    unset($_SESSION['form_data']);
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
    <form action="update_warga.php" method="POST"> <input type="hidden" name="nik_original" value="<?php echo htmlspecialchars($nik_to_edit); ?>">
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
        <div class="form-group">
            <label for="status_qurban">Status Qurban:</label>
            <select id="status_qurban" name="status_qurban" required>
                <option value="tidak_ikut" <?php echo ($status_qurban == 'tidak_ikut') ? 'selected' : ''; ?>>Tidak Ikut</option>
                <option value="peserta" <?php echo ($status_qurban == 'peserta') ? 'selected' : ''; ?>>Peserta Qurban</option>
                <option value="penerima" <?php echo ($status_qurban == 'penerima') ? 'selected' : ''; ?>>Penerima Daging</option>
            </select>
        </div>
        <div class="form-group">
            <input type="checkbox" id="status_panitia" name="status_panitia" value="1" <?php echo ($status_panitia == 1) ? 'checked' : ''; ?>>
            <label for="status_panitia">Panitia</label>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php include '../../includes/footer.php'; ?>