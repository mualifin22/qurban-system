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

$nik_to_edit = '';
$nik = $nama = $alamat = $no_hp = $status_qurban = '';
$status_panitia = 0;
$errors = [];

if (isset($_GET['nik'])) {
    $nik_to_edit = sanitizeInput($_GET['nik']);
} elseif (isset($_POST['nik_original'])) {
    $nik_to_edit = sanitizeInput($_POST['nik_original']);
} else {
    $_SESSION['message'] = "NIK warga tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik_original = sanitizeInput($_POST['nik_original'] ?? '');
    $nik = sanitizeInput($_POST['nik'] ?? '');
    $nama = sanitizeInput($_POST['nama'] ?? '');
    $alamat = sanitizeInput($_POST['alamat'] ?? '');
    $no_hp = sanitizeInput($_POST['no_hp'] ?? '');

    if (empty($nik)) { $errors[] = "NIK wajib diisi."; }
    if (empty($nama)) { $errors[] = "Nama wajib diisi."; }
    if (!preg_match('/^[0-9]{16}$/', $nik)) { $errors[] = "NIK harus 16 digit angka."; }

    if ($nik !== $nik_original) {
        $stmt_check_nik = $conn->prepare("SELECT nik FROM warga WHERE nik = ?");
        $stmt_check_nik->bind_param("s", $nik);
        $stmt_check_nik->execute();
        $stmt_check_nik->store_result();
        if ($stmt_check_nik->num_rows > 0) {
            $errors[] = "NIK baru sudah terdaftar.";
        }
        $stmt_check_nik->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE warga SET nik = ?, nama = ?, alamat = ?, no_hp = ? WHERE nik = ?");
            $stmt->bind_param("sssss", $nik, $nama, $alamat, $no_hp, $nik_original);
            $stmt->execute();
            if ($stmt->error) { throw new mysqli_sql_exception("Error update warga: " . $stmt->error); }
            $stmt->close();

            $conn->commit();
            $_SESSION['message'] = "Data warga '" . htmlspecialchars($nama) . "' berhasil diperbarui.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Gagal memperbarui data: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: edit.php?nik=" . urlencode($nik_original));
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit.php?nik=" . urlencode($nik_original));
        exit();
    }
} else {
    if (isset($_SESSION['form_data'])) {
        $nik = $_SESSION['form_data']['nik'] ?? $nik;
        $nama = $_SESSION['form_data']['nama'] ?? $nama;
        $alamat = $_SESSION['form_data']['alamat'] ?? $alamat;
        $no_hp = $_SESSION['form_data']['no_hp'] ?? $no_hp;
        unset($_SESSION['form_data']);
    } else {
        $stmt_get_data = $conn->prepare("SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga WHERE nik = ?");
        $stmt_get_data->bind_param("s", $nik_to_edit);
        $stmt_get_data->execute();
        $result_get_data = $stmt_get_data->get_result();

        if ($result_get_data->num_rows > 0) {
            $warga_data = $result_get_data->fetch_assoc();
            $nik = $warga_data['nik'];
            $nama = $warga_data['nama'];
            $alamat = $warga_data['alamat'];
            $no_hp = $warga_data['no_hp'];
            $status_qurban = $warga_data['status_qurban'];
            $status_panitia = $warga_data['status_panitia'];
        } else {
            $_SESSION['message'] = "Data warga tidak ditemukan.";
            $_SESSION['message_type'] = "error";
            header("Location: index.php");
            exit();
        }
        $stmt_get_data->close();
    }

    if (isset($_SESSION['errors'])) {
        $errors = $_SESSION['errors'];
        unset($_SESSION['errors']);
    }
}

?>
<?php include '../../includes/header.php'; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Data Warga</h1>
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
        <h6 class="m-0 font-weight-bold text-primary">Form Edit Data Warga</h6>
    </div>
    <div class="card-body">
        <form action="edit.php?nik=<?php echo htmlspecialchars($nik_to_edit); ?>" method="POST">
            <input type="hidden" name="nik_original" value="<?php echo htmlspecialchars($nik_to_edit); ?>">
            
            <h6 class="font-weight-bold text-primary">Data Pribadi</h6>
            <hr class="mt-0">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="nik">NIK (16 Digit)</label>
                    <input type="text" class="form-control" id="nik" name="nik" value="<?php echo htmlspecialchars($nik); ?>" required pattern="[0-9]{16}" title="NIK harus terdiri dari 16 digit angka.">
                </div>
                <div class="form-group col-md-6">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($alamat); ?></textarea>
            </div>
            <div class="form-group">
                <label for="no_hp">No. HP (Opsional)</label>
                <input type="tel" class="form-control" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>" placeholder="Contoh: 08123456789">
            </div>

            <h6 class="font-weight-bold text-primary mt-4">Status Sistem</h6>
            <hr class="mt-0">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Status Qurban</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status_qurban))); ?>" readonly>
                    <small class="form-text text-muted">Status ini dikelola otomatis oleh sistem Qurban.</small>
                </div>
                <div class="form-group col-md-6">
                    <label>Status Panitia</label>
                    <input type="text" class="form-control" value="<?php echo ($status_panitia ? 'Ya, Sebagai Panitia' : 'Tidak'); ?>" readonly>
                     <small class="form-text text-muted">Status ini dikelola melalui Manajemen User.</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary mt-3">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>