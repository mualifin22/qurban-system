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

// Hanya Admin yang bisa mengakses halaman ini
if (!isAdmin()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

$username = '';
$password = '';
$role = '';
$nik_warga = '';
$errors = [];

// Ambil daftar warga untuk dropdown NIK
$sql_warga = "SELECT nik, nama, status_qurban, status_panitia FROM warga ORDER BY nama ASC";
$result_warga = $conn->query($sql_warga);
$list_warga = [];
if ($result_warga && $result_warga->num_rows > 0) {
    while($row = $result_warga->fetch_assoc()) {
        $list_warga[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? '');
    $nik_warga = sanitizeInput($_POST['nik_warga'] ?? '');

    // Validasi
    if (empty($username)) { $errors[] = "Username wajib diisi."; }
    if (empty($password)) { $errors[] = "Password wajib diisi."; }
    // Role hanya bisa 'warga', 'panitia', 'admin'
    if (empty($role) || !in_array($role, ['warga', 'panitia', 'admin'])) { $errors[] = "Role tidak valid."; }

    // Cek duplikasi username
    $stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check_username->bind_param("s", $username);
    $stmt_check_username->execute();
    $stmt_check_username->store_result();
    if ($stmt_check_username->num_rows > 0) {
        $errors[] = "Username sudah terdaftar. Gunakan username lain.";
    }
    $stmt_check_username->close();

    // Validasi NIK warga jika dipilih
    if (!empty($nik_warga)) {
        $stmt_check_nik = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
        $stmt_check_nik->bind_param("s", $nik_warga);
        $stmt_check_nik->execute();
        if ($stmt_check_nik->get_result()->fetch_row()[0] == 0) {
            $errors[] = "NIK warga tidak ditemukan.";
        }
        $stmt_check_nik->close();

        // Cek apakah NIK sudah punya akun user lain
        $stmt_check_nik_in_users = $conn->prepare("SELECT id FROM users WHERE nik_warga = ?");
        $stmt_check_nik_in_users->bind_param("s", $nik_warga);
        $stmt_check_nik_in_users->execute();
        $stmt_check_nik_in_users->store_result();
        if ($stmt_check_nik_in_users->num_rows > 0) {
            $errors[] = "NIK warga ini sudah terhubung dengan akun user lain.";
        }
        $stmt_check_nik_in_users->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            $hashed_password = hashPassword($password);
            $stmt_insert_user = $conn->prepare("INSERT INTO users (username, password, role, nik_warga) VALUES (?, ?, ?, ?)");
            $nik_to_bind = !empty($nik_warga) ? $nik_warga : NULL;
            $stmt_insert_user->bind_param("ssss", $username, $hashed_password, $role, $nik_to_bind);
            $stmt_insert_user->execute();

            if ($stmt_insert_user->error) {
                throw new mysqli_sql_exception("Error saat menambahkan user: " . $stmt_insert_user->error);
            }
            $stmt_insert_user->close();

            // Jika user adalah panitia, update status_panitia di tabel warga
            if ($role === 'panitia' && !empty($nik_warga)) {
                $stmt_update_warga_panitia = $conn->prepare("UPDATE warga SET status_panitia = 1 WHERE nik = ?");
                $stmt_update_warga_panitia->bind_param("s", $nik_warga);
                $stmt_update_warga_panitia->execute();
                if ($stmt_update_warga_panitia->error) {
                    throw new mysqli_sql_exception("Error saat memperbarui status panitia warga: " . $stmt_update_warga_panitia->error);
                }
                $stmt_update_warga_panitia->close();
            }

            $conn->commit();
            $_SESSION['message'] = "User " . htmlspecialchars($username) . " berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: users.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $errors[] = "Gagal menambahkan user: " . $e->getMessage();
            $_SESSION['message'] = "Gagal menambahkan user: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            $_SESSION['form_data'] = $_POST;
            header("Location: add_user.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add_user.php");
        exit();
    }
} else {
    // Jika bukan POST, ambil data form dari session jika ada
    if (isset($_SESSION['form_data'])) {
        $username = $_SESSION['form_data']['username'] ?? '';
        $role = $_SESSION['form_data']['role'] ?? '';
        $nik_warga = $_SESSION['form_data']['nik_warga'] ?? '';
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
    <h2>Tambah User Baru</h2>
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
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="role">Role:</label>
            <select id="role" name="role" required>
                <option value="">-- Pilih Role --</option>
                <option value="warga" <?php echo ($role == 'warga') ? 'selected' : ''; ?>>Warga</option>
                <option value="panitia" <?php echo ($role == 'panitia') ? 'selected' : ''; ?>>Panitia</option>
                <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div class="form-group">
            <label for="nik_warga">NIK Warga (Opsional, untuk menghubungkan user ke data warga):</label>
            <select id="nik_warga" name="nik_warga">
                <option value="">-- Pilih NIK Warga (kosongkan jika tidak ada) --</option>
                <?php foreach ($list_warga as $warga): ?>
                    <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_warga == $warga['nik']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                        <?php echo ($warga['status_panitia'] ? ' - Panitia' : ''); ?>
                        <?php // Kita tidak perlu menampilkan status_qurban di sini karena itu akan disesuaikan otomatis
                        // echo ($warga['status_qurban'] == 'peserta' ? ' - Peserta Qurban' : '');
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Pilih NIK warga yang sudah ada. Jika NIK sudah terhubung dengan user lain, akan ada pesan error.</small>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="users.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>