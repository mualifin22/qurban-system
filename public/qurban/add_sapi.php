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
$biaya_administrasi = 100000;
$estimasi_berat_daging_kg = 100;
$tanggal_beli = date('Y-m-d');
$errors = [];
$selected_peserta = [];

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
    $selected_peserta = isset($_POST['peserta']) && is_array($_POST['peserta']) ? $_POST['peserta'] : [];

    if (!is_numeric($harga) || $harga <= 0) { $errors[] = "Harga harus angka positif."; }
    if (!is_numeric($biaya_administrasi) || $biaya_administrasi < 0) { $errors[] = "Biaya administrasi tidak valid."; }
    if (!is_numeric($estimasi_berat_daging_kg) || $estimasi_berat_daging_kg <= 0) { $errors[] = "Estimasi berat daging harus angka positif."; }
    if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }
    if (count($selected_peserta) !== 7) {
        $errors[] = "Qurban sapi harus memiliki tepat 7 peserta.";
    } else {
        foreach ($selected_peserta as $nik) {
            $stmt_check_peserta = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
            $stmt_check_peserta->bind_param("s", $nik);
            $stmt_check_peserta->execute();
            $result_check = $stmt_check_peserta->get_result()->fetch_assoc();
            if (!$result_check) {
                $errors[] = "NIK peserta " . htmlspecialchars($nik) . " tidak valid.";
            } else if ($result_check['status_qurban'] === 'peserta') {
                $errors[] = "Warga dengan NIK " . htmlspecialchars($nik) . " sudah terdaftar sebagai peserta qurban lain.";
            }
            $stmt_check_peserta->close();
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        $old_statuses = [];
        try {
            $stmt_get_old_status = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
            foreach ($selected_peserta as $nik_p) {
                $stmt_get_old_status->bind_param("s", $nik_p);
                $stmt_get_old_status->execute();
                if($row = $stmt_get_old_status->get_result()->fetch_assoc()) {
                    $old_statuses[$nik_p] = $row['status_qurban'];
                }
            }
            $stmt_get_old_status->close();
            
            $stmt_hewan = $conn->prepare("INSERT INTO hewan_qurban (jenis_hewan, harga, biaya_administrasi, tanggal_beli, estimasi_berat_daging_kg) VALUES ('sapi', ?, ?, ?, ?)");
            $stmt_hewan->bind_param("ddsd", $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg);
            $stmt_hewan->execute();
            if ($stmt_hewan->error) { throw new mysqli_sql_exception("Error saat memasukkan data hewan sapi: " . $stmt_hewan->error); }
            $id_hewan_qurban = $conn->insert_id;
            $stmt_hewan->close();

            $iuran_per_orang = ($harga + $biaya_administrasi) / 7;
            $stmt_update_warga = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ?");
            $stmt_peserta_sapi = $conn->prepare("INSERT INTO peserta_sapi (id_hewan_qurban, nik_warga, jumlah_iuran) VALUES (?, ?, ?)");
            $stmt_keuangan_in = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES ('pemasukan', ?, ?, ?, ?)");

            foreach ($selected_peserta as $nik_p) {
                $stmt_update_warga->bind_param("s", $nik_p);
                $stmt_update_warga->execute();
                if ($stmt_update_warga->error) { throw new mysqli_sql_exception("Error update status NIK " . $nik_p . ": " . $stmt_update_warga->error); }

                $stmt_peserta_sapi->bind_param("isd", $id_hewan_qurban, $nik_p, $iuran_per_orang);
                $stmt_peserta_sapi->execute();
                if ($stmt_peserta_sapi->error) { throw new mysqli_sql_exception("Error insert peserta NIK " . $nik_p . ": " . $stmt_peserta_sapi->error); }

                $keterangan_iuran = "Iuran Sapi (ID Hewan: " . $id_hewan_qurban . ") dari NIK " . $nik_p;
                $stmt_keuangan_in->bind_param("sdsi", $keterangan_iuran, $iuran_per_orang, $tanggal_beli, $id_hewan_qurban);
                $stmt_keuangan_in->execute();
                if ($stmt_keuangan_in->error) { throw new mysqli_sql_exception("Error insert iuran NIK " . $nik_p . ": " . $stmt_keuangan_in->error); }
            }
            $stmt_update_warga->close();
            $stmt_peserta_sapi->close();
            $stmt_keuangan_in->close();

            $total_biaya_sapi = $harga + $biaya_administrasi;
            $keterangan_beli_sapi = "Pembelian Qurban Sapi (ID Hewan: " . $id_hewan_qurban . ")";
            $stmt_keuangan_out = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES ('pengeluaran', ?, ?, ?, ?)");
            $stmt_keuangan_out->bind_param("sdsi", $keterangan_beli_sapi, $total_biaya_sapi, $tanggal_beli, $id_hewan_qurban);
            $stmt_keuangan_out->execute();
            if ($stmt_keuangan_out->error) { throw new mysqli_sql_exception("Error saat mencatat pengeluaran: " . $stmt_keuangan_out->error); }
            $stmt_keuangan_out->close();

            $conn->commit();
            $_SESSION['message'] = "Data qurban sapi dan 7 peserta berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (!empty($old_statuses)) {
                $stmt_rollback_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
                foreach ($old_statuses as $nik_p => $old_status) {
                    $stmt_rollback_status->bind_param("ss", $old_status, $nik_p);
                    $stmt_rollback_status->execute();
                }
                $stmt_rollback_status->close();
            }
            $_SESSION['message'] = "Gagal menambahkan data: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: add_sapi.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add_sapi.php");
        exit();
    }
} else {
    if (isset($_SESSION['form_data'])) {
        $harga = $_SESSION['form_data']['harga'] ?? '';
        $biaya_administrasi = $_SESSION['form_data']['biaya_administrasi'] ?? $biaya_administrasi;
        $estimasi_berat_daging_kg = $_SESSION['form_data']['estimasi_berat_daging_kg'] ?? $estimasi_berat_daging_kg;
        $tanggal_beli = $_SESSION['form_data']['tanggal_beli'] ?? date('Y-m-d');
        $selected_peserta = $_SESSION['form_data']['peserta'] ?? [];
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
    <h1 class="h3 mb-0 text-gray-800">Tambah Data Qurban Sapi</h1>
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
        <h6 class="m-0 font-weight-bold text-primary">Form Data Hewan Qurban Sapi</h6>
    </div>
    <div class="card-body">
        <form action="add_sapi.php" method="POST" id="form-qurban-sapi">
            <div class="form-group">
                <label for="harga">Harga Sapi (Rp)</label>
                <input type="number" class="form-control" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" step="1000" placeholder="Contoh: 21000000" required>
            </div>
            <div class="form-group">
                <label for="biaya_administrasi">Biaya Administrasi (Rp)</label>
                <input type="number" class="form-control" id="biaya_administrasi" name="biaya_administrasi" value="<?php echo htmlspecialchars($biaya_administrasi); ?>" step="1000" required>
            </div>
            <div class="form-group">
                <label for="estimasi_berat_daging_kg">Estimasi Berat Daging (kg)</label>
                <input type="number" class="form-control" id="estimasi_berat_daging_kg" name="estimasi_berat_daging_kg" value="<?php echo htmlspecialchars($estimasi_berat_daging_kg); ?>" step="0.5" placeholder="Contoh: 100.5" required>
            </div>
            <div class="form-group">
                <label for="tanggal_beli">Tanggal Beli</label>
                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
            </div>

            <div class="form-group">
                <label>Pilih 7 Peserta Qurban Sapi:</label>
                <p class="form-text text-muted">Peserta dipilih: <strong id="peserta-counter">0</strong> dari 7. Warga yang sudah menjadi peserta tidak dapat dipilih.</p>
                
                <div class="list-peserta-wrapper border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                    <?php if (!empty($list_warga)): ?>
                        <?php foreach ($list_warga as $warga): ?>
                            <?php
                                $isDisabled = ($warga['status_qurban'] === 'peserta');
                                $isChecked = in_array($warga['nik'], $selected_peserta);
                                $statusText = $isDisabled ? ' - (Sudah jadi peserta)' : '';
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="peserta[]" value="<?php echo htmlspecialchars($warga['nik']); ?>" id="peserta_<?php echo htmlspecialchars($warga['nik']); ?>"
                                    <?php echo $isChecked ? 'checked' : ''; ?>
                                    <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="peserta_<?php echo htmlspecialchars($warga['nik']); ?>">
                                    <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                                    <span class="text-danger"><?php echo htmlspecialchars($statusText); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-danger">Tidak ada data warga yang bisa dipilih.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php 
$script = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="peserta[]"]');
    const counterElement = document.getElementById('peserta-counter');
    const form = document.getElementById('form-qurban-sapi');
    const limit = 7;

    function updateCounter() {
        const checkedCount = document.querySelectorAll('input[name="peserta[]"]:checked').length;
        counterElement.textContent = checkedCount;
        
        if (checkedCount === limit) {
            counterElement.style.color = 'green';
            counterElement.style.fontWeight = 'bold';
        } else {
            counterElement.style.color = 'red';
            counterElement.style.fontWeight = 'bold';
        }

        if (checkedCount >= limit) {
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.disabled = true;
                }
            });
        } else {
            checkboxes.forEach(checkbox => {
                const isStatusPeserta = checkbox.nextElementSibling.querySelector('.text-danger').textContent.includes('Sudah jadi peserta');
                if (!isStatusPeserta) {
                   checkbox.disabled = false;
                }
            });
        }
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateCounter);
    });

    updateCounter();

    form.addEventListener('submit', function(event) {
        const checkedCount = document.querySelectorAll('input[name="peserta[]"]:checked').length;
        if (checkedCount !== limit) {
            alert('Harap pilih tepat 7 peserta untuk qurban sapi.');
            event.preventDefault();
        }
    });
});
</script>
JS;

include '../../includes/footer.php';
echo $script;
?>
