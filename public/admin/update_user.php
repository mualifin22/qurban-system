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

if (!isLoggedIn() || !isAdmin()) {
    redirectToLogin(); // Hanya Admin yang bisa mengakses ini
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: users.php");
    exit();
}

$id_user = sanitizeInput($_POST['id'] ?? '');
$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = sanitizeInput($_POST['role'] ?? ''); // Role yang dikirim dari form
$nik_warga = sanitizeInput($_POST['nik_warga'] ?? '');

$errors = [];

// Validasi
if (empty($id_user) || !is_numeric($id_user)) { $errors[] = "ID user tidak valid."; }
if (empty($username)) { $errors[] = "Username wajib diisi."; }
// Validasi role: hanya bisa 'warga', 'panitia', 'admin' dari form
if (empty($role) || !in_array($role, ['warga', 'panitia', 'admin'])) { $errors[] = "Role tidak valid."; }

// Cek duplikasi username (kecuali untuk user yang sedang diedit)
$stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt_check_username->bind_param("si", $username, $id_user);
$stmt_check_username->execute();
$stmt_check_username->store_result();
if ($stmt_check_username->num_rows > 0) {
    $errors[] = "Username sudah terdaftar. Gunakan username lain.";
}
$stmt_check_username->close();

// Validasi NIK warga jika diisi/diubah
if (!empty($nik_warga)) {
    $stmt_check_nik = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
    $stmt_check_nik->bind_param("s", $nik_warga);
    $stmt_check_nik->execute();
    if ($stmt_check_nik->get_result()->fetch_row()[0] == 0) {
        $errors[] = "NIK warga tidak ditemukan.";
    }
    $stmt_check_nik->close();

    // Cek apakah NIK sudah punya akun user lain (kecuali user yang sedang diedit)
    $stmt_check_nik_in_users = $conn->prepare("SELECT id FROM users WHERE nik_warga = ? AND id != ?");
    $stmt_check_nik_in_users->bind_param("si", $nik_warga, $id_user);
    $stmt_check_nik_in_users->execute();
    $stmt_check_nik_in_users->store_result();
    if ($stmt_check_nik_in_users->num_rows > 0) {
        $errors[] = "NIK warga ini sudah terhubung dengan akun user lain.";
    }
    $stmt_check_nik_in_users->close();
}

// Pencegahan: Admin tidak bisa mengedit akun admin utama (ID 1) atau mengubah role/username-nya
$is_main_admin_user = ($id_user == 1);
if ($is_main_admin_user && $_SESSION['user_id'] != 1) { // Jika ID 1 adalah admin utama, dan yang edit bukan dia sendiri
    $_SESSION['message'] = "Anda tidak diizinkan mengubah akun Admin utama ini.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}
// Jika admin utama mengedit dirinya sendiri, dia tidak bisa mengubah role ke non-admin
if ($is_main_admin_user && $_SESSION['user_id'] == 1 && $role !== 'admin') {
     $_SESSION['message'] = "Admin utama tidak dapat mengubah role-nya sendiri.";
     $_SESSION['message_type'] = "error";
     header("Location: edit_user.php?id=" . urlencode($id_user));
     exit();
}


// Jika ada error validasi, simpan error dan data form ke session lalu redirect
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: edit_user.php?id=" . urlencode($id_user));
    exit();
}

// --- Proses Update ---
$conn->begin_transaction();
$old_user_data_for_rollback = [];
$old_warga_status_for_rollback = [];

