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

$nik = '';
$nama = '';
$alamat = '';
$no_hp = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = sanitizeInput($_POST['nik'] ?? '');
    $nama = sanitizeInput($_POST['nama'] ?? '');
    $alamat = sanitizeInput($_POST['alamat'] ?? '');
    $no_hp = sanitizeInput($_POST['no_hp'] ?? '');
    $create_user = isset($_POST['create_user']) ? 1 : 0; // Apakah checkbox 'Buat Akun Login' dicentang?

    // Validasi
    if (empty($nik)) { $errors[] = "NIK wajib diisi."; }
    if (empty($nama)) { $errors[] = "Nama wajib diisi."; }
    if (!preg_match('/^[0-9]{16}$/', $nik)) { $errors[] = "NIK harus 16 digit angka."; }

    // Cek duplikasi NIK
    $stmt_check_nik = $conn->prepare("SELECT nik FROM warga WHERE nik = ?");
    $stmt_check_nik->bind_param("s", $nik);
    $stmt_check_nik->execute();
    $stmt_check_nik->store_result();
    if ($stmt_check_nik->num_rows > 0) {
        $errors[] = "NIK sudah terdaftar. Gunakan NIK yang berbeda.";
    }
    $stmt_check_nik->close();

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // 1. Masukkan data warga baru
            // Default status_qurban adalah 'penerima'
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
                $username_user = $nik; // Default username adalah NIK
                $password_default = hashPassword('password'); // Password default
                $role_user = 'warga'; // Default role adalah 'warga', bisa berubah jadi 'berqurban' atau 'panitia' nanti
                $stmt_user = $conn->prepare("INSERT INTO users (username, password, role, nik_warga) VALUES (?, ?, ?, ?)");
                $stmt_user->bind_param("ssss", $username_user, $password_default, $role_user, $nik);
                $stmt_user->execute();
                if ($stmt_user->error) {
                    throw new mysqli_sql_exception("Error saat membuat user login: " . $stmt_user->error);
                }
                $stmt_user->close();
            }

            $conn->commit();
            $_SESSION['message'] = "Data warga berhasil ditambahkan.";
            if ($create_user) {
                $_SESSION['message'] .= " Akun login untuk warga ini juga berhasil dibuat.";
            }
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $errors[] = "Gagal menambahkan data warga: " . $e->getMessage();
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
    // Jika bukan POST, tampilkan form (dengan data yang mungkin tersimpan di session)
    if (isset($_SESSION['form_data'])) {
        $nik = $_SESSION['form_data']['nik'] ?? '';
        $nama = $_SESSION['form_data']['nama'] ?? '';
        $alamat = $_SESSION['form_data']['alamat'] ?? '';
        $no_hp = $_SESSION['form_data']['no_hp'] ?? '';
        unset($_SESSION['form_data']);
    }
    if (isset($_SESSION['errors'])) {
        $errors = $_SESSION['errors'];
        unset($_SESSION['errors']);
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <h2>Tambah Data Warga Baru</h2>
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
    <form action="" method="POST">
        <div class="form-group">
            <label for="nik">NIK (16 Digit):</label>
            <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($nik); ?>" required>
        </div>
        <div class="form-group">
            <label for="nama">Nama Lengkap:</label>
            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required>
        </div>
        <div class="form-group">
            <label for="alamat">Alamat:</label>
            <textarea id="alamat" name="alamat"><?php echo htmlspecialchars($alamat); ?></textarea>
        </div>
        <div class="form-group">
            <label for="no_hp">No. HP:</label>
            <input type="tel" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>">
        </div>
        <div class="form-group">
            <input type="checkbox" id="create_user" name="create_user" value="1">
            <label for="create_user">Buat Akun Login untuk Warga ini</label>
            <small>Jika dicentang, akun login dengan username = NIK dan password default akan dibuat.</small>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php include '../../includes/footer.php'; ?>