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

if (!isLoggedIn()) {
    redirectToLogin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$nik_original = sanitizeInput($_POST['nik_original'] ?? '');
$nik = sanitizeInput($_POST['nik'] ?? '');
$nama = sanitizeInput($_POST['nama'] ?? '');
$alamat = sanitizeInput($_POST['alamat'] ?? '');
$no_hp = sanitizeInput($_POST['no_hp'] ?? '');
// status_qurban dan status_panitia TIDAK lagi diambil langsung dari form POST
// karena mereka dikelola otomatis atau dari tabel lain

$errors = [];

// Validasi
if (empty($nik_original)) { $errors[] = "NIK original tidak ditemukan."; }
if (empty($nik)) { $errors[] = "NIK wajib diisi."; }
if (empty($nama)) { $errors[] = "Nama wajib diisi."; }
if (!preg_match('/^[0-9]{16}$/', $nik)) { $errors[] = "NIK harus 16 digit angka."; }

// Cek duplikasi NIK baru jika NIK diubah
if ($nik !== $nik_original) {
    $stmt_check_nik = $conn->prepare("SELECT nik FROM warga WHERE nik = ?");
    $stmt_check_nik->bind_param("s", $nik);
    $stmt_check_nik->execute();
    $stmt_check_nik->store_result();
    if ($stmt_check_nik->num_rows > 0) {
        $errors[] = "NIK baru sudah terdaftar. Gunakan NIK yang berbeda.";
    }
    $stmt_check_nik->close();
}

// Jika ada error validasi, simpan error dan data form ke session lalu redirect kembali ke edit.php
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST; // Simpan data POST yang lama untuk redisplay di form
    header("Location: edit.php?nik=" . urlencode($nik_original));
    exit();
}

// --- Proses Update ---
$conn->begin_transaction();
$old_warga_data_for_rollback = []; // Untuk menyimpan data warga lama jika perlu rollback

