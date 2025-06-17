<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================================
// Bagian Pemrosesan Logika PHP (LOGIKA SUDAH BAIK, TIDAK ADA PERUBAHAN)
// =========================================================================

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    redirectToLogin();
}

$nik = '';
$nama = '';
$alamat = '';
$no_hp = '';
$create_user = 0; // Default checkbox tidak tercentang
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = sanitizeInput($_POST['nik'] ?? '');
    $nama = sanitizeInput($_POST['nama'] ?? '');
    $alamat = sanitizeInput($_POST['alamat'] ?? '');
    $no_hp = sanitizeInput($_POST['no_hp'] ?? '');
    $create_user = isset($_POST['create_user']) ? 1 : 0;

    // Validasi
    if (empty($nik)) { $errors[] = "NIK wajib diisi."; }
    if (empty($nama)) { $errors[] = "Nama wajib diisi."; }
    if (!preg_match('/^[0-9]{16}$/', $nik)) { $errors[] = "NIK harus terdiri dari 16 digit angka."; }

    if (empty($errors)) { // Hanya cek duplikasi jika validasi dasar lolos
        $stmt_check_nik = $conn->prepare("SELECT nik FROM warga WHERE nik = ?");
        $stmt_check_nik->bind_param("s", $nik);
        $stmt_check_nik->execute();
        $stmt_check_nik->store_result();
        if ($stmt_check_nik->num_rows > 0) {
            $errors[] = "NIK sudah terdaftar. Gunakan NIK yang berbeda.";
        }
        $stmt_check_nik->close();
    }
    
    // Proses jika tidak ada error sama sekali
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. Masukkan data warga baru
            $status_qurban_default = 'penerima';
            $stmt_warga = $conn->prepare("INSERT INTO warga (nik, nama, alamat, no_hp, status_qurban) VALUES (?, ?, ?, ?, ?)");
            $stmt_warga->bind_param("sssss", $nik, $nama, $alamat, $no_hp, $status_qurban_default);
            $stmt_warga->execute();
            if ($stmt_warga->error) {
                throw new mysqli_sql_exception("Error saat menambahkan data warga: " . $stmt_warga->error);
            }
            $stmt_warga->close();

            // 2. Buat user login jika dicentang
            if ($create_user) {
                $username_user = $nik;
                $password_default = hashPassword('password'); // Password default
                $role_user = 'warga';
                $stmt_user = $conn->prepare("INSERT INTO users (username, password, role, nik_warga) VALUES (?, ?, ?, ?)");
                $stmt_user->bind_param("ssss", $username_user, $password_default, $role_user, $nik);
                $stmt_user->execute();
                if ($stmt_user->error) {
                    throw new mysqli_sql_exception("Error saat membuat user login. NIK ini mungkin sudah digunakan sebagai username.: " . $stmt_user->error);
                }
                $stmt_user->close();
            }

            $conn->commit();
            $_SESSION['message'] = "Data warga '" . htmlspecialchars($nama) . "' berhasil ditambahkan.";
            if ($create_user) {
                $_SESSION['message'] .= " Akun login juga berhasil dibuat.";
            }
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Gagal menambahkan data warga: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: add.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add.php");
        exit();
    }
} else {
    if (isset($_SESSION['form_data'])) {
        $nik = $_SESSION['form_data']['nik'] ?? '';
        $nama = $_SESSION['form_data']['nama'] ?? '';
        $alamat = $_SESSION['form_data']['alamat'] ?? '';
        $no_hp = $_SESSION['form_data']['no_hp'] ?? '';
        $create_user = isset($_SESSION['form_data']['create_user']) ? 1 : 0;
        unset($_SESSION['form_data']);
    }
    if (isset($_SESSION['errors'])) {
        $errors = $_SESSION['errors'];
        unset($_SESSION['errors']);
    }
}

// =========================================================================
// Bagian Tampilan HTML (BAGIAN INI YANG DIPERCANTIK)
// =========================================================================
?>
<?php include '../../includes/header.php'; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Tambah Data Warga Baru</h1>
</div>

<?php
// Sistem Notifikasi Pesan Sesuai Standar
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
        <h6 class="m-0 font-weight-bold text-primary">Form Data Warga</h6>
    </div>
    <div class="card-body">
        <form action="add.php" method="POST">
            <div class="form-group">
                <label for="nik">NIK (16 Digit)</label>
                <input type="text" class="form-control" id="nik" name="nik" value="<?php echo htmlspecialchars($nik); ?>" required pattern="[0-9]{16}" title="NIK harus terdiri dari 16 digit angka.">
            </div>
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($alamat); ?></textarea>
            </div>
            <div class="form-group">
                <label for="no_hp">No. HP (Opsional)</label>
                <input type="tel" class="form-control" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>" placeholder="Contoh: 08123456789">
            </div>
            <hr>
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="create_user" name="create_user" value="1" <?php echo $create_user ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="create_user">
                        <strong>Buat Akun Login untuk Warga ini</strong>
                    </label>
                </div>
                <small class="form-text text-muted ml-4">Jika dicentang, akun login akan dibuat dengan username = NIK dan password default 'password'.</small>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">Simpan Data Warga</button>
            <a href="index.php" class="btn btn-secondary mt-3">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>