<?php
session_start(); 

include '../../includes/db.php';
include '../../includes/functions.php';

if (!isAdmin()) {
    redirectToLogin(); 
}

if (isset($_GET['nik'])) {
    $nik_to_delete = sanitizeInput($_GET['nik']);

    $conn->begin_transaction();
    try {
        $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE nik_warga = ?");
        $stmt_delete_user->bind_param("s", $nik_to_delete);
        $stmt_delete_user->execute();
        $stmt_delete_user->close();

        $stmt_delete_warga = $conn->prepare("DELETE FROM warga WHERE nik = ?");
        $stmt_delete_warga->bind_param("s", $nik_to_delete);
        $stmt_delete_warga->execute();

        if ($stmt_delete_warga->affected_rows > 0) {
            $conn->commit(); 
            $_SESSION['message'] = "Data warga dan akun login terkait berhasil dihapus.";
            $_SESSION['message_type'] = "success";
        } else {
            $conn->rollback(); 
            $_SESSION['message'] = "Gagal menghapus data warga atau warga tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_warga->close();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback(); 
        $_SESSION['message'] = "Terjadi kesalahan saat menghapus data warga: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }

} else {
    $_SESSION['message'] = "NIK tidak ditemukan untuk dihapus.";
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>