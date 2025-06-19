<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include '../../includes/db.php';
include '../../includes/functions.php';

if (!isAdmin() && !isPanitia()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_to_delete = sanitizeInput($_GET['id']);

    $conn->begin_transaction();
    $old_perlengkapan_nama = ''; 
    $old_keuangan_data = []; 

    try {
        $stmt_get_perlengkapan_info = $conn->prepare("SELECT nama_barang FROM perlengkapan WHERE id = ?");
        $stmt_get_perlengkapan_info->bind_param("i", $id_to_delete);
        $stmt_get_perlengkapan_info->execute();
        $perlengkapan_info = $stmt_get_perlengkapan_info->get_result()->fetch_assoc();
        $old_perlengkapan_nama = $perlengkapan_info['nama_barang'] ?? '';
        $stmt_get_perlengkapan_info->close();

        $stmt_get_keuangan_id = $conn->prepare("SELECT id, jenis, keterangan, jumlah, tanggal FROM keuangan WHERE keterangan LIKE CONCAT('Pembelian Perlengkapan: ', ?, '%') ORDER BY id DESC LIMIT 1");
        $stmt_get_keuangan_id->bind_param("s", $old_perlengkapan_nama);
        $stmt_get_keuangan_id->execute();
        $keuangan_data = $stmt_get_keuangan_id->get_result()->fetch_assoc();
        $stmt_get_keuangan_id->close();

        if ($keuangan_data) {
            $old_keuangan_data = $keuangan_data; 
            $stmt_delete_keuangan = $conn->prepare("DELETE FROM keuangan WHERE id = ?");
            $stmt_delete_keuangan->bind_param("i", $keuangan_data['id']);
            $stmt_delete_keuangan->execute();
            if ($stmt_delete_keuangan->error) {
                throw new mysqli_sql_exception("Error saat menghapus transaksi keuangan terkait: " . $stmt_delete_keuangan->error);
            }
            $stmt_delete_keuangan->close();
        }

        $stmt_delete_perlengkapan = $conn->prepare("DELETE FROM perlengkapan WHERE id = ?");
        $stmt_delete_perlengkapan->bind_param("i", $id_to_delete);
        $stmt_delete_perlengkapan->execute();

        if ($stmt_delete_perlengkapan->affected_rows > 0) {
            $conn->commit();
            $_SESSION['message'] = "Perlengkapan berhasil dihapus.";
            $_SESSION['message_type'] = "success";
        } else {
            $conn->rollback();
            $_SESSION['message'] = "Gagal menghapus perlengkapan atau tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_perlengkapan->close();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if (!empty($old_keuangan_data)) {
            $stmt_rollback_keuangan = $conn->prepare("INSERT INTO keuangan (id, jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?, ?)");
            $stmt_rollback_keuangan->bind_param("isds",
                $old_keuangan_data['id'],
                $old_keuangan_data['jenis'],
                $old_keuangan_data['keterangan'],
                $old_keuangan_data['jumlah'],
                $old_keuangan_data['tanggal']
            );
            $stmt_rollback_keuangan->execute();
            $stmt_rollback_keuangan->close();
        }
        $errors[] = "Terjadi kesalahan saat menghapus perlengkapan: " . $e->getMessage();
        $_SESSION['message'] = "Gagal menghapus perlengkapan: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "ID perlengkapan tidak ditemukan untuk dihapus.";
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
