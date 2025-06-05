<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/header.php'; // Sertakan header yang berisi koneksi DB dan fungsi helper

$id_qurban = '';
// Dapatkan ID qurban dari URL (GET) atau dari form (POST)
if (isset($_GET['id'])) {
    $id_qurban = sanitizeInput($_GET['id']);
} elseif (isset($_POST['id'])) {
    $id_qurban = sanitizeInput($_POST['id']);
} else {
    // Jika tidak ada ID, kembalikan ke halaman daftar dengan pesan error
    $_SESSION['message'] = "ID hewan qurban tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Inisialisasi variabel untuk form
$jenis_hewan = $harga = $biaya_administrasi = $estimasi_berat_daging_kg = '';
$tanggal_beli = date('Y-m-d'); // Default tanggal hari ini, akan ditimpa dari DB
$nik_peserta = ''; // NIK peserta kambing (dari DB)
$errors = []; // Array untuk menyimpan pesan error validasi
$selected_peserta = []; // Array untuk menyimpan NIK peserta sapi yang sudah ada

// Ambil data hewan qurban dari database berdasarkan ID
$stmt_get = $conn->prepare("SELECT id, jenis_hewan, harga, biaya_administrasi, tanggal_beli, estimasi_berat_daging_kg, nik_peserta_tunggal FROM hewan_qurban WHERE id = ?");
$stmt_get->bind_param("i", $id_qurban);
$stmt_get->execute();
$result_get = $stmt_get->get_result();

if ($result_get->num_rows > 0) {
    $qurban_data = $result_get->fetch_assoc();
    $jenis_hewan = $qurban_data['jenis_hewan'];
    $harga = $qurban_data['harga'];
    $biaya_administrasi = $qurban_data['biaya_administrasi'];
    $estimasi_berat_daging_kg = $qurban_data['estimasi_berat_daging_kg'];
    $tanggal_beli = $qurban_data['tanggal_beli'];

    // Jika kambing, ambil data pesertanya
    if ($jenis_hewan === 'kambing') {
        $nik_peserta = $qurban_data['nik_peserta_tunggal'];
    }
    // Jika sapi, ambil data pesertanya (logika lama tetap)
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
    // Jika data tidak ditemukan, redirect dengan pesan error
    $_SESSION['message'] = "Data hewan qurban tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}
$stmt_get->close();

// Ambil daftar warga untuk pilihan peserta (baik kambing atau sapi)
$sql_warga_peserta_select = "SELECT nik, nama FROM warga WHERE status_qurban = 'peserta' OR status_panitia = TRUE ORDER BY nama ASC";
$result_warga_peserta_select = $conn->query($sql_warga_peserta_select);
$list_warga = [];
if ($result_warga_peserta_select && $result_warga_peserta_select->num_rows > 0) {
    while($row = $result_warga_peserta_select->fetch_assoc()) {
        $list_warga[] = $row;
    }
}


// Proses form jika ada data yang dikirim (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitasi dan ambil data dari POST
    $id_qurban = sanitizeInput($_POST['id']);
    // Jenis hewan tidak diambil dari POST karena tidak dapat diubah
    $harga = sanitizeInput($_POST['harga']);
    $biaya_administrasi = sanitizeInput($_POST['biaya_administrasi']);
    $estimasi_berat_daging_kg = sanitizeInput($_POST['estimasi_berat_daging_kg']);
    $tanggal_beli = sanitizeInput($_POST['tanggal_beli']);

    // Ambil data peserta sesuai jenis hewan
    if ($jenis_hewan === 'kambing') {
        $nik_peserta = sanitizeInput($_POST['nik_peserta']);
    } elseif ($jenis_hewan === 'sapi') {
        $new_peserta = isset($_POST['peserta']) ? $_POST['peserta'] : [];
    }

    // --- Validasi Data ---
    if (!is_numeric($harga) || $harga <= 0) { $errors[] = "Harga harus angka positif."; }
    if (!is_numeric($biaya_administrasi) || $biaya_administrasi < 0) { $errors[] = "Biaya administrasi tidak valid."; }
    if (!is_numeric($estimasi_berat_daging_kg) || $estimasi_berat_daging_kg <= 0) { $errors[] = "Estimasi berat daging harus angka positif."; }
    if (empty($tanggal_beli)) { $errors[] = "Tanggal beli wajib diisi."; }

    // Validasi Khusus Kambing
    if ($jenis_hewan === 'kambing') {
        if (empty($nik_peserta)) { $errors[] = "Peserta qurban kambing wajib dipilih."; }
    }
    // Validasi Khusus Sapi
    elseif ($jenis_hewan === 'sapi') {
        if (count($new_peserta) !== 7) {
            $errors[] = "Qurban sapi harus memiliki tepat 7 peserta.";
        }
        // Pastikan NIK yang dipilih valid (opsional, tapi bagus untuk keamanan)
        foreach ($new_peserta as $nik_p) {
            $found = false;
            foreach ($list_warga as $w) {
                if ($w['nik'] === $nik_p) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $errors[] = "NIK peserta " . htmlspecialchars($nik_p) . " tidak valid.";
                break;
            }
        }
    }

    // Periksa apakah ada error validasi
    if (empty($errors)) {
        $conn->begin_transaction(); // Mulai transaksi database

        try {
            // 1. Update data hewan qurban di tabel `hewan_qurban`
            // Perlu update kolom nik_peserta_tunggal jika jenisnya kambing
            if ($jenis_hewan === 'kambing') {
                $stmt_update_hewan = $conn->prepare("UPDATE hewan_qurban SET harga = ?, biaya_administrasi = ?, tanggal_beli = ?, estimasi_berat_daging_kg = ?, nik_peserta_tunggal = ? WHERE id = ?");
                // Tipe data: double, double, string, double, string, integer
                $stmt_update_hewan->bind_param("ddsdsi", $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg, $nik_peserta, $id_qurban);
            } else { // Sapi
                $stmt_update_hewan = $conn->prepare("UPDATE hewan_qurban SET harga = ?, biaya_administrasi = ?, tanggal_beli = ?, estimasi_berat_daging_kg = ? WHERE id = ?");
                // Tipe data: double, double, string, double, integer
                $stmt_update_hewan->bind_param("ddsdi", $harga, $biaya_administrasi, $tanggal_beli, $estimasi_berat_daging_kg, $id_qurban);
            }
            $stmt_update_hewan->execute();

            if ($stmt_update_hewan->error) {
                throw new mysqli_sql_exception("Error saat memperbarui data hewan_qurban: " . $stmt_update_hewan->error);
            }
            $stmt_update_hewan->close();

            // 2. Update data keuangan (pengeluaran pembelian hewan)
            // Hapus transaksi pembelian lama yang terkait dengan hewan ini
            $stmt_del_keuangan_beli = $conn->prepare("DELETE FROM keuangan WHERE id_hewan_qurban = ? AND jenis = 'pengeluaran' AND keterangan LIKE 'Pembelian Qurban %'");
            $stmt_del_keuangan_beli->bind_param("i", $id_qurban);
            $stmt_del_keuangan_beli->execute();
            $stmt_del_keuangan_beli->close();

            // Masukkan transaksi pembelian yang diperbarui
            $total_biaya = $harga + $biaya_administrasi;
            $stmt_keuangan_beli = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal, id_hewan_qurban) VALUES (?, ?, ?, ?, ?)");
            $keterangan_beli = "Pembelian Qurban " . ucfirst($jenis_hewan) . " (ID: " . $id_qurban . ") - Diperbarui";
            $jenis_transaksi_keluar = 'pengeluaran';
            // Tipe data: string, string, double, string, integer
            $stmt_keuangan_beli->bind_param("ssdsi", $jenis_transaksi_keluar, $keterangan_beli, $total_biaya, $tanggal_beli, $id_qurban);
            $stmt_keuangan_beli->execute();

            if ($stmt_keuangan_beli->error) {
                throw new mysqli_sql_exception("Error saat memasukkan pengeluaran pembelian setelah update: " . $stmt_keuangan_beli->error);
            }
            $stmt_keuangan_beli->close();


            // 3. Update pemasukan iuran sesuai jenis hewan
            // Hapus semua iuran lama terkait hewan ini (baik kambing atau sapi)
            $stmt_delete_iuran_keuangan = $conn->prepare("DELETE FROM keuangan WHERE jenis = 'pemasukan' AND (keterangan LIKE CONCAT('Iuran Qurban Sapi ID ', ?, '%') OR keterangan LIKE CONCAT('Iuran Qurban Kambing ID ', ?, '%'))");
            $stmt_delete_iuran_keuangan->bind_param("ii", $id_qurban, $id_qurban);
            $stmt_delete_iuran_keuangan->execute();
            $stmt_delete_iuran_keuangan->close();

            if ($jenis_hewan === 'kambing') {
                // Tambahkan pemasukan iuran baru untuk kambing
                $stmt_keuangan_iuran = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
                $keterangan_iuran = "Iuran Qurban Kambing ID " . $id_qurban . " dari NIK " . $nik_peserta . " - Diperbarui";
                $jenis_transaksi_masuk = 'pemasukan';
                $stmt_keuangan_iuran->bind_param("ssds", $jenis_transaksi_masuk, $keterangan_iuran, $total_biaya, $tanggal_beli);
                $stmt_keuangan_iuran->execute();
                if ($stmt_keuangan_iuran->error) {
                    throw new mysqli_sql_exception("Error saat memasukkan iuran keuangan kambing setelah update: " . $stmt_keuangan_iuran->error);
                }
                $stmt_keuangan_iuran->close();

                // Perbarui status_qurban warga menjadi 'peserta' jika belum
                $stmt_update_warga_status = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ? AND status_qurban != 'peserta'");
                $stmt_update_warga_status->bind_param("s", $nik_peserta);
                $stmt_update_warga_status->execute();
                if ($stmt_update_warga_status->error) {
                    throw new mysqli_sql_exception("Error saat memperbarui status warga (kambing) untuk NIK " . $nik_peserta . ": " . $stmt_update_warga_status->error);
                }
                $stmt_update_warga_status->close();

            } elseif ($jenis_hewan === 'sapi') {
                // Hapus semua peserta sapi lama
                $stmt_delete_peserta = $conn->prepare("DELETE FROM peserta_sapi WHERE id_hewan_qurban = ?");
                $stmt_delete_peserta->bind_param("i", $id_qurban);
                $stmt_delete_peserta->execute();
                $stmt_delete_peserta->close();

                // Tambahkan peserta sapi baru dan iuran
                $iuran_per_orang = ($harga / 7) + ($biaya_administrasi / 7);
                $stmt_insert_peserta = $conn->prepare("INSERT INTO peserta_sapi (id_hewan_qurban, nik_warga, jumlah_iuran) VALUES (?, ?, ?)");
                foreach ($new_peserta as $nik_p) {
                    $nik_p_sanitized = sanitizeInput($nik_p);
                    $stmt_insert_peserta->bind_param("isd", $id_qurban, $nik_p_sanitized, $iuran_per_orang);
                    $stmt_insert_peserta->execute();

                    if ($stmt_insert_peserta->error) {
                        throw new mysqli_sql_exception("Error saat memasukkan peserta sapi baru untuk NIK " . $nik_p_sanitized . ": " . $stmt_insert_peserta->error);
                    }

                    // Perbarui status_qurban warga menjadi 'peserta' jika belum
                    $stmt_update_warga_status = $conn->prepare("UPDATE warga SET status_qurban = 'peserta' WHERE nik = ? AND status_qurban != 'peserta'");
                    $stmt_update_warga_status->bind_param("s", $nik_p_sanitized);
                    $stmt_update_warga_status->execute();
                    if ($stmt_update_warga_status->error) {
                        throw new mysqli_sql_exception("Error saat memperbarui status warga (sapi) untuk NIK " . $nik_p_sanitized . ": " . $stmt_update_warga_status->error);
                    }
                    $stmt_update_warga_status->close();

                    // Tambahkan transaksi pemasukan iuran peserta ke tabel `keuangan`
                    $stmt_keuangan_iuran_sapi = $conn->prepare("INSERT INTO keuangan (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
                    $keterangan_iuran_sapi = "Iuran Qurban Sapi ID " . $id_qurban . " dari NIK " . $nik_p_sanitized . " - Diperbarui";
                    $jenis_transaksi_masuk_sapi = 'pemasukan';
                    $stmt_keuangan_iuran_sapi->bind_param("ssds", $jenis_transaksi_masuk_sapi, $keterangan_iuran_sapi, $iuran_per_orang, $tanggal_beli);
                    $stmt_keuangan_iuran_sapi->execute();
                    if ($stmt_keuangan_iuran_sapi->error) {
                        throw new mysqli_sql_exception("Error saat memasukkan iuran keuangan sapi baru untuk NIK " . $nik_p_sanitized . ": " . $stmt_keuangan_iuran_sapi->error);
                    }
                    $stmt_keuangan_iuran_sapi->close();
                }
                $stmt_insert_peserta->close();
                $selected_peserta = $new_peserta; // Perbarui untuk tampilan form
            }


            $conn->commit(); // Komit transaksi jika semua operasi berhasil
            $_SESSION['message'] = "Data qurban " . ucfirst($jenis_hewan) . " berhasil diperbarui.";
            $_SESSION['message_type'] = "success";
            header("Location: index.php"); // Redirect ke halaman daftar hewan qurban
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback(); // Batalkan semua operasi jika terjadi error
            $errors[] = "Terjadi kesalahan saat memperbarui data qurban: " . $e->getMessage();
            $_SESSION['message'] = "Gagal memperbarui data qurban: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            // Tidak perlu redirect di sini, biarkan error ditampilkan di form
        }
    }
}
?>

<div class="container">
    <h2>Edit Data Qurban <?php echo ucfirst($jenis_hewan); ?></h2>
    <?php
    // Tampilkan pesan error jika ada
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    ?>
    <form action="" method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_qurban); ?>">
        <input type="hidden" name="jenis_hewan" value="<?php echo htmlspecialchars($jenis_hewan); ?>">

        <div class="form-group">
            <label for="jenis_hewan_display">Jenis Hewan:</label>
            <input type="text" id="jenis_hewan_display" value="<?php echo htmlspecialchars(ucfirst($jenis_hewan)); ?>" readonly>
            <small>Jenis hewan tidak dapat diubah setelah dibuat.</small>
        </div>
        <div class="form-group">
            <label for="harga">Harga:</label>
            <input type="number" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" step="10000" required>
        </div>
        <div class="form-group">
            <label for="biaya_administrasi">Biaya Administrasi:</label>
            <input type="number" id="biaya_administrasi" name="biaya_administrasi" value="<?php echo htmlspecialchars($biaya_administrasi); ?>" step="10000" required>
        </div>
        <div class="form-group">
            <label for="estimasi_berat_daging_kg">Estimasi Berat Daging (kg):</label>
            <input type="number" id="estimasi_berat_daging_kg" name="estimasi_berat_daging_kg" value="<?php echo htmlspecialchars($estimasi_berat_daging_kg); ?>" step="0.1" required>
        </div>
        <div class="form-group">
            <label for="tanggal_beli">Tanggal Beli:</label>
            <input type="date" id="tanggal_beli" name="tanggal_beli" value="<?php echo htmlspecialchars($tanggal_beli); ?>" required>
        </div>

        <?php if ($jenis_hewan === 'kambing'): ?>
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
                    <p style="color: red; margin-top: 5px;">Tidak ada warga yang terdaftar sebagai peserta atau panitia.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($jenis_hewan === 'sapi'): ?>
            <div id="peserta_sapi_section" class="form-group">
                <label>Pilih 7 Peserta Qurban Sapi:</label>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                <?php if (!empty($list_warga)): ?>
                    <?php foreach ($list_warga as $warga): ?>
                        <input type="checkbox" id="peserta_<?php echo htmlspecialchars($warga['nik']); ?>" name="peserta[]" value="<?php echo htmlspecialchars($warga['nik']); ?>"
                        <?php echo in_array($warga['nik'], $selected_peserta) ? 'checked' : ''; ?>>
                        <label for="peserta_<?php echo htmlspecialchars($warga['nik']); ?>"><?php echo htmlspecialchars($warga['nama']); ?> (<?php echo htmlspecialchars($warga['nik']); ?>)</label><br>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: red; margin-top: 5px;">Tidak ada warga yang terdaftar sebagai peserta atau panitia.</p>
                <?php endif; ?>
                </div>
                <small>Pilih **tepat 7 orang** untuk qurban sapi.</small>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary" style="background-color: #6c757d;">Batal</a>
    </form>
</div>
<?php
include '../../includes/footer.php'; // Sertakan footer
?>