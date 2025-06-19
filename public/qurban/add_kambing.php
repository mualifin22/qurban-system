<?php
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

$harga = '';
$biaya_administrasi = 50000; 
$estimasi_berat_daging_kg = 25; 
$tanggal_beli = date('Y-m-d');
$nik_peserta = '';
$errors = [];

$sql_warga_peserta = "SELECT nik, nama, status_qurban FROM warga ORDER BY nama ASC";
$result_warga_peserta = $conn->query($sql_warga_peserta);
$list_warga = [];
if ($result_warga_peserta && $result_warga_peserta->num_rows > 0) {
    while($row = $result_warga_peserta->fetch_assoc()) {
        $list_warga[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $harga = sanitizeInput($_POST['harga'] ?? '');
    $biaya_administrasi = sanitizeInput($_POST['biaya_administrasi'] ?? 0);
    $estimasi_berat_daging_kg = sanitizeInput($_POST['estimasi_berat_daging_kg'] ?? 0);
    $tanggal_beli = sanitizeInput($_POST['tanggal_beli'] ?? '');
    $nik_peserta = sanitizeInput($_POST['nik_peserta'] ?? '');

    if (!is_numeric($harga) || $harga <= 0) { $errors[] = "Harga harus angka positif."; }
    if (!is_numeric($biaya_administrasi) || $biaya_administrasi < 0) { $errors[] = "Biaya administrasi tidak valid."; }
    if (!is_numeric($estimasi_berat_daging_kg) || $estimasi_berat_daging_kg <= 0) { $errors[] = "Estimasi berat daging harus angka positif."; }
    if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }
    if (empty($nik_peserta)) { $errors[] = "Peserta qurban kambing wajib dipilih."; }
    else {
        $stmt_check_peserta = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
        $stmt_check_peserta->bind_param("s", $nik_peserta);
        $stmt_check_peserta->execute();
        $result_check = $stmt_check_peserta->get_result()->fetch_assoc();
        if ($result_check && $result_check['status_qurban'] === 'peserta') {
            $errors[] = "Warga ini sudah terdaftar sebagai peserta qurban lain.";
        }
        $stmt_check_peserta->close();
    }


    if (empty($errors)) {
        $conn->begin_transaction();
        $old_status_qurban = '';
        try {
            $stmt_get_old_status = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
            $stmt_get_old_status->bind_param("s", $nik_peserta);
            $stmt_get_old_status->execute();
            $result_old_status = $stmt_get_old_status->get_result();
            if ($row = $result_old_status->fetch_assoc()) {
                $old_status_qurban = $row['status_qurban'];
            }
            $stmt_get_old_status->close();

            $stmt_hewan = $conn->prepare("INSERT INTO hewan_qurban (jenis_hewan, harga, biaya_administrasi, tanggal_beli, estimasi_berat_daging_kg, nik_peserta_tunggal) VALUES (?, ?, ?, ?, ?, ?)");
            $jenis_hewan = 'kambing';
            $stmt_hewan->bind_param("sddsds", $jenis_hewan, $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg, $nik_peserta);
            $stmt_hewan->execute();
            if ($stmt_hewan->error) { throw new mysqli_sql_exception("Error saat memasukkan data hewan: " . $stmt_hewan->error); }
            $id_hewan_qurban = $conn->insert_id;
            $stmt_hewan->close();
            
            $stmt_update_warga_status = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ?");
            $stmt_update_warga_status->bind_param("s", $nik_peserta);
            $stmt_update_warga_status->execute();
            if ($stmt_update_warga_status->error) { throw new mysqli_sql_exception("Error saat memperbarui status warga: " . $stmt_update_warga_status->error); }
            $stmt_update_warga_status->close();

            $total_biaya = $harga + $biaya_administrasi;
            $stmt_keuangan_out = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES ('pengeluaran', ?, ?, ?, ?)");
            $keterangan_beli = "Pembelian Qurban Kambing (ID Hewan: " . $id_hewan_qurban . ")";
            $stmt_keuangan_out->bind_param("sdsi", $keterangan_beli, $total_biaya, $tanggal_beli, $id_hewan_qurban);
            $stmt_keuangan_out->execute();
            if ($stmt_keuangan_out->error) { throw new mysqli_sql_exception("Error saat mencatat pengeluaran: " . $stmt_keuangan_out->error); }
            $stmt_keuangan_out->close();

            $stmt_keuangan_in = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES ('pemasukan', ?, ?, ?, ?)");
            $keterangan_iuran = "Iuran Qurban Kambing (ID Hewan: " . $id_hewan_qurban . ") dari NIK " . $nik_peserta;
            $stmt_keuangan_in->bind_param("sdsi", $keterangan_iuran, $total_biaya, $tanggal_beli, $id_hewan_qurban);
            $stmt_keuangan_in->execute();
            if ($stmt_keuangan_in->error) { throw new mysqli_sql_exception("Error saat mencatat pemasukan iuran: " . $stmt_keuangan_in->error); }
            $stmt_keuangan_in->close();

            $conn->commit();
            $_SESSION['message'] = "Data qurban kambing berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (!empty($old_status_qurban)) { 
                $stmt_rollback_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
                $stmt_rollback_status->bind_param("ss", $old_status_qurban, $nik_peserta);
                $stmt_rollback_status->execute();
                $stmt_rollback_status->close();
            }
            $_SESSION['message'] = "Gagal menambahkan data: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: add_kambing.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add_kambing.php");
        exit();
    }
} else {
    if (isset($_SESSION['form_data'])) {
        $harga = $_SESSION['form_data']['harga'] ?? '';
        $biaya_administrasi = $_SESSION['form_data']['biaya_administrasi'] ?? $biaya_administrasi;
        $estimasi_berat_daging_kg = $_SESSION['form_data']['estimasi_berat_daging_kg'] ?? $estimasi_berat_daging_kg;
        $tanggal_beli = $_SESSION['form_data']['tanggal_beli'] ?? date('Y-m-d');
        $nik_peserta = $_SESSION['form_data']['nik_peserta'] ?? '';
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
    <h1 class="h3 mb-0 text-gray-800">Tambah Data Qurban Kambing</h1>
</div>

<?php
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . ($_SESSION['message_type'] == 'error' ? 'danger' : 'success') . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['message']);
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
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
        <h6 class="m-0 font-weight-bold text-primary">Form Data Hewan Qurban Kambing</h6>
    </div>
    <div class="card-body">
        <form action="add_kambing.php" method="POST">
            <div class="form-group">
                <label for="harga">Harga Kambing (Rp)</label>
                <input type="number" class="form-control" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" step="1000" placeholder="Contoh: 3000000" required>
            </div>
            <div class="form-group">
                <label for="biaya_administrasi">Biaya Administrasi (Rp)</label>
                <input type="number" class="form-control" id="biaya_administrasi" name="biaya_administrasi" value="<?php echo htmlspecialchars($biaya_administrasi); ?>" step="1000" required>
            </div>
            <div class="form-group">
                <label for="estimasi_berat_daging_kg">Estimasi Berat Daging (kg)</label>
                <input type="number" class="form-control" id="estimasi_berat_daging_kg" name="estimasi_berat_daging_kg" value="<?php echo htmlspecialchars($estimasi_berat_daging_kg); ?>" step="0.1" placeholder="Contoh: 25.5" required>
                <small class="form-text text-muted">Perkiraan berat daging bersih per ekor kambing.</small>
            </div>
            <div class="form-group">
                <label for="tanggal_beli">Tanggal Beli</label>
                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
            </div>
            <div class="form-group">
                <label for="nik_peserta">Peserta Qurban (1 Orang)</label>
                <select class="form-control" id="nik_peserta" name="nik_peserta" required>
                    <option value="">-- Pilih Warga --</option>
                    <?php foreach ($list_warga as $warga): ?>
                        <?php
                            $disabled = ($warga['status_qurban'] === 'peserta') ? 'disabled' : '';
                            $selected = ($nik_peserta == $warga['nik']) ? 'selected' : '';
                            $status_text = ($warga['status_qurban'] === 'peserta') ? ' - (Sudah jadi peserta)' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo $selected; ?> <?php echo $disabled; ?>>
                            <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>) <?php echo htmlspecialchars($status_text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($list_warga)): ?>
                    <p class="text-danger mt-2">Tidak ada data warga yang bisa dipilih.</p>
                <?php endif; ?>
                <small class="form-text text-muted">Warga yang sudah terdaftar sebagai peserta tidak dapat dipilih.</small>
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>