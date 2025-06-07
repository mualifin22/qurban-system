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

$nik_to_edit = ''; // NIK yang akan diedit (dari GET atau POST)
$nik = $nama = $alamat = $no_hp = $status_qurban = '';
$status_panitia = 0; // Data dari DB
$errors = []; // Untuk menampilkan error validasi

// Ambil NIK dari URL (GET) jika ini request awal
if (isset($_GET['nik'])) {
    $nik_to_edit = sanitizeInput($_GET['nik']);
} 
// Jika ini adalah POST request (form disubmit), ambil NIK original dari hidden field
elseif (isset($_POST['nik_original'])) {
    $nik_to_edit = sanitizeInput($_POST['nik_original']);
} 
// Jika tidak ada NIK sama sekali, redirect
else {
    $_SESSION['message'] = "NIK warga tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Proses form jika ada data yang dikirim (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik_original = sanitizeInput($_POST['nik_original'] ?? ''); // NIK asli dari hidden field
    $nik = sanitizeInput($_POST['nik'] ?? '');
    $nama = sanitizeInput($_POST['nama'] ?? '');
    $alamat = sanitizeInput($_POST['alamat'] ?? '');
    $no_hp = sanitizeInput($_POST['no_hp'] ?? '');
    // status_qurban dan status_panitia TIDAK diambil dari form POST di sini,
    // karena mereka dikelola otomatis atau dari modul lain.

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

    // Jika ada error validasi, simpan data POST ke session dan tampilkan di form
    if (!empty($errors)) {
        $_SESSION['form_data'] = $_POST; // Simpan data POST yang lama untuk redisplay
        // Pesan error sudah ada di $errors, tidak perlu di session message
        // Redirect kembali ke halaman ini untuk menampilkan error
        header("Location: edit.php?nik=" . urlencode($nik_original));
        exit();
    }

    // --- Proses Update (jika validasi berhasil) ---
    $conn->begin_transaction();
    $old_warga_data_for_rollback = []; // Untuk menyimpan data warga lama jika perlu rollback

    try {
        // Ambil data warga lama (termasuk status_qurban dan status_panitia) untuk rollback
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

        // Query UPDATE hanya untuk NIK, nama, alamat, no_hp
        // status_qurban dan status_panitia TIDAK diubah dari sini
        $stmt = $conn->prepare("UPDATE warga SET nik = ?, nama = ?, alamat = ?, no_hp = ? WHERE nik = ?");
        $stmt->bind_param("sssss", $nik, $nama, $alamat, $no_hp, $nik_original);
        $stmt->execute();
        if ($stmt->error) {
            throw new mysqli_sql_exception("Error saat memperbarui data warga: " . $stmt->error);
        }
        $stmt->close();

        // Update atau buat user login
        $username_user = $nik; // Username berdasarkan NIK baru

        // Dapatkan status_qurban dan status_panitia TERBARU dari DB setelah update NIK (jika berubah)
        $stmt_get_current_status = $conn->prepare("SELECT status_qurban, status_panitia FROM warga WHERE nik = ?");
        $stmt_get_current_status->bind_param("s", $nik);
        $stmt_get_current_status->execute();
        $current_warga_status_after_update = $stmt_get_current_status->get_result()->fetch_assoc();
        $actual_status_qurban = $current_warga_status_after_update['status_qurban'];
        $actual_status_panitia = $current_warga_status_after_update['status_panitia'];
        $stmt_get_current_status->close();

        // Logika penentuan role user (prioritas: panitia > berqurban > warga)
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
                $_SESSION['message_type'] = "success";
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
        // Rollback data warga ke kondisi awal
        if (!empty($old_warga_data_for_rollback)) {
            $stmt_rollback_warga = $conn->prepare("UPDATE warga SET nik = ?, nama = ?, alamat = ?, no_hp = ?, status_qurban = ?, status_panitia = ? WHERE nik = ?");
            $stmt_rollback_warga->bind_param("sssssis",
                $old_warga_data_for_rollback['nik'],
                $old_warga_data_for_rollback['nama'],
                $old_warga_data_for_rollback['alamat'],
                $old_warga_data_for_rollback['no_hp'],
                $old_warga_data_for_rollback['status_qurban'],
                $old_warga_data_for_rollback['status_panitia'],
                $nik_original
            );
            $stmt_rollback_warga->execute();
            $stmt_rollback_warga->close();
        }

        $errors[] = "Terjadi kesalahan saat memperbarui data warga: " . $e->getMessage();
        $_SESSION['message'] = "Gagal memperbarui data warga: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        // Simpan data POST untuk ditampilkan kembali di form jika terjadi error
        $_SESSION['form_data'] = $_POST;
        header("Location: edit.php?nik=" . urlencode($nik_original)); // Redirect kembali ke form edit
        exit();
    }
}


