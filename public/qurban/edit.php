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

if (!isLoggedIn()) {
    redirectToLogin();
}

$id_qurban = ''; // ID hewan qurban yang akan diedit (dari GET atau POST)
$jenis_hewan = $harga = $biaya_administrasi = $estimasi_berat_daging_kg = '';
$tanggal_beli = date('Y-m-d');
$nik_peserta = ''; // NIK peserta kambing (untuk tampilan form)
$selected_peserta = []; // Array untuk menyimpan NIK peserta sapi yang sudah ada (untuk tampilan form)
$errors = []; // Untuk menampilkan error validasi

// Ambil ID qurban dari URL (GET) jika ini request awal
if (isset($_GET['id'])) {
    $id_qurban = sanitizeInput($_GET['id']);
}
// Jika ini adalah POST request (form disubmit), ambil ID dari hidden field
elseif (isset($_POST['id'])) {
    $id_qurban = sanitizeInput($_POST['id']);
}
// Jika tidak ada ID sama sekali, redirect
else {
    $_SESSION['message'] = "ID hewan qurban tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Proses form jika ada data yang dikirim (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari POST
    $id_qurban = sanitizeInput($_POST['id'] ?? '');
    $jenis_hewan = sanitizeInput($_POST['jenis_hewan'] ?? '');
    $harga = sanitizeInput($_POST['harga'] ?? '');
    $biaya_administrasi = sanitizeInput($_POST['biaya_administrasi'] ?? '');
    $estimasi_berat_daging_kg = sanitizeInput($_POST['estimasi_berat_daging_kg'] ?? '');
    $tanggal_beli = sanitizeInput($_POST['tanggal_beli'] ?? '');
    $nik_peserta_kambing_baru = sanitizeInput($_POST['nik_peserta'] ?? '');
    $new_peserta_sapi = isset($_POST['peserta']) ? $_POST['peserta'] : [];

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

    // Jika ada error validasi, simpan data POST ke session dan tampilkan di form
    if (!empty($errors)) {
        $_SESSION['form_data'] = $_POST;
        header("Location: edit.php?id=" . urlencode($id_qurban));
        exit();
    }

    // --- Proses Update (jika validasi berhasil) ---
    $conn->begin_transaction();
    $old_participant_niks = []; // Untuk menyimpan NIK peserta lama (baik kambing/sapi)
    $old_statuses_for_rollback = []; // Untuk menyimpan status_qurban warga asli untuk rollback

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

        // 3. Update data keuangan (pengeluaran pembelian hewan)
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

        // 4. Update pemasukan iuran sesuai jenis hewan
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

        // --- KOREKSI UTAMA: Kelola status_qurban untuk SEMUA NIK yang relevan ---
        // Kumpulkan semua NIK yang terlibat (lama dan baru)
        $all_involved_niks = array_unique(array_merge($old_participant_niks, $current_participant_niks));

        // Simpan status_qurban asli dari NIK yang akan diubah/dicek, untuk rollback
        foreach ($all_involved_niks as $nik_check) {
            if (!empty($nik_check)) {
                $stmt_get_current_status = $conn->prepare("SELECT status_qurban FROM warga WHERE nik = ?");
                $stmt_get_current_status->bind_param("s", $nik_check);
                $stmt_get_current_status->execute();
                $result_current_status = $stmt_get_current_status->get_result();
                if ($result_current_status->num_rows > 0) {
                    $old_statuses_for_rollback[$nik_check] = $result_current_status->fetch_assoc()['status_qurban'];
                } else {
                    $old_statuses_for_rollback[$nik_check] = 'penerima'; // Fallback jika NIK tidak ditemukan
                }
                $stmt_get_current_status->close();
            }
        }

        // Proses khusus untuk peserta baru (set ke 'peserta') dan iuran
        if ($jenis_hewan === 'kambing') {
            if (!empty($nik_peserta_kambing_baru)) {
                $stmt_keuangan_iuran = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
                $keterangan_iuran = "Iuran Qurban Kambing ID " . $id_qurban . " dari NIK " . $nik_peserta_kambing_baru . " - Diperbarui";
                $jenis_transaksi_masuk = 'pemasukan';
                $stmt_keuangan_iuran->bind_param("ssds", $jenis_transaksi_masuk, $keterangan_iuran, $total_biaya, $tanggal_beli);
                $stmt_keuangan_iuran->execute();
                if ($stmt_keuangan_iuran->error) { throw new mysqli_sql_exception("Error saat memasukkan iuran keuangan kambing setelah update: " . $stmt_keuangan_iuran->error); }
                $stmt_keuangan_iuran->close();
            }
        } elseif ($jenis_hewan === 'sapi') {
            $stmt_delete_peserta_sapi_current = $conn->prepare("DELETE FROM peserta_sapi WHERE id_hewan_qurban = ?");
            $stmt_delete_peserta_sapi_current->bind_param("i", $id_qurban);
            $stmt_delete_peserta_sapi_current->execute();
            if ($stmt_delete_peserta_sapi_current->error) { throw new mysqli_sql_exception("Error saat menghapus peserta sapi lama: " . $stmt_delete_peserta_sapi_current->error); }
            $stmt_delete_peserta_sapi_current->close();

            $iuran_per_orang = ($harga / 7) + ($biaya_administrasi / 7);
            $stmt_insert_peserta = $conn->prepare("INSERT INTO peserta_sapi (id_hewan_qurban, nik_warga, jumlah_iuran) VALUES (?, ?, ?)");
            foreach ($new_peserta_sapi as $nik_p_sanitized) {
                $stmt_insert_peserta->bind_param("isd", $id_qurban, $nik_p_sanitized, $iuran_per_orang);
                $stmt_insert_peserta->execute();
                if ($stmt_insert_peserta->error) { throw new mysqli_sql_exception("Error saat memasukkan peserta sapi baru untuk NIK " . $nik_p_sanitized . ": " . $stmt_insert_peserta->error); }

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

        // FINAL KOREKSI: Perbarui status_qurban untuk semua NIK yang terlibat
        // Ini adalah loop yang akan menentukan status akhir untuk setiap NIK yang berubah
        // atau mungkin tetap sama, setelah semua perubahan qurban ini.
        foreach ($all_involved_niks as $nik_check) {
            if (empty($nik_check)) continue;

            // Cek apakah NIK ini TERMASUK dalam peserta yang BARU (akan jadi 'peserta')
            $is_current_participant = in_array($nik_check, $current_participant_niks);

            // Cek apakah NIK ini masih terdaftar sebagai peserta di Qurban lain (selain yang sedang diedit)
            $stmt_check_other_kambing = $conn->prepare("SELECT COUNT(*) FROM hewan_qurban WHERE nik_peserta_tunggal = ? AND id != ?");
            $stmt_check_other_kambing->bind_param("si", $nik_check, $id_qurban);
            $stmt_check_other_kambing->execute();
            $is_kambing_participant_elsewhere = ($stmt_check_other_kambing->get_result()->fetch_row()[0] > 0);
            $stmt_check_other_kambing->close();

            $stmt_check_other_sapi = $conn->prepare("SELECT COUNT(*) FROM peserta_sapi WHERE nik_warga = ? AND id_hewan_qurban != ?");
            $stmt_check_other_sapi->bind_param("si", $nik_check, $id_qurban);
            $stmt_check_other_sapi->execute();
            $is_sapi_participant_elsewhere = ($stmt_check_other_sapi->get_result()->fetch_row()[0] > 0);
            $stmt_check_other_sapi->close();
            
            $is_participant_anywhere = $is_current_participant || $is_kambing_participant_elsewhere || $is_sapi_participant_elsewhere;

            // Cek apakah NIK ini adalah panitia
            $stmt_check_panitia = $conn->prepare("SELECT status_panitia FROM warga WHERE nik = ?");
            $stmt_check_panitia->bind_param("s", $nik_check);
            $stmt_check_panitia->execute();
            $is_panitia = ($stmt_check_panitia->get_result()->fetch_assoc()['status_panitia'] ?? 0);
            $stmt_check_panitia->close();

            $new_status_qurban_for_warga = '';
            if ($is_participant_anywhere) {
                $new_status_qurban_for_warga = 'peserta';
            } elseif ($is_panitia) {
                $new_status_qurban_for_warga = 'penerima'; // Panitia yang bukan peserta qurban, asumsikan penerima
            } else {
                $new_status_qurban_for_warga = 'penerima'; // Default jika tidak ada status khusus
            }

            // Dapatkan status_qurban dari DB SEBELUM UPDATE ini
            $current_db_status_qurban = $old_statuses_for_rollback[$nik_check] ?? 'penerima'; // Fallback

            if ($current_db_status_qurban !== $new_status_qurban_for_warga) {
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
        header("Location: index.php"); // Redirect ke halaman daftar hewan qurban
        exit();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        // Rollback status_qurban jika terjadi error
        foreach ($old_statuses_for_rollback as $nik_check => $old_status) {
            $stmt_rollback_status = $conn->prepare("UPDATE warga SET status_qurban = ? WHERE nik = ?");
            $stmt_rollback_status->bind_param("ss", $old_status, $nik_check);
            $stmt_rollback_status->execute();
            $stmt_rollback_status->close();
        }
        $errors[] = "Terjadi kesalahan saat memperbarui data qurban: " . $e->getMessage();
        $_SESSION['message'] = "Gagal memperbarui data qurban: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        $_SESSION['form_data'] = $_POST; // Simpan data POST untuk ditampilkan kembali di form
        header("Location: edit.php?id=" . urlencode($id_qurban)); // Redirect kembali ke form edit
        exit();
    }
}


// =========================================================================
// Bagian Pengambilan Data untuk Tampilan Form (Jika bukan POST atau ada error POST)
// =========================================================================

// Ambil data hewan qurban dari database berdasarkan ID
$stmt_get_data = $conn->prepare("SELECT id, jenis_hewan, harga, biaya_administrasi, tanggal_beli, estimasi_berat_daging_kg, nik_peserta_tunggal FROM hewan_qurban WHERE id = ?");
$stmt_get_data->bind_param("i", $id_qurban);
$stmt_get_data->execute();
$result_get_data = $stmt_get_data->get_result();

if ($result_get_data->num_rows > 0) {
    $qurban_data = $result_get_data->fetch_assoc();
    $jenis_hewan = $qurban_data['jenis_hewan'];
    $harga = $qurban_data['harga'];
    $biaya_administrasi = $qurban_data['biaya_administrasi'];
    $estimasi_berat_daging_kg = $qurban_data['estimasi_berat_daging_kg'];
    $tanggal_beli = $qurban_data['tanggal_beli'];

    // Jika kambing, ambil NIK pesertanya
    if ($jenis_hewan === 'kambing') {
        $nik_peserta = $qurban_data['nik_peserta_tunggal'];
    }
    // Jika sapi, ambil NIK peserta sapi
    elseif ($jenis_hewan === 'sapi') {
        $sql_current_peserta = "SELECT nik_warga FROM peserta_sapi WHERE id_hewan_qurban = ?";
        $stmt_current_peserta = $conn->prepare($sql_current_peserta);
        $stmt_current_peserta->bind_param("i", $id_qurban);
        $stmt_current_peserta->execute();
        $result_current_peserta = $stmt_current_peserta->get_result();
        while($p = $result_current_peserta->fetch_assoc()) {
            $selected_peserta[] = $p['nik_warga'];
        }
        $stmt_current_peserta->close();
    }
} else {
    $_SESSION['message'] = "Data hewan qurban tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$stmt_get_data->close();

// Jika ada data form dari session (setelah redirect karena error validasi), gunakan data itu
// Ini akan menimpa nilai dari DB dengan nilai dari POST jika ada error
if (isset($_SESSION['form_data'])) {
    $harga = $_SESSION['form_data']['harga'] ?? $harga;
    $biaya_administrasi = $_SESSION['form_data']['biaya_administrasi'] ?? $biaya_administrasi;
    $estimasi_berat_daging_kg = $_SESSION['form_data']['estimasi_berat_daging_kg'] ?? $estimasi_berat_daging_kg;
    $tanggal_beli = $_SESSION['form_data']['tanggal_beli'] ?? $tanggal_beli;
    if ($jenis_hewan === 'kambing') {
        $nik_peserta = $_SESSION['form_data']['nik_peserta'] ?? $nik_peserta;
    } elseif ($jenis_hewan === 'sapi') {
        $selected_peserta = $_SESSION['form_data']['peserta'] ?? $selected_peserta;
    }
    unset($_SESSION['form_data']);
}
// Ambil pesan error dari session jika ada
if (isset($_SESSION['errors'])) {
    $errors = array_merge($errors, $_SESSION['errors']);
    unset($_SESSION['errors']);
}


// Ambil daftar warga untuk dropdown peserta (baik kambing atau sapi)
$sql_warga_peserta_select = "SELECT nik, nama, status_qurban, status_panitia FROM warga ORDER BY nama ASC";
$result_warga_peserta_select = $conn->query($sql_warga_peserta_select);
$list_warga = [];
if ($result_warga_peserta_select && $result_warga_peserta_select->num_rows > 0) {
    while($row = $result_warga_peserta_select->fetch_assoc()) {
        $list_warga[] = $row;
    }
}


// =========================================================================
// Bagian Tampilan HTML
// =========================================================================
include '../../includes/header.php'; // Sertakan header setelah semua logika PHP selesai
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Data Qurban <?php echo htmlspecialchars(ucfirst($jenis_hewan)); ?></h1>
</div>

<?php
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
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . ($_SESSION['message_type'] == 'error' ? 'danger' : ($_SESSION['message_type'] == 'info' ? 'info' : 'success')) . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['message']);
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Edit Hewan Qurban</h6>
    </div>
    <div class="card-body">
        <form action="" method="POST"> <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_qurban); ?>">
            <input type="hidden" name="jenis_hewan" value="<?php echo htmlspecialchars($jenis_hewan); ?>">

            <div class="form-group">
                <label for="jenis_hewan_display">Jenis Hewan:</label>
                <input type="text" class="form-control" id="jenis_hewan_display" value="<?php echo htmlspecialchars(ucfirst($jenis_hewan)); ?>" readonly>
                <small class="form-text text-muted">Jenis hewan tidak dapat diubah setelah dibuat.</small>
            </div>
            <div class="form-group">
                <label for="harga">Harga:</label>
                <input type="number" class="form-control" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" step="10000" required>
            </div>
            <div class="form-group">
                <label for="biaya_administrasi">Biaya Administrasi:</label>
                <input type="number" class="form-control" id="biaya_administrasi" name="biaya_administrasi" value="<?php echo htmlspecialchars($biaya_administrasi); ?>" step="10000" required>
            </div>
            <div class="form-group">
                <label for="estimasi_berat_daging_kg">Estimasi Berat Daging (kg):</label>
                <input type="number" class="form-control" id="estimasi_berat_daging_kg" name="estimasi_berat_daging_kg" value="<?php echo htmlspecialchars($estimasi_berat_daging_kg); ?>" step="0.1" required>
            </div>
            <div class="form-group">
                <label for="tanggal_beli">Tanggal Beli:</label>
                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
            </div>

            <?php if ($jenis_hewan === 'kambing'): ?>
                <div class="form-group">
                    <label for="nik_peserta">Peserta Qurban Kambing (1 Orang):</label>
                    <select class="form-control" id="nik_peserta" name="nik_peserta" required>
                        <option value="">-- Pilih Warga --</option>
                        <?php foreach ($list_warga as $warga): ?>
                            <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_peserta == $warga['nik']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($list_warga)): ?>
                        <p class="text-danger mt-2">Tidak ada warga yang terdaftar sebagai peserta atau panitia.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($jenis_hewan === 'sapi'): ?>
                <div id="peserta_sapi_section" class="form-group">
                    <label>Pilih 7 Peserta Qurban Sapi:</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                    <?php if (!empty($list_warga)): ?>
                        <?php foreach ($list_warga as $warga): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="peserta_<?php echo htmlspecialchars($warga['nik']); ?>" name="peserta[]" value="<?php echo htmlspecialchars($warga['nik']); ?>"
                                <?php echo in_array($warga['nik'], $selected_peserta) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="peserta_<?php echo htmlspecialchars($warga['nik']); ?>"><?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)</label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-danger mt-2">Tidak ada warga yang terdaftar sebagai peserta atau panitia.</p>
                    <?php endif; ?>
                    </div>
                    <small class="form-text text-muted">Pilih **tepat 7 orang** untuk qurban sapi.</small>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
