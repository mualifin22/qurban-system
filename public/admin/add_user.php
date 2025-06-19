<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

    if (empty($username)) { $errors[] = "Username wajib diisi."; }
    if (empty($password)) { $errors[] = "Password wajib diisi."; }
    if (empty($role) || !in_array($role, ['warga', 'panitia', 'admin'])) { $errors[] = "Role tidak valid."; }

    $stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check_username->bind_param("s", $username);
    $stmt_check_username->execute();
    $stmt_check_username->store_result();
    if ($stmt_check_username->num_rows > 0) {
        $errors[] = "Username sudah terdaftar. Gunakan username lain.";
    }
    $stmt_check_username->close();

    if (!empty($nik_warga)) {
        $stmt_check_nik = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
        $stmt_check_nik->bind_param("s", $nik_warga);
        $stmt_check_nik->execute();
        if ($stmt_check_nik->get_result()->fetch_row()[0] == 0) {
            $errors[] = "NIK warga tidak ditemukan.";
        }
        $stmt_check_nik->close();

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

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Tambah User Baru</h1>
</div>

<?php
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . ($_SESSION['message_type'] == 'error' ? 'danger' : ($_SESSION['message_type'] == 'info' ? 'info' : 'success')) . ' alert-dismissible fade show" role="alert">';
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
        <h6 class="m-0 font-weight-bold text-primary">Form Tambah User</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select class="form-control" id="role" name="role" required>
                    <option value="">-- Pilih Role --</option>
                    <option value="warga" <?php echo ($role == 'warga') ? 'selected' : ''; ?>>Warga</option>
                    <option value="panitia" <?php echo ($role == 'panitia') ? 'selected' : ''; ?>>Panitia</option>
                    <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="nik_warga">NIK Warga (Opsional, untuk menghubungkan user ke data warga):</label>
                <select class="form-control" id="nik_warga" name="nik_warga">
                    <option value="">-- Pilih NIK Warga (kosongkan jika tidak ada) --</option>
                    <?php foreach ($list_warga as $warga): ?>
                        <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_warga == $warga['nik']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                            <?php echo ($warga['status_panitia'] ? ' - Panitia' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Pilih NIK warga yang sudah ada. Jika NIK sudah terhubung dengan user lain, akan ada pesan error.</small>
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="users.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>