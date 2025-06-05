<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/header.php'; // Sertakan header yang berisi koneksi DB dan fungsi helper

// Inisialisasi variabel untuk form
$harga = '';
$biaya_administrasi = 50000; // Default biaya administrasi kambing
$estimasi_berat_daging_kg = 25; // Asumsi per ekor kambing
$tanggal_beli = date('Y-m-d');
$nik_peserta = ''; // NIK peserta kambing
$errors = [];

// Ambil daftar warga yang berstatus 'peserta' (pekurban) atau 'panitia'
$sql_warga_peserta = "SELECT nik, nama FROM warga WHERE status_qurban = 'peserta' OR status_panitia = TRUE ORDER BY nama ASC";
$result_warga_peserta = $conn->query($sql_warga_peserta);
$list_warga = [];
if ($result_warga_peserta && $result_warga_peserta->num_rows > 0) {
    while($row = $result_warga_peserta->fetch_assoc()) {
        $list_warga[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $harga = sanitizeInput($_POST['harga']);
    $biaya_administrasi = sanitizeInput($_POST['biaya_administrasi']);
    $estimasi_berat_daging_kg = sanitizeInput($_POST['estimasi_berat_daging_kg']);
    $tanggal_beli = sanitizeInput($_POST['tanggal_beli']);
    $nik_peserta = sanitizeInput($_POST['nik_peserta']); // Ambil NIK peserta

    // Validasi
    if (!is_numeric($harga) || $harga <= 0) { $errors[] = "Harga harus angka positif."; }
    if (!is_numeric($biaya_administrasi) || $biaya_administrasi < 0) { $errors[] = "Biaya administrasi tidak valid."; }
    if (!is_numeric($estimasi_berat_daging_kg) || $estimasi_berat_daging_kg <= 0) { $errors[] = "Estimasi berat daging harus angka positif."; }
    if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }
    if (empty($nik_peserta)) { $errors[] = "Peserta qurban kambing wajib dipilih."; }

    if (empty($errors)) {
        $conn->begin_transaction(); // Mulai transaksi

        try {
            // 1. Masukkan data hewan qurban (kambing)
            $stmt_hewan = $conn->prepare("INSERT INTO hewan_qurban (jenis_hewan, harga, biaya_administrasi, tanggal_beli, estimasi_berat_daging_kg, nik_peserta_tunggal) VALUES (?, ?, ?, ?, ?, ?)");
            $jenis_hewan = 'kambing';
            // Tipe data: string, double, double, string, double, string
            $stmt_hewan->bind_param("sddsds", $jenis_hewan, $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg, $nik_peserta);
            $stmt_hewan->execute();

            if ($stmt_hewan->error) {
                throw new mysqli_sql_exception("Error saat memasukkan data hewan_qurban kambing: " . $stmt_hewan->error);
            }
            $id_hewan_qurban = $conn->insert_id;
            $stmt_hewan->close();

            // 2. Tambahkan transaksi ke keuangan (pengeluaran pembelian kambing)
            $total_biaya = $harga + $biaya_administrasi;
            $stmt_keuangan = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES (?, ?, ?, ?, ?)");
            $keterangan_beli = "Pembelian Qurban Kambing (ID: " . $id_hewan_qurban . ") untuk NIK " . $nik_peserta;
            $jenis_transaksi = 'pengeluaran';
            // Tipe data: string, string, double, string, integer
            $stmt_keuangan->bind_param("ssdsi", $jenis_transaksi, $keterangan_beli, $total_biaya, $tanggal_beli, $id_hewan_qurban);
            $stmt_keuangan->execute();

            if ($stmt_keuangan->error) {
                throw new mysqli_sql_exception("Error saat memasukkan pengeluaran pembelian kambing: " . $stmt_keuangan->error);
            }
            $stmt_keuangan->close();

            // 3. Tambahkan pemasukan dari iuran peserta kambing
            $stmt_keuangan_iuran = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
            $keterangan_iuran = "Iuran Qurban Kambing ID " . $id_hewan_qurban . " dari NIK " . $nik_peserta;
            $jenis_transaksi_masuk = 'pemasukan';
            // Tipe data: string, string, double, string
            $stmt_keuangan_iuran->bind_param("ssds", $jenis_transaksi_masuk, $keterangan_iuran, $total_biaya, $tanggal_beli); // Seluruh biaya ditanggung 1 orang
            $stmt_keuangan_iuran->execute();

            if ($stmt_keuangan_iuran->error) {
                throw new mysqli_sql_exception("Error saat memasukkan iuran keuangan kambing: " . $stmt_keuangan_iuran->error);
            }
            $stmt_keuangan_iuran->close();


            // 4. Perbarui status_qurban warga menjadi 'peserta' jika belum
            $stmt_update_warga_status = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ? AND status_qurban != 'peserta'");
            $stmt_update_warga_status->bind_param("s", $nik_peserta);
            $stmt_update_warga_status->execute();
            if ($stmt_update_warga_status->error) {
                throw new mysqli_sql_exception("Error saat memperbarui status warga (kambing) untuk NIK " . $nik_peserta . ": " . $stmt_update_warga_status->error);
            }
            $stmt_update_warga_status->close();


            $conn->commit(); // Komit transaksi jika semua berhasil
            $_SESSION['message'] = "Data qurban kambing berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback(); // Rollback jika ada error
            $errors[] = "Gagal menambahkan data qurban kambing: " . $e->getMessage();
            $_SESSION['message'] = "Gagal menambahkan data qurban kambing: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
}
?>

<div class="container">
    <h2>Tambah Data Qurban Kambing</h2>
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
            <label for="harga">Harga Kambing:</label>
            <input type="number" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" step="10000" required>
        </div>
        <div class="form-group">
            <label for="biaya_administrasi">Biaya Administrasi:</label>
            <input type="number" id="biaya_administrasi" name="biaya_administrasi" value="<?php echo htmlspecialchars($biaya_administrasi); ?>" step="10000" required>
        </div>
        <div class="form-group">
            <label for="estimasi_berat_daging_kg">Estimasi Berat Daging (kg):</label>
            <input type="number" id="estimasi_berat_daging_kg" name="estimasi_berat_daging_kg" value="<?php echo htmlspecialchars($estimasi_berat_daging_kg); ?>" step="0.1" required>
            <small>Asumsi per ekor kambing.</small>
        </div>
        <div class="form-group">
            <label for="tanggal_beli">Tanggal Beli:</label>
            <input type="date" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
        </div>
        <div class="form-group">
            <label for="nik_peserta">Peserta Qurban Kambing (1 Orang):</label>
            <select id="nik_peserta" name="nik_peserta" required>
                <option value="">-- Pilih Warga --</option>
                <?php foreach ($list_warga as $warga): ?>
                    <option value="<?php echo htmlspecialchars($warga['nik']); ?>" <?php echo ($nik_peserta == $warga['nik']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($list_warga)): ?>
                <p style="color: red; margin-top: 5px;">Tidak ada warga yang terdaftar sebagai peserta atau panitia. Mohon daftarkan di menu Data Warga.</p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php
include '../../includes/footer.php'; // Sesuaikan path
?>