try {
    // 1. Ambil data warga lama (termasuk status_qurban dan status_panitia) untuk rollback
    $stmt_get_old_warga = $conn->prepare("SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga WHERE nik = ?");
    $stmt_get_old_warga->bind_param("s", $nik_original);
    $stmt_get_old_warga->execute();
    $result_old_warga = $stmt_get_old_warga->get_result();
    if ($result_old_warga->num_rows > 0) {
        $old_warga_data_for_rollback = $result_old_warga->fetch_assoc();
    } else {
        throw new mysqli_sql_exception("Data warga asli tidak ditemukan untuk update.");
    }
    $stmt_get_old_warga->close();

    // 2. Tentukan status_qurban dan status_panitia yang AKAN disimpan ke DB
    // Karena kita tidak lagi mengambil dari form, kita akan ambil dari DB yang lama
    // atau biarkan sistem lain yang mengatur.
    // Untuk 'status_qurban', kita biarkan sistem modul qurban yang mengatur
    // Untuk 'status_panitia', kita bisa tambahkan fitur di admin/user.php untuk mengubahnya secara manual
    // Jadi di sini, kita hanya mengupdate data inti warga, BUKAN status_qurban/status_panitia.
    // Jika memang harus bisa diubah dari form warga, kita harus cek lagi keterikatan.
    // Untuk kesederhanaan, `status_qurban` dan `status_panitia` tidak diubah dari `update_warga.php`
    // melainkan dari modul qurban (untuk status_qurban) atau modul admin (untuk status_panitia).

    // Maka, query UPDATE hanya untuk NIK, nama, alamat, no_hp
    $stmt = $conn->prepare("UPDATE warga SET nik = ?, nama = ?, alamat = ?, no_hp = ? WHERE nik = ?");
    $stmt->bind_param("sssss", $nik, $nama, $alamat, $no_hp, $nik_original);
    $stmt->execute();
    if ($stmt->error) {
        throw new mysqli_sql_exception("Error saat memperbarui data warga: " . $stmt->error);
    }
    $stmt->close();

    // 3. Update atau buat user login
    $username_user = $nik; // Username berdasarkan NIK baru

    // Dapatkan status_qurban dan status_panitia TERBARU dari DB setelah update NIK (jika berubah)
    $stmt_get_current_status = $conn->prepare("SELECT status_qurban, status_panitia FROM warga WHERE nik = ?");
    $stmt_get_current_status->bind_param("s", $nik);
    $stmt_get_current_status->execute();
    $current_warga_status_after_update = $stmt_get_current_status->get_result()->fetch_assoc();
    $actual_status_qurban = $current_warga_status_after_update['status_qurban'];
    $actual_status_panitia = $current_warga_status_after_update['status_panitia'];
    $stmt_get_current_status->close();

    // Logika penentuan role user (sama seperti sebelumnya, prioritas: panitia > berqurban > warga)
    $role_user_final = '';
    if ($actual_status_panitia === 1) {
        $role_user_final = 'panitia';
    } elseif ($actual_status_qurban === 'peserta') {
        $role_user_final = 'berqurban';
    } else {
        $role_user_final = 'warga';
    }

    // Cek apakah user ini sudah punya akun login
    $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE nik_warga = ?");
    $stmt_check_user->bind_param("s", $nik_original); // Cek berdasarkan NIK original
    $stmt_check_user->execute();
    $result_check_user = $stmt_check_user->get_result();

    if ($result_check_user->num_rows > 0) {
        // User sudah ada, update username, role, dan nik_warga
        $stmt_update_user = $conn->prepare("UPDATE users SET username = ?, role = ?, nik_warga = ? WHERE nik_warga = ?");
        $stmt_update_user->bind_param("ssss", $username_user, $role_user_final, $nik, $nik_original);
        $stmt_update_user->execute();
        if ($stmt_update_user->error) {
             throw new mysqli_sql_exception("Error saat memperbarui user login: " . $stmt_update_user->error);
        }
        $stmt_update_user->close();
    } else {
        // Jika tidak ada user login sebelumnya untuk NIK ini,
        // dan NIK ini punya role yang layak login, buat akun baru.
        if ($actual_status_panitia === 1 || $actual_status_qurban === 'peserta') {
            $password_default = hashPassword('qurbanrt001'); // Password default
            $stmt_insert_user = $conn->prepare("INSERT INTO users (username, password, role, nik_warga) VALUES (?, ?, ?, ?)");
            $stmt_insert_user->bind_param("ssss", $username_user, $password_default, $role_user_final, $nik);
            $stmt_insert_user->execute();
            if ($stmt_insert_user->error) {
                throw new mysqli_sql_exception("Error saat membuat user login baru: " . $stmt_insert_user->error);
            }
            $_SESSION['message'] .= " Akun login untuk warga ini juga berhasil dibuat.";
            $_SESSION['message_type'] = "success"; // Set type untuk pesan tambahan ini
            $stmt_insert_user->close();
        }
    }
    $stmt_check_user->close();

    $conn->commit();
    $_SESSION['message'] = "Data warga berhasil diperbarui.";
    $_SESSION['message_type'] = "success";
    header("Location: index.php");
    exit();

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    // Rollback data warga ke kondisi awal (jika perlu)
    if (!empty($old_warga_data_for_rollback)) {
        $stmt_rollback_warga = $conn->prepare("UPDATE warga SET nik = ?, nama = ?, alamat = ?, no_hp = ?, status_qurban = ?, status_panitia = ? WHERE nik = ?");
        $stmt_rollback_warga->bind_param("sssssis", $old_warga_data_for_rollback['nik'], $old_warga_data_for_rollback['nama'], $old_warga_data_for_rollback['alamat'], $old_warga_data_for_rollback['no_hp'], $old_warga_data_for_rollback['status_qurban'], $old_warga_data_for_rollback['status_panitia'], $nik_original);
        $stmt_rollback_warga->execute();
        $stmt_rollback_warga->close();
    }

    $errors[] = "Terjadi kesalahan saat memperbarui data warga: " . $e->getMessage();
    $_SESSION['message'] = "Gagal memperbarui data warga: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    $_SESSION['form_data'] = $_POST;
    header("Location: edit.php?nik=" . urlencode($nik_original));
    exit();
}