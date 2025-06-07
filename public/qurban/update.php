<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    redirectToLogin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$id_qurban = sanitizeInput($_POST['id'] ?? '');
$jenis_hewan = sanitizeInput($_POST['jenis_hewan'] ?? '');
$harga = sanitizeInput($_POST['harga'] ?? '');
$biaya_administrasi = sanitizeInput($_POST['biaya_administrasi'] ?? '');
$estimasi_berat_daging_kg = sanitizeInput($_POST['estimasi_berat_daging_kg'] ?? '');
$tanggal_beli = sanitizeInput($_POST['tanggal_beli'] ?? '');
$nik_peserta_kambing_baru = sanitizeInput($_POST['nik_peserta'] ?? ''); // Untuk kambing
$new_peserta_sapi = isset($_POST['peserta']) ? $_POST['peserta'] : []; // Untuk sapi

$errors = [];

// --- Validasi Data ---
if (empty($id_qurban) || !is_numeric($id_qurban)) { $errors[] = "ID hewan qurban tidak valid."; }
if (empty($jenis_hewan) || !in_array($jenis_hewan, ['kambing', 'sapi'])) { $errors[] = "Jenis hewan tidak valid."; }
if (!is_numeric($harga) || $harga <= 0) { $errors[] = "Harga harus angka positif."; }
if (!is_numeric($biaya_administrasi) || $biaya_administrasi < 0) { $errors[] = "Biaya administrasi tidak valid."; }
if (!is_numeric($estimasi_berat_daging_kg) || $estimasi_berat_daging_kg <= 0) { $errors[] = "Estimasi berat daging harus angka positif."; }
if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }

// Validasi Khusus Kambing
if ($jenis_hewan === 'kambing') {
    if (empty($nik_peserta_kambing_baru)) { $errors[] = "Peserta qurban kambing wajib dipilih."; }
    $stmt_check_nik_kambing = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
    $stmt_check_nik_kambing->bind_param("s", $nik_peserta_kambing_baru);
    $stmt_check_nik_kambing->execute();
    if ($stmt_check_nik_kambing->get_result()->fetch_row()[0] == 0) {
        $errors[] = "NIK peserta kambing tidak ditemukan dalam data warga.";
    }
    $stmt_check_nik_kambing->close();
}
// Validasi Khusus Sapi
elseif ($jenis_hewan === 'sapi') {
    if (count($new_peserta_sapi) !== 7) {
        $errors[] = "Qurban sapi harus memiliki tepat 7 peserta.";
    }
    foreach ($new_peserta_sapi as $nik_p) {
        $nik_p_sanitized = sanitizeInput($nik_p);
        $stmt_check_nik_sapi = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
        $stmt_check_nik_sapi->bind_param("s", $nik_p_sanitized);
        $stmt_check_nik_sapi->execute();
        if ($stmt_check_nik_sapi->get_result()->fetch_row()[0] == 0) {
            $errors[] = "NIK peserta sapi " . htmlspecialchars($nik_p_sanitized) . " tidak ditemukan dalam data warga.";
        }
        $stmt_check_nik_sapi->close();
    }
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: edit.php?id=" . urlencode($id_qurban));
    exit();
}

// --- Proses Update ---
$conn->begin_transaction();
$old_participant_niks = []; // Untuk menyimpan NIK peserta lama (baik kambing/sapi)
$old_statuses = []; // Untuk menyimpan status warga lama sebelum diupdate

