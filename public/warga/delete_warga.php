<?php
session_start(); // Pastikan session dimulai

include '../../includes/db.php';
include '../../includes/functions.php';

// Hanya admin yang bisa menghapus
if (!isAdmin()) {
    redirectToLogin(); // Atau halaman error unauthorized
}

if (isset($_GET['nik'])) {
    $nik_to_delete = sanitizeInput($_GET['nik']);

    // Mulai transaksi untuk memastikan konsistensi data
    $conn->begin_transaction();
    try {
        // Hapus juga user login yang terkait dengan NIK ini
        $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE nik_warga = ?");
        $stmt_delete_user->bind_param("s", $nik_to_delete);
        $stmt_delete_user->execute();
        $stmt_delete_user->close();

        // Hapus data warga
        $stmt_delete_warga = $conn->prepare("DELETE FROM warga WHERE nik = ?");
        $stmt_delete_warga->bind_param("s", $nik_to_delete);
        $stmt_delete_warga->execute();

        if ($stmt_delete_warga->affected_rows > 0) {
            $conn->commit(); // Komit transaksi jika berhasil
            $_SESSION['message'] = "Data warga dan akun login terkait berhasil dihapus.";
            $_SESSION['message_type'] = "success";
        } else {
            $conn->rollback(); // Rollback jika tidak ada baris yang terpengaruh
            $_SESSION['message'] = "Gagal menghapus data warga atau warga tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_warga->close();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback(); // Rollback jika ada error
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