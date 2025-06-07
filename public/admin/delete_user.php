<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include '../../includes/db.php';
include '../../includes/functions.php';

if (!isAdmin()) {
    redirectToLogin();
}

if (isset($_GET['id'])) {
    $id_to_delete = sanitizeInput($_GET['id']);

    // Pencegahan: Tidak bisa menghapus akun admin utama (ID 1)
    if ($id_to_delete == 1) {
        $_SESSION['message'] = "Akun Admin utama tidak dapat dihapus.";
        $_SESSION['message_type'] = "error";
        header("Location: users.php");
        exit();
    }
    // Pencegahan: Admin tidak bisa menghapus dirinya sendiri (akun yang sedang login)
    if ($id_to_delete == $_SESSION['user_id']) {
        $_SESSION['message'] = "Anda tidak dapat menghapus akun Anda sendiri.";
        $_SESSION['message_type'] = "error";
        header("Location: users.php");
        exit();
    }


    $conn->begin_transaction();
    $old_nik_warga = NULL; // Untuk menyimpan NIK warga yang terkait
    $old_status_panitia = 0; // Untuk rollback status_panitia

    try {
        // 1. Ambil NIK warga yang terkait dan status panitia lama
        $stmt_get_user_info = $conn->prepare("SELECT nik_warga, role FROM users WHERE id = ?");
        $stmt_get_user_info->bind_param("i", $id_to_delete);
        $stmt_get_user_info->execute();
        $user_info = $stmt_get_user_info->get_result()->fetch_assoc();
        $old_nik_warga = $user_info['nik_warga'] ?? NULL;
        $user_role_to_delete = $user_info['role'] ?? '';
        $stmt_get_user_info->close();

        if (!empty($old_nik_warga)) {
            $stmt_get_old_panitia_status = $conn->prepare("SELECT status_panitia FROM warga WHERE nik = ?");
            $stmt_get_old_panitia_status->bind_param("s", $old_nik_warga);
            $stmt_get_old_panitia_status->execute();
            $old_status_panitia = $stmt_get_old_panitia_status->get_result()->fetch_assoc()['status_panitia'] ?? 0;
            $stmt_get_old_panitia_status->close();
        }

        // 2. Hapus user dari tabel `users`
        $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete_user->bind_param("i", $id_to_delete);
        $stmt_delete_user->execute();

        if ($stmt_delete_user->affected_rows > 0) {
            // 3. Update status_panitia di tabel warga jika user yang dihapus adalah panitia
            if ($user_role_to_delete === 'panitia' && !empty($old_nik_warga)) {
                // Cek apakah NIK warga ini masih terhubung dengan user panitia lain
                $stmt_check_other_panitia = $conn->prepare("SELECT COUNT(*) FROM users WHERE nik_warga = ? AND role = 'panitia'");
                $stmt_check_other_panitia->bind_param("s", $old_nik_warga);
                $stmt_check_other_panitia->execute();
                $is_other_panitia_still_active = ($stmt_check_other_panitia->get_result()->fetch_row()[0] > 0);
                $stmt_check_other_panitia->close();

                // Jika tidak ada user panitia lain yang terhubung ke NIK ini, reset status_panitia warga
                if (!$is_other_panitia_still_active) {
                    $stmt_update_warga_panitia = $conn->prepare("UPDATE warga SET status_panitia = 0 WHERE nik = ?");
                    $stmt_update_warga_panitia->bind_param("s", $old_nik_warga);
                    $stmt_update_warga_panitia->execute();
                    if ($stmt_update_warga_panitia->error) {
                        throw new mysqli_sql_exception("Error saat mereset status panitia warga: " . $stmt_update_warga_panitia->error);
                    }
                    $stmt_update_warga_panitia->close();
                }
            }

            $conn->commit();
            $_SESSION['message'] = "User berhasil dihapus.";
            $_SESSION['message_type'] = "success";
        } else {
            $conn->rollback();
            $_SESSION['message'] = "Gagal menghapus user atau user tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_user->close();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        // Rollback status_panitia warga jika terjadi error
        if (!empty($old_nik_warga)) {
            $stmt_rollback_panitia = $conn->prepare("UPDATE warga SET status_panitia = ? WHERE nik = ?");
            $stmt_rollback_panitia->bind_param("is", $old_status_panitia, $old_nik_warga);
            $stmt_rollback_panitia->execute();
            $stmt_rollback_panitia->close();
        }
        $_SESSION['message'] = "Terjadi kesalahan saat menghapus user: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "ID user tidak ditemukan untuk dihapus.";
    $_SESSION['message_type'] = "error";
}

header("Location: users.php");
exit();
?>