try {
    // 1. Ambil NIK peserta lama (sebelum update)
    $stmt_get_old_participants = null;
    if ($jenis_hewan === 'kambing') {
        $stmt_get_old_participants = $conn->prepare("SELECT nik_peserta_tunggal FROM hewan_qurban WHERE id = ?");
        $stmt_get_old_participants->bind_param("i", $id_qurban);
    } elseif ($jenis_hewan === 'sapi') {
        $stmt_get_old_participants = $conn->prepare("SELECT nik_warga FROM peserta_sapi WHERE id_hewan_qurban = ?");
        $stmt_get_old_participants->bind_param("i", $id_qurban);
    }

    if ($stmt_get_old_participants) {
        $stmt_get_old_participants->execute();
        $result_old_participants = $stmt_get_old_participants->get_result();
        while($row = $result_old_participants->fetch_assoc()) {
            if ($jenis_hewan === 'kambing') {
                if (!empty($row['nik_peserta_tunggal'])) $old_participant_niks[] = $row['nik_peserta_tunggal'];
            } else { // Sapi
                $old_participant_niks[] = $row['nik_warga'];
            }
        }
        $stmt_get_old_participants->close();
    }

    // 2. Update data hewan qurban
    if ($jenis_hewan === 'kambing') {
        $stmt_update_hewan = $conn->prepare("UPDATE hewan_qurban SET harga = ?, biaya_administrasi = ?, tanggal_beli = ?, estimasi_berat_daging_kg = ?, nik_peserta_tunggal = ? WHERE id = ?");
        $stmt_update_hewan->bind_param("ddsdsi", $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg, $nik_peserta_kambing_baru, $id_qurban);
    } else { // Sapi
        $stmt_update_hewan = $conn->prepare("UPDATE hewan_qurban SET harga = ?, biaya_administrasi = ?, tanggal_beli = ?, estimasi_berat_daging_kg = ? WHERE id = ?");
        $stmt_update_hewan->bind_param("ddsdi", $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg, $id_qurban);
    }
    $stmt_update_hewan->execute();
    if ($stmt_update_hewan->error) { throw new mysqli_sql_exception("Error saat memperbarui data hewan_qurban: " . $stmt_update_hewan->error); }
    $stmt_update_hewan->close();

    // 3. Update data keuangan (pengeluaran pembelian hewan) - Sama seperti sebelumnya
    $stmt_del_keuangan_beli = $conn->prepare("DELETE FROM keuangan WHERE id_hewan_qurban = ? AND jenis = 'pengeluaran' AND keterangan LIKE 'Pembelian Qurban %'");
    $stmt_del_keuangan_beli->bind_param("i", $id_qurban);
    $stmt_del_keuangan_beli->execute();
    $stmt_del_keuangan_beli->close();

    $total_biaya = $harga + $biaya_administrasi;
    $stmt_keuangan_beli = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES (?, ?, ?, ?, ?)");
    $keterangan_beli = "Pembelian Qurban " . ucfirst($jenis_hewan) . " (ID: " . $id_qurban . ") - Diperbarui";
    $jenis_transaksi_keluar = 'pengeluaran';
    $stmt_keuangan_beli->bind_param("ssdsi", $jenis_transaksi_keluar, $keterangan_beli, $total_biaya, $tanggal_beli, $id_qurban);
    $stmt_keuangan_beli->execute();
    if ($stmt_keuangan_beli->error) { throw new mysqli_sql_exception("Error saat memasukkan pengeluaran pembelian setelah update: " . $stmt_keuangan_beli->error); }
    $stmt_keuangan_beli->close();

    // 4. Update pemasukan iuran sesuai jenis hewan - Sama seperti sebelumnya
    $stmt_delete_iuran_keuangan = $conn->prepare("DELETE FROM keuangan WHERE jenis = 'pemasukan' AND (keterangan LIKE CONCAT('Iuran Qurban Sapi ID ', ?, '%') OR keterangan LIKE CONCAT('Iuran Qurban Kambing ID ', ?, '%'))");
    $stmt_delete_iuran_keuangan->bind_param("ii", $id_qurban, $id_qurban);
    $stmt_delete_iuran_keuangan->execute();
    $stmt_delete_iuran_keuangan->close();

    // Dapatkan daftar NIK peserta yang baru
    $current_participant_niks = [];
    if ($jenis_hewan === 'kambing') {
        if (!empty($nik_peserta_kambing_baru)) {
            $current_participant_niks[] = $nik_peserta_kambing_baru;
        }
    } else { // Sapi
        $current_participant_niks = $new_peserta_sapi;
    }

    // Simpan status_qurban asli dari NIK yang akan diubah/dicek, untuk rollback
    foreach (array_unique(array_merge($old_participant_niks, $current_participant_niks)) as $nik_check) {
        if (!empty($nik_check)) {
            $stmt_get_current_status = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
            $stmt_get_current_status->bind_param("s", $nik_check);
            $stmt_get_current_status->execute();
            $result_current_status = $stmt_get_current_status->get_result();
            if ($result_current_status->num_rows > 0) {
                $old_statuses[$nik_check] = $result_current_status->fetch_assoc()['status_qurban'];
            }
            $stmt_get_current_status->close();
        }
    }


    if ($jenis_hewan === 'kambing') {
        $stmt_keuangan_iuran = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
        $keterangan_iuran = "Iuran Qurban Kambing ID " . $id_qurban . " dari NIK " . $nik_peserta_kambing_baru . " - Diperbarui";
        $jenis_transaksi_masuk = 'pemasukan';
        $stmt_keuangan_iuran->bind_param("ssds", $jenis_transaksi_masuk, $keterangan_iuran, $total_biaya, $tanggal_beli);
        $stmt_keuangan_iuran->execute();
        if ($stmt_keuangan_iuran->error) { throw new mysqli_sql_exception("Error saat memasukkan iuran keuangan kambing setelah update: " . $stmt_keuangan_iuran->error); }
        $stmt_keuangan_iuran->close();

        // Update status_qurban warga baru menjadi 'peserta'
        if (!empty($nik_peserta_kambing_baru)) {
            $stmt_update_warga_status = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ? AND status_qurban != 'peserta'");
            $stmt_update_warga_status->bind_param("s", $nik_peserta_kambing_baru);
            $stmt_update_warga_status->execute();
            if ($stmt_update_warga_status->error) { throw new mysqli_sql_exception("Error saat memperbarui status warga (kambing) untuk NIK " . $nik_peserta_kambing_baru . ": " . $stmt_update_warga_status->error); }
            $stmt_update_warga_status->close();
        }

    } elseif ($jenis_hewan === 'sapi') {
        $stmt_delete_peserta = $conn->prepare("DELETE FROM peserta_sapi WHERE id_hewan_qurban = ?");
        $stmt_delete_peserta->bind_param("i", $id_qurban);
        $stmt_delete_peserta->execute();
        $stmt_delete_peserta->close();

        $iuran_per_orang = ($harga / 7) + ($biaya_administrasi / 7);
        $stmt_insert_peserta = $conn->prepare("INSERT INTO peserta_sapi (id_hewan_qurban, nik_warga, jumlah_iuran) VALUES (?, ?, ?)");
        foreach ($new_peserta_sapi as $nik_p_sanitized) {
            $stmt_insert_peserta->bind_param("isd", $id_qurban, $nik_p_sanitized, $iuran_per_orang);
            $stmt_insert_peserta->execute();
            if ($stmt_insert_peserta->error) { throw new mysqli_sql_exception("Error saat memasukkan peserta sapi baru untuk NIK " . $nik_p_sanitized . ": " . $stmt_insert_peserta->error); }

            $stmt_update_warga_status = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ? WHERE status_qurban != 'peserta'");
            $stmt_update_warga_status->bind_param("s", $nik_p_sanitized);
            $stmt_update_warga_status->execute();
            if ($stmt_update_warga_status->error) { throw new mysqli_sql_exception("Error saat memperbarui status warga (sapi) untuk NIK " . $nik_p_sanitized . ": " . $stmt_update_warga_status->error); }
            $stmt_update_warga_status->close();

            $stmt_keuangan_iuran_sapi = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
            $keterangan_iuran_sapi = "Iuran Qurban Sapi ID " . $id_qurban . " dari NIK " . $nik_p_sanitized . " - Diperbarui";
            $jenis_transaksi_masuk_sapi = 'pemasukan';
            $stmt_keuangan_iuran_sapi->bind_param("ssds", $jenis_transaksi_masuk_sapi, $keterangan_iuran_sapi, $iuran_per_orang, $tanggal_beli);
            $stmt_keuangan_iuran_sapi->execute();
            if ($stmt_keuangan_iuran_sapi->error) { throw new mysqli_sql_exception("Error saat memasukkan iuran keuangan sapi baru untuk NIK " . $nik_p_sanitized . ": " . $stmt_keuangan_iuran_sapi->error); }
            $stmt_keuangan_iuran_sapi->close();
        }
        $stmt_insert_peserta->close();
    }

    // 5. Logika untuk menentukan status_qurban warga yang tidak lagi menjadi peserta
    // Ini adalah bagian paling kompleks: menentukan apakah NIK yang 'dilepas' dari qurban
    // masih terikat dengan qurban lain atau harus diubah statusnya
    $all_related_niks = array_unique(array_merge($old_participant_niks, $current_participant_niks));

    foreach ($all_related_niks as $nik_check) {
        if (empty($nik_check)) continue; // Skip if NIK is empty

        // Cek apakah NIK ini masih menjadi peserta di hewan qurban lain (kambing)
        $stmt_check_kambing = $conn->prepare("SELECT COUNT(*) FROM hewan_qurban WHERE nik_peserta_tunggal = ? AND id != ?");
        $stmt_check_kambing->bind_param("si", $nik_check, $id_qurban);
        $stmt_check_kambing->execute();
        $is_kambing_participant_elsewhere = ($stmt_check_kambing->get_result()->fetch_row()[0] > 0);
        $stmt_check_kambing->close();

        // Cek apakah NIK ini masih menjadi peserta di hewan qurban lain (sapi)
        $stmt_check_sapi = $conn->prepare("SELECT COUNT(*) FROM peserta_sapi WHERE nik_warga = ? AND id_hewan_qurban != ?");
        $stmt_check_sapi->bind_param("si", $nik_check, $id_qurban);
        $stmt_check_sapi->execute();
        $is_sapi_participant_elsewhere = ($stmt_check_sapi->get_result()->fetch_row()[0] > 0);
        $stmt_check_sapi->close();

        // Cek apakah NIK ini adalah panitia
        $stmt_check_panitia = $conn->prepare("SELECT status_panitia FROM warga WHERE nik = ?");
        $stmt_check_panitia->bind_param("s", $nik_check);
        $stmt_check_panitia->execute();
        $is_panitia = ($stmt_check_panitia->get_result()->fetch_assoc()['status_panitia'] ?? 0);
        $stmt_check_panitia->close();


        // Tentukan status baru
        $new_status_qurban_for_warga = '';
        if ($is_kambing_participant_elsewhere || $is_sapi_participant_elsewhere) {
            $new_status_qurban_for_warga = 'peserta'; // Masih peserta di qurban lain
        } elseif ($is_panitia) {
            // Jika bukan peserta qurban lagi tapi dia panitia, statusnya tetap 'panitia' (bukan di status_qurban)
            // status_qurban bisa jadi 'penerima' jika panitia secara default menerima
            // Kita asumsikan panitia otomatis berhak menerima, jadi 'penerima'
            $new_status_qurban_for_warga = 'penerima';
        } else {
            // Jika tidak lagi peserta qurban mana pun, dan bukan panitia, maka jadi 'penerima'
            $new_status_qurban_for_warga = 'penerima';
        }

        // Hanya update jika statusnya benar-benar berubah dan tidak menyebabkan masalah
        if ($old_statuses[$nik_check] !== $new_status_qurban_for_warga) {
            $stmt_update_final_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
            $stmt_update_final_status->bind_param("ss", $new_status_qurban_for_warga, $nik_check);
            $stmt_update_final_status->execute();
            if ($stmt_update_final_status->error) {
                throw new mysqli_sql_exception("Error saat memperbarui status_qurban final untuk NIK " . $nik_check . ": " . $stmt_update_final_status->error);
            }
            $stmt_update_final_status->close();
        }
    }


    $conn->commit();
    $_SESSION['message'] = "Data qurban " . ucfirst($jenis_hewan) . " berhasil diperbarui.";
    $_SESSION['message_type'] = "success";
    header("Location: index.php");
    exit();

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    // Rollback status_qurban yang sudah diubah (jika ada)
    foreach ($old_statuses as $nik_check => $old_status) {
        $stmt_rollback_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
        $stmt_rollback_status->bind_param("ss", $old_status, $nik_check);
        $stmt_rollback_status->execute();
        $stmt_rollback_status->close();
    }
    $errors[] = "Terjadi kesalahan saat memperbarui data qurban: " . $e->getMessage();
    $_SESSION['message'] = "Gagal memperbarui data qurban: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    $_SESSION['form_data'] = $_POST;
    header("Location: edit.php?id=" . urlencode($id_qurban));
    exit();
}