// =========================================================================
// Bagian Pengambilan Data untuk Tampilan Form (Jika bukan POST atau ada error POST)
// =========================================================================

// Jika ini GET request atau POST request dengan error, ambil data dari DB atau session
if (!isset($_POST['nik_original']) || !empty($errors)) {
    // Ambil data warga dari database berdasarkan NIK_TO_EDIT
    $stmt_get_data = $conn->prepare("SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga WHERE nik = ?");
    $stmt_get_data->bind_param("s", $nik_to_edit);
    $stmt_get_data->execute();
    $result_get_data = $stmt_get_data->get_result();

    if ($result_get_data->num_rows > 0) {
        $warga_data = $result_get_data->fetch_assoc();
        $nik = $warga_data['nik'];
        $nama = $warga_data['nama'];
        $alamat = $warga_data['alamat'];
        $no_hp = $warga_data['no_hp'];
        $status_qurban = $warga_data['status_qurban'];
        $status_panitia = $warga_data['status_panitia'];
    } else {
        // Ini seharusnya tidak terjadi jika $nik_to_edit valid, tapi sebagai safeguard
        $_SESSION['message'] = "Data warga dengan NIK tersebut tidak ditemukan.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
    $stmt_get_data->close();
}

// Jika ada data form dari session (setelah redirect karena error validasi), gunakan data itu
if (isset($_SESSION['form_data'])) {
    $nik = $_SESSION['form_data']['nik'] ?? $nik; // Gunakan NIK dari form_data jika ada, else dari DB
    $nama = $_SESSION['form_data']['nama'] ?? $nama;
    $alamat = $_SESSION['form_data']['alamat'] ?? $alamat;
    $no_hp = $_SESSION['form_data']['no_hp'] ?? $no_hp;
    unset($_SESSION['form_data']);
}
// Ambil pesan error dari session jika ada
if (isset($_SESSION['errors'])) {
    $errors = array_merge($errors, $_SESSION['errors']); // Gabungkan error dari proses saat ini dan session
    unset($_SESSION['errors']);
}


// Cek keterikatan qurban untuk menampilkan informasi saja
$is_kambing_participant = false;
$is_sapi_participant = false;

$stmt_check_kambing_qurban = $conn->prepare("SELECT COUNT(*) FROM hewan_qurban WHERE nik_peserta_tunggal = ?");
$stmt_check_kambing_qurban->bind_param("s", $nik);
$stmt_check_kambing_qurban->execute();
$is_kambing_participant = ($stmt_check_kambing_qurban->get_result()->fetch_row()[0] > 0);
$stmt_check_kambing_qurban->close();

$stmt_check_sapi_qurban = $conn->prepare("SELECT COUNT(*) FROM peserta_sapi WHERE nik_warga = ?");
$stmt_check_sapi_qurban->bind_param("s", $nik);
$stmt_check_sapi_qurban->execute();
$is_sapi_participant = ($stmt_check_sapi_qurban->get_result()->fetch_row()[0] > 0);
$stmt_check_sapi_qurban->close();


// =========================================================================
// Bagian Tampilan HTML
// =========================================================================
include '../../includes/header.php'; // Sertakan header setelah semua logika PHP selesai
?>

<div class="container">
    <h2>Edit Data Warga</h2>
    <?php
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <form action="" method="POST"> <input type="hidden" name="nik_original" value="<?php echo htmlspecialchars($nik_to_edit); ?>">
        <div class="form-group">
            <label for="nik">NIK:</label>
            <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($nik); ?>" required>
        </div>
        <div class="form-group">
            <label for="nama">Nama:</label>
            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required>
        </div>
        <div class="form-group">
            <label for="alamat">Alamat:</label>
            <textarea id="alamat" name="alamat"><?php echo htmlspecialchars($alamat); ?></textarea>
        </div>
        <div class="form-group">
            <label for="no_hp">No. HP:</label>
            <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>">
        </div>
        <div class="form-group">
            <label>Status Qurban Saat Ini:</label>
            <p><strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status_qurban))); ?></strong>
            <?php if ($is_kambing_participant || $is_sapi_participant): ?>
                <small style="color: orange; display: block; margin-top: 5px;">(Status ini diatur otomatis oleh sistem berdasarkan partisipasi dalam Qurban.)</small>
            <?php endif; ?>
            </p>
        </div>
        <div class="form-group">
            <label>Status Panitia Saat Ini:</label>
            <p><strong><?php echo ($status_panitia ? 'Ya' : 'Tidak'); ?></strong>
            <small style="display: block; margin-top: 5px;">(Status ini dikelola melalui Manajemen User.)</small>
            </p>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php include '../../includes/footer.php'; ?>