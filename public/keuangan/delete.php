<?php
session_start();

include '../../includes/db.php';
include '../../includes/functions.php';

if (!isAdmin()) { // Hanya Admin yang bisa menghapus transaksi keuangan
    redirectToLogin();
}

if (isset($_GET['id'])) {
    $id_to_delete = sanitizeInput($_GET['id']);

    // Cek apakah transaksi ini terkait dengan hewan qurban
    // Transaksi terkait hewan qurban sebaiknya tidak dihapus manual
    $stmt_check_relation = $conn->prepare("SELECT id_hewan_qurban FROM keuangan WHERE id = ?");
    $stmt_check_relation->bind_param("i", $id_to_delete);
    $stmt_check_relation->execute();
    $result_check_relation = $stmt_check_relation->get_result();
    $transaksi_data = $result_check_relation->fetch_assoc();
    $stmt_check_relation->close();

    if (!empty($transaksi_data['id_hewan_qurban'])) {
        $_SESSION['message'] = "Transaksi ini terkait dengan pembelian/iuran hewan qurban dan tidak dapat dihapus secara manual. Silakan hapus melalui menu Data Qurban jika diperlukan.";
        $_SESSION['message_type'] = "error";
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM keuangan WHERE id = ?");
        $stmt_delete->bind_param("i", $id_to_delete);

        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $_SESSION['message'] = "Transaksi keuangan berhasil dihapus.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Gagal menghapus transaksi atau transaksi tidak ditemukan.";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Gagal menghapus transaksi: " . $stmt_delete->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete->close();
    }
} else {
    $_SESSION['message'] = "ID transaksi tidak ditemukan untuk dihapus.";
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>