try {
    // Ambil data user lama untuk rollback
    $stmt_get_old_user = $conn->prepare("SELECT username, role, nik_warga FROM users WHERE id = ?");
    $stmt_get_old_user->bind_param("i", $id_user);
    $stmt_get_old_user->execute();
    $old_user_data_for_rollback = $stmt_get_old_user->get_result()->fetch_assoc();
    $stmt_get_old_user->close();
    $old_nik_warga = $old_user_data_for_rollback['nik_warga'];


    // Dapatkan status_panitia_lama dari warga (jika ada NIK) sebelum update
    // Ini penting untuk rollback status_panitia jika NIK lama tidak lagi panitia
    if (!empty($old_nik_warga)) {
        $stmt_get_old_panitia_status = $conn->prepare("SELECT status_panitia FROM warga WHERE nik = ?");
        $stmt_get_old_panitia_status->bind_param("s", $old_nik_warga);
        $stmt_get_old_panitia_status->execute();
        $old_warga_status_for_rollback[$old_nik_warga] = $stmt_get_old_panitia_status->get_result()->fetch_assoc()['status_panitia'] ?? 0;
        $stmt_get_old_panitia_status->close();
    }
    // Jika NIK warga baru berbeda dari NIK lama, juga simpan status panitia NIK baru
    if (!empty($nik_warga) && $nik_warga !== $old_nik_warga) {
        $stmt_get_new_panitia_status = $conn->prepare("SELECT status_panitia FROM warga WHERE nik = ?");
        $stmt_get_new_panitia_status->bind_param("s", $nik_warga);
        $stmt_get_new_panitia_status->execute();
        $old_warga_status_for_rollback[$nik_warga] = $stmt_get_new_panitia_status->get_result()->fetch_assoc()['status_panitia'] ?? 0;
        $stmt_get_new_panitia_status->close();
    }


    // 1. Update data user di tabel `users`
    $update_query = "UPDATE users SET username = ?, role = ?, nik_warga = ? ";
    if (!empty($password)) { // Jika password diisi, update password
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

    // 2. Update status_panitia di tabel warga
    // Logika untuk NIK warga LAMA (jika ada)
    if (!empty($old_nik_warga)) {
        // Cek apakah NIK lama masih terhubung dengan user panitia lain (selain user yang diedit)
        $stmt_check_other_panitia = $conn->prepare("SELECT COUNT(*) FROM users WHERE nik_warga = ? AND role = 'panitia' AND id != ?");
        $stmt_check_other_panitia->bind_param("si", $old_nik_warga, $id_user);
        $stmt_check_other_panitia->execute();
        $is_other_panitia = ($stmt_check_other_panitia->get_result()->fetch_row()[0] > 0);
        $stmt_check_other_panitia->close();

        // Jika NIK lama adalah panitia di user yang diedit SEBELUMNYA dan tidak ada panitia lain
        if ($old_user_data_for_rollback['role'] === 'panitia' && !$is_other_panitia) {
            $stmt_reset_old_warga_panitia = $conn->prepare("UPDATE warga SET status_panitia = 0 WHERE nik = ?");
            $stmt_reset_old_warga_panitia->bind_param("s", $old_nik_warga);
            $stmt_reset_old_warga_panitia->execute();
            if ($stmt_reset_old_warga_panitia->error) { throw new mysqli_sql_exception("Error saat mereset status panitia warga lama: " . $stmt_reset_old_warga_panitia->error); }
            $stmt_reset_old_warga_panitia->close();
        }
    }

    // Logika untuk NIK warga BARU (jika ada)
    if (!empty($nik_warga) && $role === 'panitia') {
        $stmt_set_new_warga_panitia = $conn->prepare("UPDATE warga SET status_panitia = 1 WHERE nik = ?");
        $stmt_set_new_warga_panitia->bind_param("s", $nik_warga);
        $stmt_set_new_warga_panitia->execute();
        if ($stmt_set_new_warga_panitia->error) { throw new mysqli_sql_exception("Error saat mengatur status panitia warga baru: " . $stmt_set_new_warga_panitia->error); }
        $stmt_set_new_warga_panitia->close();
    }


    $conn->commit();
    $_SESSION['message'] = "User " . htmlspecialchars($username) . " berhasil diperbarui.";
    $_SESSION['message_type'] = "success";
    header("Location: users.php");
    exit();

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    // Rollback data user
    if (!empty($old_user_data_for_rollback)) {
        $update_rollback_query = "UPDATE users SET username = ?, role = ?, nik_warga = ? WHERE id = ?";
        $stmt_rollback_user = $conn->prepare($update_rollback_query);
        $stmt_rollback_user->bind_param("sssi",
            $old_user_data_for_rollback['username'],
            $old_user_data_for_rollback['role'],
            $old_user_data_for_rollback['nik_warga'],
            $id_user
        );
        $stmt_rollback_user->execute();
        $stmt_rollback_user->close();
    }
    // Rollback status_panitia di warga
    foreach ($old_warga_status_for_rollback as $nik_rollback => $status_rollback) {
        $stmt_rollback_warga_panitia = $conn->prepare("UPDATE warga SET status_panitia = ? WHERE nik = ?");
        $stmt_rollback_warga_panitia->bind_param("is", $status_rollback, $nik_rollback);
        $stmt_rollback_warga_panitia->execute();
        $stmt_rollback_warga_panitia->close();
    }

    $errors[] = "Terjadi kesalahan saat memperbarui user: " . $e->getMessage();
    $_SESSION['message'] = "Gagal memperbarui user: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    $_SESSION['form_data'] = $_POST;
    header("Location: edit_user.php?id=" . urlencode($id_user));
    exit();
}