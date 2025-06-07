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

// Hanya Admin dan Panitia yang bisa mengakses halaman ini
if (!isAdmin() && !isPanitia()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

$errors = [];
$tanggal_distribusi_default = date('Y-m-d');

// --- Hitung Total Daging Qurban Tersedia ---
$total_daging_tersedia_sql = "SELECT SUM(estimasi_berat_daging_kg) AS total_kg FROM hewan_qurban";
$result_total_daging = $conn->query($total_daging_tersedia_sql);
$total_daging_tersedia = 0;
if ($result_total_daging && $result_total_daging->num_rows > 0) {
    $row_total_daging = $result_total_daging->fetch_assoc();
    $total_daging_tersedia = (float)$row_total_daging['total_kg'];
}

// LOGIKA BARU: Jika total daging tersedia 0, kosongkan tabel pembagian_daging
if ($total_daging_tersedia <= 0) {
    // Hanya TRUNCATE jika tidak ada POST request untuk menghindari TRUNCATE saat generate plan (yang punya logikanya sendiri)
    // Atau jika ada POST request tapi itu bukan untuk generate_plan (misal update status)
    if (!isset($_POST['action']) || (isset($_POST['action']) && $_POST['action'] !== 'generate_plan')) {
        $conn->query("TRUNCATE TABLE pembagian_daging");
        $_SESSION['message'] = "Tabel pembagian daging dikosongkan karena tidak ada daging qurban yang tersedia.";
        $_SESSION['message_type'] = "info"; // Ganti ke info agar tidak terlihat seperti error
    }
    
    // Set variabel-variabel terkait jatah menjadi nol atau tidak relevan
    $jumlah_pekurban = 0;
    $jumlah_panitia = 0;
    $jumlah_penerima_umum = 0;
    $sisa_daging_untuk_penerima_umum = 0;
    $jatah_warga_penerima_kg = 0;
    $final_recipients = []; // Pastikan ini juga kosong

    // Tambahkan pesan error jika memang belum ada daging
    if (!isset($_SESSION['message_type']) || $_SESSION['message_type'] !== 'info') { // Hindari duplikasi pesan jika sudah di-TRUNCATE
        $errors[] = "Tidak ada daging qurban yang tersedia. Pembagian daging tidak dapat dilakukan.";
    }

} else {
    // --- Definisikan Jatah Daging per Kategori ---
    $jatah_pekurban_kg = 2;   // Asumsi jatah untuk setiap pekurban
    $jatah_panitia_kg = 1.5;  // Asumsi jatah untuk setiap panitia

    // --- Dapatkan Daftar Semua Warga dengan Status Mereka ---
    $sql_all_warga = "SELECT nik, nama, status_qurban, status_panitia FROM warga ORDER BY nama ASC";
    $result_all_warga = $conn->query($sql_all_warga);
    $list_warga_dengan_status = [];
    if ($result_all_warga && $result_all_warga->num_rows > 0) {
        while($row = $result_all_warga->fetch_assoc()) {
            $list_warga_dengan_status[] = $row;
        }
    }

    // Inisialisasi array untuk menyimpan penerima akhir dengan jatahnya
    $final_recipients = [];
    $daging_teralokasi_spesifik = 0; // Daging yang dialokasikan untuk pekurban dan panitia

    // Alokasikan jatah daging berdasarkan prioritas: Pekurban > Panitia > Penerima Umum
    foreach ($list_warga_dengan_status as $warga) {
        $nik = $warga['nik'];
        $nama = $warga['nama'];
        $status_qurban = $warga['status_qurban'];
        $status_panitia = $warga['status_panitia'];
        $jatah_daging_individu = 0;
        $kategori_jatah = '';

        if ($status_qurban === 'peserta') {
            $jatah_daging_individu = $jatah_pekurban_kg;
            $kategori_jatah = 'pekurban';
            $daging_teralokasi_spesifik += $jatah_daging_individu;
        } elseif ($status_panitia) {
            $jatah_daging_individu = $jatah_panitia_kg;
            $kategori_jatah = 'panitia';
            $daging_teralokasi_spesifik += $jatah_daging_individu;
        } else {
            $kategori_jatah = 'penerima_umum';
        }

        $final_recipients[$nik] = [
            'nama' => $nama,
            'nik' => $nik,
            'kategori' => $kategori_jatah,
            'jatah_kg' => $jatah_daging_individu
        ];
    }

    // Hitung jatah untuk penerima umum dari sisa daging
    $sisa_daging_untuk_penerima_umum = $total_daging_tersedia - $daging_teralokasi_spesifik;
    $jumlah_penerima_umum = 0;
    foreach ($final_recipients as $recipient) {
        if ($recipient['kategori'] === 'penerima_umum') {
            $jumlah_penerima_umum++;
        }
    }

    $jatah_warga_penerima_kg = 0;
    if ($jumlah_penerima_umum > 0 && $sisa_daging_untuk_penerima_umum > 0) {
        $jatah_warga_penerima_kg = $sisa_daging_untuk_penerima_umum / $jumlah_penerima_umum;
    }
    if ($jatah_warga_penerima_kg < 0) $jatah_warga_penerima_kg = 0;
    if ($sisa_daging_untuk_penerima_umum < 0) $sisa_daging_untuk_penerima_umum = 0;


    // Perbarui jatah akhir untuk penerima umum di $final_recipients
    foreach ($final_recipients as $nik => $recipient) {
        if ($recipient['kategori'] === 'penerima_umum') {
            $final_recipients[$nik]['jatah_kg'] = $jatah_warga_penerima_kg;
        }
    }

    // Hapus penerima dengan jatah 0 kg
    $final_recipients = array_filter($final_recipients, function($recipient) {
        return $recipient['jatah_kg'] > 0;
    });

    // Menghitung jumlah pekurban dan panitia yang benar-benar dialokasikan jatahnya
    $jumlah_pekurban_terdata = 0;
    $jumlah_panitia_terdata = 0;
    foreach ($final_recipients as $recipient) {
        if ($recipient['kategori'] === 'pekurban') {
            $jumlah_pekurban_terdata++;
        } elseif ($recipient['kategori'] === 'panitia') {
            $jumlah_panitia_terdata++;
        }
    }
    $jumlah_pekurban = $jumlah_pekurban_terdata;
    $jumlah_panitia = $jumlah_panitia_terdata;

} // END if ($total_daging_tersedia <= 0) ELSE

// =========================================================================
// Proses Simpan Rencana Pembagian Daging (Jika tombol "Generate & Simpan Rencana" ditekan)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_plan') {
    $tanggal_distribusi = sanitizeInput($_POST['tanggal_distribusi']);

    if ($total_daging_tersedia <= 0) { // Validasi tambahan jika total daging tersedia 0
        $_SESSION['message'] = "Tidak ada daging qurban yang tersedia, rencana pembagian tidak dapat digenerate.";
        $_SESSION['message_type'] = "error";
        header("Location: pembagian.php");
        exit();
    }

    if (empty($tanggal_distribusi)) {
        $errors[] = "Tanggal distribusi wajib diisi.";
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $conn->query("TRUNCATE TABLE pembagian_daging"); // Kosongkan sebelum mengisi ulang

            $stmt_insert = $conn->prepare("INSERT INTO pembagian_daging (id_hewan_qurban, nik_warga, jumlah_daging_kg, tanggal_distribusi) VALUES (?, ?, ?, ?)");
            $id_qurban_placeholder = 0;
            
            foreach ($final_recipients as $recipient) {
                $nik = $recipient['nik'];
                $jumlah_daging = $recipient['jatah_kg'];
                
                if ($jumlah_daging > 0) {
                    $stmt_insert->bind_param("isds", $id_qurban_placeholder, $nik, $jumlah_daging, $tanggal_distribusi);
                    $stmt_insert->execute();
                    if ($stmt_insert->error) {
                        throw new mysqli_sql_exception("Error saat memasukkan data pembagian untuk NIK " . $nik . ": " . $stmt_insert->error);
                    }
                }
            }
            $stmt_insert->close();

            $conn->commit();
            $_SESSION['message'] = "Rencana pembagian daging berhasil digenerate dan disimpan.";
            $_SESSION['message_type'] = "success";
            header("Location: pembagian.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $errors[] = "Gagal menggenerate rencana pembagian: " . $e->getMessage();
            $_SESSION['message'] = "Gagal menggenerate rencana pembagian: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
}

// =========================================================================
// Proses Update Status Pengambilan Daging (Jika tombol "Ambil" ditekan)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_pengambilan') {
    $id_pembagian = sanitizeInput($_POST['id_pembagian']);
    $status_pengambilan = isset($_POST['status_pengambilan']) ? 1 : 0; 

    if (empty($id_pembagian)) {
        $errors[] = "ID Pembagian tidak valid.";
    }

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE pembagian_daging SET status_pengambilan = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $status_pengambilan, $id_pembagian);
        if ($stmt_update->execute()) {
            $_SESSION['message'] = "Status pengambilan berhasil diperbarui.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal memperbarui status pengambilan: " . $stmt_update->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt_update->close();
        header("Location: pembagian.php");
        exit();
    }
}

// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../../includes/header.php'; // Sertakan header setelah semua logika PHP selesai

// Ambil data pembagian daging yang sudah tersimpan untuk ditampilkan di tabel
// Ini akan mengambil data dari tabel pembagian_daging, yang sudah dikosongkan jika total_daging_tersedia <= 0
$sql_pembagian = "SELECT pd.id, pd.nik_warga, w.nama, pd.jumlah_daging_kg, pd.tanggal_distribusi, pd.status_pengambilan
                  FROM pembagian_daging pd
                  JOIN warga w ON pd.nik_warga = w.nik
                  ORDER BY w.nama ASC";
$result_pembagian = $conn->query($sql_pembagian);
$data_pembagian = [];
if ($result_pembagian && $result_pembagian->num_rows > 0) {
    while($row = $result_pembagian->fetch_assoc()) {
        $data_pembagian[] = $row;
    }
}

?>

<div class="container">
    <h2>Rekapan Pembagian Daging Qurban</h2>
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    ?>

    <div style="background-color: #f0f8ff; border: 1px solid #d0e8ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h3>Rangkuman Alokasi Daging</h3>
        <p><strong>Total Daging Qurban Tersedia:</strong> <?php echo number_format($total_daging_tersedia, 2); ?> kg</p>
        <?php if ($total_daging_tersedia > 0): ?>
            <p><strong>Jatah untuk Pekurban:</strong> <?php echo $jatah_pekurban_kg; ?> kg/orang</p>
            <p><strong>Jatah untuk Panitia:</strong> <?php echo $jatah_panitia_kg; ?> kg/orang</p>
            <p><strong>Estimasi Jatah per Penerima Umum:</strong> <?php echo number_format($jatah_warga_penerima_kg, 2); ?> kg/orang</p>
            <p>Sisa daging (setelah pekurban & panitia): <?php echo number_format($sisa_daging_untuk_penerima_umum, 2); ?> kg akan dibagi rata untuk <?php echo $jumlah_penerima_umum; ?> penerima umum.</p>
        <?php else: ?>
            <p style="color: red; font-weight: bold;">Tidak ada daging qurban yang tersedia untuk dibagikan.</p>
        <?php endif; ?>
    </div>

    <form action="" method="POST" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="generate_plan">
        <div class="form-group">
            <label for="tanggal_distribusi">Tanggal Distribusi Daging:</label>
            <input type="date" id="tanggal_distribusi" name="tanggal_distribusi" value="<?php echo htmlspecialchars($tanggal_distribusi_default); ?>" required <?php echo ($total_daging_tersedia <= 0) ? 'disabled' : ''; ?>>
        </div>
        <button type="submit" class="btn btn-primary" <?php echo ($total_daging_tersedia <= 0) ? 'disabled' : ''; ?>>Generate & Simpan Rencana Pembagian</button>
        <small style="margin-left: 10px;">(Ini akan menghapus rencana lama dan membuat yang baru, memastikan setiap warga hanya mendapat satu jatah terbaik)</small>
        <?php if ($total_daging_tersedia <= 0): ?>
            <small style="color: red; display: block; margin-top: 5px;">Tombol dinonaktifkan karena tidak ada daging qurban.</small>
        <?php endif; ?>
    </form>

    <h3>Daftar Penerima Daging</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIK Warga</th>
                <th>Nama Warga</th>
                <th>Jatah Daging (kg)</th>
                <th>Kategori Jatah</th>
                <th>Tgl. Distribusi</th>
                <th>Status Pengambilan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($data_pembagian)) {
                $no = 1;
                foreach($data_pembagian as $row) {
                    $kategori_display = '';
                    // Re-derive kategori berdasarkan jatah
                    if ($row['jumlah_daging_kg'] == $jatah_pekurban_kg) {
                        $kategori_display = 'Pekurban';
                    } elseif ($row['jumlah_daging_kg'] == $jatah_panitia_kg) {
                        $kategori_display = 'Panitia';
                    } else {
                        $kategori_display = 'Penerima Umum';
                    }

                    echo "<tr>";
                    echo "<td>" . $no++ . "</td>";
                    echo "<td>" . htmlspecialchars($row['nik_warga']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                    echo "<td>" . number_format($row['jumlah_daging_kg'], 2) . " kg</td>";
                    echo "<td>" . htmlspecialchars($kategori_display) . "</td>";
                    echo "<td>" . htmlspecialchars($row['tanggal_distribusi']) . "</td>";
                    echo '<td>';
                    echo '<form action="" method="POST" style="display: inline-block;">';
                    echo '<input type="hidden" name="action" value="update_status_pengambilan">';
                    echo '<input type="hidden" name="id_pembagian" value="' . htmlspecialchars($row['id']) . '">';
                    echo '<input type="checkbox" name="status_pengambilan" value="1" ' . ($row['status_pengambilan'] ? 'checked' : '') . ' onchange="this.form.submit()">';
                    echo '<label for="status_pengambilan">' . ($row['status_pengambilan'] ? 'Sudah Diambil' : 'Belum Diambil') . '</label>';
                    echo '</form>';
                    echo '</td>';
                    echo '<td>
                            <a href="delete_pembagian.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus catatan pembagian ini?\')">Hapus</a>
                          </td>';
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>Tidak ada rencana pembagian daging. Silakan 'Generate & Simpan Rencana Pembagian' jika daging qurban tersedia.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php
include '../../includes/footer.php'; // Sertakan footer
?>