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
$status_qurban = sanitizeInput($_POST['status_qurban'] ?? '');
$status_panitia = isset($_POST['status_panitia']) ? 1 : 0;

$errors = [];

// Validasi
if (empty($nik_original)) { $errors[] = "NIK original tidak ditemukan."; }
if (empty($nik)) { $errors[] = "NIK wajib diisi."; }
if (empty($nama)) { $errors[] = "Nama wajib diisi."; }
if (!in_array($status_qurban, ['peserta', 'penerima', 'tidak_ikut'])) {
    $errors[] = "Status qurban tidak valid.";
}

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
    $_SESSION['form_data'] = $_POST;
    header("Location: edit.php?nik=" . urlencode($nik_original));
    exit();
}

// --- Proses Update ---
$conn->begin_transaction();
try {
    // Update data warga
    $stmt = $conn->prepare("UPDATE warga SET nik = ?, nama = ?, alamat = ?, no_hp = ?, status_qurban = ?, status_panitia = ? WHERE nik = ?");
    $stmt->bind_param("sssssis", $nik, $nama, $alamat, $no_hp, $status_qurban, $status_panitia, $nik_original);
    $stmt->execute();
    if ($stmt->error) {
        throw new mysqli_sql_exception("Error saat memperbarui data warga: " . $stmt->error);
    }
    $stmt->close();

    // Update atau buat user login berdasarkan status qurban/panitia
    $username_warga = $nik;
    $role_user = '';
    if ($status_panitia === 1) {
        $role_user = 'panitia';
    } elseif ($status_qurban === 'peserta') {
        $role_user = 'berqurban';
    } else {
        $role_user = 'warga'; // Jika hanya penerima atau tidak ikut
    }

    // Cek apakah user ini sudah punya akun login
    $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE nik_warga = ?");
    $stmt_check_user->bind_param("s", $nik_original);
    $stmt_check_user->execute();
    $result_check_user = $stmt_check_user->get_result();

    if ($result_check_user->num_rows > 0) {
        // User sudah ada, update username, role, dan nik_warga
        $stmt_update_user = $conn->prepare("UPDATE users SET username = ?, role = ?, nik_warga = ? WHERE nik_warga = ?");
        $stmt_update_user->bind_param("ssss", $username_warga, $role_user, $nik, $nik_original);
        $stmt_update_user->execute();
        if ($stmt_update_user->error) {
             throw new mysqli_sql_exception("Error saat memperbarui user login: " . $stmt_update_user->error);
        }
        $stmt_update_user->close();
    } else {
        // User belum ada, buat akun baru jika statusnya 'peserta' atau 'panitia'
        if ($status_qurban === 'peserta' || $status_panitia === 1) {
            $password_default = hashPassword('qurbanrt001'); // Password default
            $stmt_insert_user = $conn->prepare("INSERT INTO users (username, password, role, nik_warga) VALUES (?, ?, ?, ?)");
            $stmt_insert_user->bind_param("ssss", $username_warga, $password_default, $role_user, $nik);
            $stmt_insert_user->execute();
            if ($stmt_insert_user->error) {
                throw new mysqli_sql_exception("Error saat membuat user login baru: " . $stmt_insert_user->error);
            }
            $stmt_insert_user->close();
        }
    }
    $stmt_check_user->close();

    $conn->commit();
    $_SESSION['message'] = "Data warga berhasil diperbarui.";
    $_SESSION['message_type'] = "success";
    header("Location: index.php"); // Redirect ke halaman daftar warga
    exit();

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    $errors[] = "Terjadi kesalahan saat memperbarui data warga: " . $e->getMessage();
    $_SESSION['message'] = "Gagal memperbarui data warga: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    $_SESSION['form_data'] = $_POST;
    header("Location: edit.php?nik=" . urlencode($nik_original));
    exit();
}
?>