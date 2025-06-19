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

$id_user = ''; 
$username = '';
$role = '';
$nik_warga = '';
$errors = []; 


if (isset($_GET['id'])) {
    $id_user = sanitizeInput($_GET['id']);
}
elseif (isset($_POST['id'])) {
    $id_user = sanitizeInput($_POST['id']);
}

else {
    $_SESSION['message'] = "ID user tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$is_main_admin_user = ($id_user == 1); 
$can_change_role_username = !$is_main_admin_user || ($_SESSION['user_id'] == 1 && $is_main_admin_user);

if ($is_main_admin_user && $_SESSION['user_id'] != $id_user) {
    $_SESSION['message'] = "Anda tidak bisa mengedit akun Admin utama ini.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari POST
    $id_user = sanitizeInput($_POST['id'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; 
    $role = sanitizeInput($_POST['role'] ?? ''); 
    $nik_warga = sanitizeInput($_POST['nik_warga'] ?? '');

    if (empty($id_user) || !is_numeric($id_user)) { $errors[] = "ID user tidak valid."; }
    if (empty($username)) { $errors[] = "Username wajib diisi."; }
    if (empty($role) || !in_array($role, ['warga', 'panitia', 'admin'])) { $errors[] = "Role tidak valid."; }

    $stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt_check_username->bind_param("si", $username, $id_user);
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

        $stmt_check_nik_in_users = $conn->prepare("SELECT id FROM users WHERE nik_warga = ? AND id != ?");
        $stmt_check_nik_in_users->bind_param("si", $nik_warga, $id_user);
        $stmt_check_nik_in_users->execute();
        $stmt_check_nik_in_users->store_result();
        if ($stmt_check_nik_in_users->num_rows > 0) {
            $errors[] = "NIK warga ini sudah terhubung dengan akun user lain.";
        }
        $stmt_check_nik_in_users->close();
    }

    if ($is_main_admin_user && $_SESSION['user_id'] == $id_user && $role !== 'admin') {
         $errors[] = "Admin utama tidak dapat mengubah role-nya sendiri.";
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_user.php?id=" . urlencode($id_user));
        exit();
    }

    $conn->begin_transaction();

    try {
        $stmt_get_old_user = $conn->prepare("SELECT username, role, nik_warga FROM users WHERE id = ?");
        $stmt_get_old_user->bind_param("i", $id_user);
        $stmt_get_old_user->execute();
        $old_user_data = $stmt_get_old_user->get_result()->fetch_assoc();
        $stmt_get_old_user->close();
        $old_nik_warga = $old_user_data['nik_warga'];

        $update_query = "UPDATE users SET username = ?, role = ?, nik_warga = ? ";
        if (!empty($password)) { 
            $hashed_password = hashPassword($password);
            $update_query .= ", password = ? ";
        }
        $update_query .= "WHERE id = ?";
        
        $stmt_update_user = $conn->prepare($update_query);
        $nik_to_bind = !empty($nik_warga) ? $nik_warga : NULL;

        if (!empty($password)) {
            $stmt_update_user->bind_param("ssssi", $username, $role, $nik_to_bind, $hashed_password, $id_user);
        } else {
            $stmt_update_user->bind_param("sssi", $username, $role, $nik_to_bind, $id_user);
        }
        $stmt_update_user->execute();
        if ($stmt_update_user->error) {
            throw new mysqli_sql_exception("Error saat memperbarui user: " . $stmt_update_user->error);
        }
        $stmt_update_user->close();

        if ($old_nik_warga !== $nik_warga || $old_user_data['role'] !== $role) {
            if (!empty($old_nik_warga)) {
                 $stmt_check_other = $conn->prepare("SELECT COUNT(*) FROM users WHERE nik_warga = ? AND role = 'panitia'");
                 $stmt_check_other->bind_param("s", $old_nik_warga);
                 $stmt_check_other->execute();
                 if ($stmt_check_other->get_result()->fetch_row()[0] == 0) {
                     $stmt_reset = $conn->prepare("UPDATE warga SET status_panitia = 0 WHERE nik = ?");
                     $stmt_reset->bind_param("s", $old_nik_warga);
                     $stmt_reset->execute();
                     $stmt_reset->close();
                 }
                 $stmt_check_other->close();
            }
            if (!empty($nik_warga) && $role === 'panitia') {
                 $stmt_set = $conn->prepare("UPDATE warga SET status_panitia = 1 WHERE nik = ?");
                 $stmt_set->bind_param("s", $nik_warga);
                 $stmt_set->execute();
                 $stmt_set->close();
            }
        }

        $conn->commit();
        $_SESSION['message'] = "User " . htmlspecialchars($username) . " berhasil diperbarui.";
        $_SESSION['message_type'] = "success";
        header("Location: users.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $errors[] = "Gagal memperbarui user: " . $e->getMessage();
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_user.php?id=" . urlencode($id_user));
        exit();
    }
}

if (!isset($_POST['id']) || !empty($errors)) {
    $stmt_get_data = $conn->prepare("SELECT id, username, role, nik_warga FROM users WHERE id = ?");
    $stmt_get_data->bind_param("i", $id_user);
    $stmt_get_data->execute();
    $result_get_data = $stmt_get_data->get_result();

    if ($result_get_data->num_rows > 0) {
        $user_data = $result_get_data->fetch_assoc();
        $username = $user_data['username'];
        $role = $user_data['role'];
        $nik_warga = $user_data['nik_warga'];
    } else {
        $_SESSION['message'] = "Data user tidak ditemukan.";
        $_SESSION['message_type'] = "error";
        header("Location: users.php");
        exit();
    }
    $stmt_get_data->close();
}

if (isset($_SESSION['form_data'])) {
    $username = $_SESSION['form_data']['username'] ?? $username;
    $role = $_SESSION['form_data']['role'] ?? $role;
    $nik_warga = $_SESSION['form_data']['nik_warga'] ?? $nik_warga;
    unset($_SESSION['form_data']);
}
if (isset($_SESSION['errors'])) {
    $errors = array_merge($errors, $_SESSION['errors']);
    unset($_SESSION['errors']);
}


$sql_warga = "SELECT nik, nama, status_qurban, status_panitia FROM warga ORDER BY nama ASC";
$result_warga = $conn->query($sql_warga);
$list_warga = [];
if ($result_warga && $result_warga->num_rows > 0) {
    while($row = $result_warga->fetch_assoc()) {
        $list_warga[] = $row;
    }
}

include '../../includes/header.php'; 
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit User: <?php echo htmlspecialchars($username); ?></h1>
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
        <h6 class="m-0 font-weight-bold text-primary">Form Edit User</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_user); ?>">

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required <?php echo $can_change_role_username ? '' : 'readonly'; ?>>
                <?php if (!$can_change_role_username): ?>
                    <small class="form-text text-warning">Username Admin utama tidak dapat diubah.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password Baru:</label>
                <input type="password" class="form-control" id="password" name="password">
                <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select class="form-control" id="role" name="role" required <?php echo $can_change_role_username ? '' : 'disabled'; ?>>
                    <option value="warga" <?php echo ($role == 'warga') ? 'selected' : ''; ?>>Warga</option>
                    <option value="panitia" <?php echo ($role == 'panitia') ? 'selected' : ''; ?>>Panitia</option>
                    <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
                <?php if (!$can_change_role_username): ?>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                    <small class="form-text text-warning">Role Admin utama tidak dapat diubah.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="nik_warga">NIK Warga (Opsional, untuk menghubungkan user ke data warga):</label>
                <select class="form-control" id="nik_warga" name="nik_warga" <?php echo $can_change_role_username ? '' : 'disabled'; ?>>
                    <option value="">-- Pilih NIK Warga (kosongkan jika tidak ada) --</option>
                    <?php foreach ($list_warga as $warga): ?>
                        <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_warga == $warga['nik']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                            <?php 
                                // Beri tanda jika warga ini sudah panitia TAPI bukan karena user yang diedit
                                $stmt_check_panitia_assoc = $conn->prepare("SELECT id FROM users WHERE nik_warga = ? AND role = 'panitia' AND id != ?");
                                $stmt_check_panitia_assoc->bind_param("si", $warga['nik'], $id_user);
                                $stmt_check_panitia_assoc->execute();
                                $is_panitia_other_user = $stmt_check_panitia_assoc->get_result()->num_rows > 0;
                                $stmt_check_panitia_assoc->close();
                                if ($warga['status_panitia'] || $is_panitia_other_user) { echo ' - (Sudah Panitia)'; }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$can_change_role_username): ?>
                    <input type="hidden" name="nik_warga" value="<?php echo htmlspecialchars($nik_warga); ?>">
                     <small class="form-text text-warning">NIK Warga untuk Admin utama tidak dapat diubah.</small>
                <?php endif; ?>
                 <small class="form-text text-muted">Pilih NIK warga yang sudah ada. Jika NIK sudah terhubung dengan user lain, akan ada pesan error.</small>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="users.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>