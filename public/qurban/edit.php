<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/db.php';        // Koneksi database
include '../../includes/functions.php';  // Fungsi-fungsi helper

// Pastikan session sudah dimulai.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login.
if (!isLoggedIn()) {
    redirectToLogin();
}

$id_qurban = '';
// Dapatkan ID qurban dari URL (GET). Jika tidak ada, redirect.
if (isset($_GET['id'])) {
    $id_qurban = sanitizeInput($_GET['id']);
} else {
    $_SESSION['message'] = "ID hewan qurban tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Inisialisasi variabel untuk form
$jenis_hewan = $harga = $biaya_administrasi = $estimasi_berat_daging_kg = '';
$tanggal_beli = date('Y-m-d'); // Default, akan ditimpa dari DB
$nik_peserta = ''; // NIK peserta kambing
$selected_peserta = []; // Array untuk menyimpan NIK peserta sapi yang sudah ada
$errors = []; // Untuk menampilkan error jika ada redirect balik dari update.php

// Ambil data hewan qurban dari database berdasarkan ID
$stmt_get = $conn->prepare("SELECT id, jenis_hewan, harga, biaya_administrasi, tanggal_beli, estimasi_berat_daging_kg, nik_peserta_tunggal FROM hewan_qurban WHERE id = ?");
$stmt_get->bind_param("i", $id_qurban);
$stmt_get->execute();
$result_get = $stmt_get->get_result();

if ($result_get->num_rows > 0) {
    $qurban_data = $result_get->fetch_assoc();
    $jenis_hewan = $qurban_data['jenis_hewan'];
    $harga = $qurban_data['harga'];
    $biaya_administrasi = $qurban_data['biaya_administrasi'];
    $estimasi_berat_daging_kg = $qurban_data['estimasi_berat_daging_kg'];
    $tanggal_beli = $qurban_data['tanggal_beli'];

    // Jika kambing, ambil data pesertanya
    if ($jenis_hewan === 'kambing') {
        $nik_peserta = $qurban_data['nik_peserta_tunggal'];
    }
    // Jika sapi, ambil data pesertanya
    elseif ($jenis_hewan === 'sapi') {
        $sql_current_peserta = "SELECT nik_warga FROM peserta_sapi WHERE id_hewan_qurban = ?";
        $stmt_current_peserta = $conn->prepare($sql_current_peserta);
        $stmt_current_peserta->bind_param("i", $id_qurban);
        $stmt_current_peserta->execute();
        $result_current_peserta = $stmt_current_peserta->get_result();
        while($p = $result_current_peserta->fetch_assoc()) {
            $selected_peserta[] = $p['nik_warga'];
        }
        $stmt_current_peserta->close();
    }
} else {
    // Jika data tidak ditemukan, redirect dengan pesan error
    $_SESSION['message'] = "Data hewan qurban tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$stmt_get->close();

// Ambil daftar warga untuk pilihan peserta (baik kambing atau sapi)
$sql_warga_peserta_select = "SELECT nik, nama FROM warga WHERE status_qurban = 'peserta' OR status_panitia = TRUE ORDER BY nama ASC";
$result_warga_peserta_select = $conn->query($sql_warga_peserta_select);
$list_warga = [];
if ($result_warga_peserta_select && $result_warga_peserta_select->num_rows > 0) {
    while($row = $result_warga_peserta_select->fetch_assoc()) {
        $list_warga[] = $row;
    }
}

// Ambil pesan error/sukses dari session jika ada (dari update.php)
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['form_data'])) {
    // Jika ada data form yang dikirim balik (misal karena error), gunakan data itu
    $harga = $_SESSION['form_data']['harga'];
    $biaya_administrasi = $_SESSION['form_data']['biaya_administrasi'];
    $estimasi_berat_daging_kg = $_SESSION['form_data']['estimasi_berat_daging_kg'];
    $tanggal_beli = $_SESSION['form_data']['tanggal_beli'];
    if ($jenis_hewan === 'kambing') {
        $nik_peserta = $_SESSION['form_data']['nik_peserta'];
    } elseif ($jenis_hewan === 'sapi') {
        $selected_peserta = $_SESSION['form_data']['peserta'];
    }
    unset($_SESSION['form_data']);
}

?>

<?php include '../../includes/header.php'; // Sertakan header ?>

<div class="container">
    <h2>Edit Data Qurban <?php echo htmlspecialchars(ucfirst($jenis_hewan)); ?></h2>
    <?php
    // Tampilkan pesan error jika ada
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    // Tampilkan pesan sukses jika ada (misal dari redirect update.php)
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <form action="update.php" method="POST"> <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_qurban); ?>">
        <input type="hidden" name="jenis_hewan" value="<?php echo htmlspecialchars($jenis_hewan); ?>">

        <div class="form-group">
            <label for="jenis_hewan_display">Jenis Hewan:</label>
            <input type="text" id="jenis_hewan_display" value="<?php echo htmlspecialchars(ucfirst($jenis_hewan)); ?>" readonly>
            <small>Jenis hewan tidak dapat diubah setelah dibuat.</small>
        </div>
        <div class="form-group">
            <label for="harga">Harga:</label>
            <input type="number" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" step="10000" required>
        </div>
        <div class="form-group">
            <label for="biaya_administrasi">Biaya Administrasi:</label>
            <input type="number" id="biaya_administrasi" name="biaya_administrasi" value="<?php echo htmlspecialchars($biaya_administrasi); ?>" step="10000" required>
        </div>
        <div class="form-group">
            <label for="estimasi_berat_daging_kg">Estimasi Berat Daging (kg):</label>
            <input type="number" id="estimasi_berat_daging_kg" name="estimasi_berat_daging_kg" value="<?php echo htmlspecialchars($estimasi_berat_daging_kg); ?>" step="0.1" required>
        </div>
        <div class="form-group">
            <label for="tanggal_beli">Tanggal Beli:</label>
            <input type="date" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
        </div>

        <?php if ($jenis_hewan === 'kambing'): ?>
            <div class="form-group">
                <label for="nik_peserta">Peserta Qurban Kambing (1 Orang):</label>
                <select id="nik_peserta" name="nik_peserta" required>
                    <option value="">-- Pilih Warga --</option>
                    <?php foreach ($list_warga as $warga): ?>
                        <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_peserta == $warga['nik']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($list_warga)): ?>
                    <p style="color: red; margin-top: 5px;">Tidak ada warga yang terdaftar sebagai peserta atau panitia.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($jenis_hewan === 'sapi'): ?>
            <div id="peserta_sapi_section" class="form-group">
                <label>Pilih 7 Peserta Qurban Sapi:</label>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                <?php if (!empty($list_warga)): ?>
                    <?php foreach ($list_warga as $warga): ?>
                        <input type="checkbox" id="peserta_<?php echo htmlspecialchars($warga['nik']); ?>" name="peserta[]" value="<?php echo htmlspecialchars($warga['nik']); ?>"
                        <?php echo in_array($warga['nik'], $selected_peserta) ? 'checked' : ''; ?>>
                        <label for="peserta_<?php echo htmlspecialchars($warga['nik']); ?>"><?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)</label><br>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: red; margin-top: 5px;">Tidak ada warga yang terdaftar sebagai peserta atau panitia.</p>
                <?php endif; ?>
                </div>
                <small>Pilih **tepat 7 orang** untuk qurban sapi.</small>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php include '../../includes/footer.php'; // Sertakan footer ?>