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

$id_user = '';
// Dapatkan ID user dari URL (GET)
if (isset($_GET['id'])) {
    $id_user = sanitizeInput($_GET['id']);
} else {
    $_SESSION['message'] = "ID user tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

// Ambil data user yang akan diedit
$stmt_get_user = $conn->prepare("SELECT id, username, role, nik_warga FROM users WHERE id = ?");
$stmt_get_user->bind_param("i", $id_user);
$stmt_get_user->execute();
$result_get_user = $stmt_get_user->get_result();

if ($result_get_user->num_rows > 0) {
    $user_data = $result_get_user->fetch_assoc();
    $username = $user_data['username'];
    $role = $user_data['role'];
    $nik_warga = $user_data['nik_warga'];
} else {
    $_SESSION['message'] = "Data user tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}
$stmt_get_user->close();

// Admin tidak bisa mengedit dirinya sendiri (user utama ID 1)
// Atau mengubah role/username Admin utama (ID 1)
$is_main_admin = ($id_user == 1 && $username == 'admin');
$can_edit_main_admin = ($_SESSION['user_id'] == 1 && $is_main_admin); // Hanya admin utama bisa edit dirinya sendiri
$can_change_role_username = !$is_main_admin || $can_edit_main_admin; // Bisa ubah role/username kecuali admin utama

if ($is_main_admin && $_SESSION['user_id'] != $id_user) {
    $_SESSION['message'] = "Anda tidak bisa mengedit akun Admin utama ini.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}


$errors = []; // Untuk menampilkan error jika ada redirect balik dari update_user.php

// Ambil daftar warga untuk dropdown NIK
$sql_warga = "SELECT nik, nama, status_qurban, status_panitia FROM warga ORDER BY nama ASC";
$result_warga = $conn->query($sql_warga);
$list_warga = [];
if ($result_warga && $result_warga->num_rows > 0) {
    while($row = $result_warga->fetch_assoc()) {
        $list_warga[] = $row;
    }
}

// Ambil pesan error/sukses dari session jika ada (dari update_user.php)
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['form_data'])) {
    // Jika ada data form yang dikirim balik (misal karena error), gunakan data itu
    $username = $_SESSION['form_data']['username'] ?? '';
    $role = $_SESSION['form_data']['role'] ?? '';
    $nik_warga = $_SESSION['form_data']['nik_warga'] ?? '';
    unset($_SESSION['form_data']);
}

?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <h2>Edit User: <?php echo htmlspecialchars($username); ?></h2>
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
    <form action="update_user.php" method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_user); ?>">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required <?php echo $can_change_role_username ? '' : 'readonly'; ?>>
            <?php if (!$can_change_role_username): ?>
                <small style="color: orange; display: block; margin-top: 5px;">Username Admin utama tidak dapat diubah.</small>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="password">Password Baru (kosongkan jika tidak ingin mengubah):</label>
            <input type="password" id="password" name="password">
            <small>Isi hanya jika ingin mengubah password.</small>
        </div>
        <div class="form-group">
            <label for="role">Role:</label>
            <select id="role" name="role" required <?php echo $can_change_role_username ? '' : 'disabled'; ?>>
                <option value="warga" <?php echo ($role == 'warga') ? 'selected' : ''; ?>>Warga</option>
                <option value="panitia" <?php echo ($role == 'panitia') ? 'selected' : ''; ?>>Panitia</option>
                <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>
            <?php if (!$can_change_role_username): ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                <small style="color: orange; display: block; margin-top: 5px;">Role Admin utama tidak dapat diubah.</small>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="nik_warga">NIK Warga (Opsional, untuk menghubungkan user ke data warga):</label>
            <select id="nik_warga" name="nik_warga" <?php echo $can_change_role_username ? '' : 'disabled'; ?>>
                <option value="">-- Pilih NIK Warga (kosongkan jika tidak ada) --</option>
                <?php foreach ($list_warga as $warga): ?>
                    <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_warga == $warga['nik']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                        <?php echo ($warga['status_panitia'] ? ' - Panitia' : ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$can_change_role_username): ?>
                <input type="hidden" name="nik_warga" value="<?php echo htmlspecialchars($nik_warga); ?>">
                <small style="color: orange; display: block; margin-top: 5px;">NIK Warga untuk Admin utama tidak dapat diubah dari sini.</small>
            <?php endif; ?>
            <small>Pilih NIK warga yang sudah ada. Jika NIK sudah terhubung dengan user lain, akan ada pesan error.</small>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="users.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>