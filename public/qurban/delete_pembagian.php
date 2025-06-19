<?php
session_start();

include '../../includes/db.php';
include '../../includes/functions.php';

if (!isAdmin() && !isPanitia()) {
    redirectToLogin(); 
}

if (isset($_GET['id'])) {
    $id_to_delete = sanitizeInput($_GET['id']);

    $stmt_delete = $conn->prepare("DELETE FROM pembagian_daging WHERE id = ?");
    $stmt_delete->bind_param("i", $id_to_delete);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $_SESSION['message'] = "Catatan pembagian daging berhasil dihapus.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal menghapus catatan pembagian atau tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Gagal menghapus catatan pembagian: " . $stmt_delete->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt_delete->close();
} else {
    $_SESSION['message'] = "ID catatan pembagian tidak ditemukan untuk dihapus.";
    $_SESSION['message_type'] = "error";
}

header("Location: pembagian.php");
exit();
?>