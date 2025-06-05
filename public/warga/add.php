<?php
include '../../includes/header.php'; // Sesuaikan path

$nik = $nama = $alamat = $no_hp = $status_qurban = '';
$status_panitia = 0; // Default: bukan panitia
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = sanitizeInput($_POST['nik']);
    $nama = sanitizeInput($_POST['nama']);
    $alamat = sanitizeInput($_POST['alamat']);
    $no_hp = sanitizeInput($_POST['no_hp']);
    $status_qurban = sanitizeInput($_POST['status_qurban']);
    $status_panitia = isset($_POST['status_panitia']) ? 1 : 0;

    // Validasi
    if (empty($nik)) { $errors[] = "NIK wajib diisi."; }
    if (empty($nama)) { $errors[] = "Nama wajib diisi."; }
    if (!in_array($status_qurban, ['peserta', 'penerima', 'tidak_ikut'])) {
        $errors[] = "Status qurban tidak valid.";
    }

    // Cek duplikasi NIK
    $stmt_check_nik = $conn->prepare("SELECT nik FROM warga WHERE nik = ?");
    $stmt_check_nik->bind_param("s", $nik);
    $stmt_check_nik->execute();
    $stmt_check_nik->store_result();
    if ($stmt_check_nik->num_rows > 0) {
        $errors[] = "NIK sudah terdaftar. Gunakan NIK yang berbeda.";
    }
    $stmt_check_nik->close();

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO warga (nik, nama, alamat, no_hp, status_qurban, status_panitia) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $nik, $nama, $alamat, $no_hp, $status_qurban, $status_panitia);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Data warga berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";

            // Jika warga ini adalah peserta qurban, buat juga user login untuk dia
            if ($status_qurban === 'peserta' || $status_panitia === 1) {
                $username_warga = $nik; // NIK sebagai username default
                $password_default = hashPassword('qurbanrt001'); // Password default, bisa diganti
                $role_user = ($status_qurban === 'peserta' && $status_panitia === 0) ? 'berqurban' : (($status_panitia === 1) ? 'panitia' : 'warga'); // Berqurban kalau dia peserta tapi bukan panitia, panitia kalau dia panitia.

                // Cek apakah user dengan NIK ini sudah ada di tabel users
                $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE nik_warga = ?");
                $stmt_check_user->bind_param("s", $nik);
                $stmt_check_user->execute();
                $stmt_check_user->store_result();
                if ($stmt_check_user->num_rows == 0) { // Jika belum ada, baru insert
                     $stmt_insert_user = $conn->prepare("INSERT INTO users (username, password, role, nik_warga) VALUES (?, ?, ?, ?)");
                     $stmt_insert_user->bind_param("ssss", $username_warga, $password_default, $role_user, $nik);
                     if ($stmt_insert_user->execute()) {
                         $_SESSION['message'] .= " Akun login (" . $username_warga . ") untuk warga ini juga berhasil dibuat.";
                     } else {
                         $_SESSION['message'] .= " Namun, gagal membuat akun login untuk warga ini: " . $stmt_insert_user->error;
                     }
                     $stmt_insert_user->close();
                } else {
                     $_SESSION['message'] .= " Akun login untuk warga ini sudah ada.";
                }
                $stmt_check_user->close();
            }

            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Gagal menambahkan data warga: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<div class="container">
    <h2>Tambah Data Warga Baru</h2>
    <?php
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    ?>
    <form action="" method="POST">
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
            <label for="status_qurban">Status Qurban:</label>
            <select id="status_qurban" name="status_qurban" required>
                <option value="tidak_ikut" <?php echo ($status_qurban == 'tidak_ikut') ? 'selected' : ''; ?>>Tidak Ikut</option>
                <option value="peserta" <?php echo ($status_qurban == 'peserta') ? 'selected' : ''; ?>>Peserta Qurban</option>
                <option value="penerima" <?php echo ($status_qurban == 'penerima') ? 'selected' : ''; ?>>Penerima Daging</option>
            </select>
        </div>
        <div class="form-group">
            <input type="checkbox" id="status_panitia" name="status_panitia" value="1" <?php echo ($status_panitia == 1) ? 'checked' : ''; ?>>
            <label for="status_panitia">Panitia</label>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php
include '../../includes/footer.php'; // Sesuaikan path
?>