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

// LOGIKA PENTING: Jika total daging tersedia 0, kosongkan tabel pembagian_daging
if ($total_daging_tersedia <= 0) {
    // Hanya TRUNCATE jika bukan POST request untuk generate_plan (yang punya logikanya sendiri)
    if (!isset($_POST['action']) || (isset($_POST['action']) && $_POST['action'] !== 'generate_plan')) {
        try {
            $conn->query("TRUNCATE TABLE pembagian_daging");
            if (!isset($_SESSION['message']) || $_SESSION['message_type'] !== 'info') { // Hindari duplikasi pesan jika sudah di-TRUNCATE
                $_SESSION['message'] = "Tabel pembagian daging dikosongkan karena tidak ada daging qurban yang tersedia.";
                $_SESSION['message_type'] = "info";
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = "Gagal mengosongkan tabel pembagian: " . $e->getMessage();
        }
    }
    
    // Set variabel-variabel terkait jatah menjadi nol atau tidak relevan
    $jumlah_pekurban = 0;
    $jumlah_panitia = 0;
    $jumlah_penerima_umum = 0;
    $sisa_daging_untuk_penerima_umum = 0;
    $jatah_warga_penerima_kg = 0;
    $final_recipients = []; // Pastikan ini juga kosong

    // Tambahkan pesan error jika memang belum ada daging
    if (!isset($_SESSION['message_type']) || ($_SESSION['message_type'] !== 'info' && $_SESSION['message_type'] !== 'success')) {
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
    $jumlah_pekurban = 0; // Reset untuk perhitungan akurat dari final_recipients
    $jumlah_panitia = 0; // Reset untuk perhitungan akurat dari final_recipients
    foreach ($final_recipients as $recipient) {
        if ($recipient['kategori'] === 'pekurban') {
            $jumlah_pekurban++;
        } elseif ($recipient['kategori'] === 'panitia') {
            $jumlah_panitia++;
        }
    }

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
            $id_qurban_placeholder = 0; // Tetap 0 atau NULL karena bukan dari 1 hewan spesifik
            
            foreach ($final_recipients as $recipient) {
                $nik = $recipient['nik'];
                $jumlah_daging = $recipient['jatah_kg'];
                
                if ($jumlah_daging > 0) { // Hanya masukkan yang jatahnya > 0
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
include '../../includes/header.php'; // Sertakan header SB Admin 2

// Ambil data pembagian daging yang sudah tersimpan untuk ditampilkan di tabel
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

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manajemen Pembagian Daging Qurban</h1>
</div>

<?php
// Tampilkan pesan sukses/error/info (yang kita simpan di $_SESSION)
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . ($_SESSION['message_type'] == 'error' ? 'danger' : ($_SESSION['message_type'] == 'info' ? 'info' : 'success')) . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['message']);
    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    echo '</div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
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
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Rangkuman Alokasi Daging</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6 mb-3">
                <p class="mb-1"><strong>Total Daging Qurban Tersedia:</strong> <span class="text-primary font-weight-bold"><?php echo number_format($total_daging_tersedia, 2); ?> kg</span></p>
                <?php if ($total_daging_tersedia > 0): ?>
                    <p class="mb-1"><strong>Jatah per Pekurban:</strong> <?php echo $jatah_pekurban_kg; ?> kg/orang</p>
                    <p class="mb-1"><strong>Jatah per Panitia:</strong> <?php echo $jatah_panitia_kg; ?> kg/orang</p>
                    <p class="mb-1"><strong>Estimasi Jatah per Penerima Umum:</strong> <span class="font-weight-bold text-info"><?php echo number_format($jatah_warga_penerima_kg, 2); ?> kg/orang</span></p>
                <?php else: ?>
                    <p class="text-danger font-weight-bold">Tidak ada daging qurban yang tersedia untuk dibagikan.</p>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <p class="mb-1"><strong>Jumlah Pekurban (yang menerima jatah):</strong> <?php echo $jumlah_pekurban; ?> Orang</p>
                <p class="mb-1"><strong>Jumlah Panitia (yang menerima jatah):</strong> <?php echo $jumlah_panitia; ?> Orang</p>
                <p class="mb-1"><strong>Jumlah Penerima Umum (yang menerima jatah):</strong> <?php echo $jumlah_penerima_umum; ?> Orang</p>
                <p class="mb-1"><strong>Sisa Daging untuk Penerima Umum:</strong> <span class="font-weight-bold text-info"><?php echo number_format($sisa_daging_untuk_penerima_umum, 2); ?> kg</span></p>
            </div>
        </div>
        <hr>
        <form action="" method="POST" class="mb-0">
            <input type="hidden" name="action" value="generate_plan">
            <div class="form-group row align-items-center mb-0">
                <label for="tanggal_distribusi" class="col-sm-4 col-form-label">Tanggal Distribusi Daging:</label>
                <div class="col-sm-5">
                    <input type="date" id="tanggal_distribusi" name="tanggal_distribusi" class="form-control" value="<?php echo htmlspecialchars($tanggal_distribusi_default); ?>" required <?php echo ($total_daging_tersedia <= 0) ? 'disabled' : ''; ?>>
                </div>
                <div class="col-sm-3">
                    <button type="submit" class="btn btn-primary btn-block" <?php echo ($total_daging_tersedia <= 0) ? 'disabled' : ''; ?>>
                        <i class="fas fa-sync-alt fa-sm text-white-50"></i> Generate & Simpan Rencana
                    </button>
                </div>
            </div>
            <?php if ($total_daging_tersedia <= 0): ?>
                <small class="text-danger mt-2 d-block">Tombol dinonaktifkan karena tidak ada daging qurban.</small>
            <?php endif; ?>
            <small class="form-text text-muted">Aksi ini akan menghapus semua rencana pembagian sebelumnya dan membuat yang baru berdasarkan data terkini.</small>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Penerima Daging Qurban</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
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
                <tfoot>
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
                </tfoot>
                <tbody>
                    <?php
                    if (!empty($data_pembagian)) {
                        $no = 1;
                        foreach($data_pembagian as $row) {
                            $kategori_display = '';
                            // Re-derive kategori berdasarkan jatah
                            if ($row['jumlah_daging_kg'] == $jatah_pekurban_kg) {
                                $kategori_display = '<span class="badge badge-success">Pekurban</span>';
                            } elseif ($row['jumlah_daging_kg'] == $jatah_panitia_kg) {
                                $kategori_display = '<span class="badge badge-primary">Panitia</span>';
                            } else {
                                $kategori_display = '<span class="badge badge-info">Penerima Umum</span>';
                            }

                            echo "<tr>";
                            echo "<td>" . $no++ . "</td>";
                            echo "<td>" . htmlspecialchars($row['nik_warga']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                            echo "<td>" . number_format($row['jumlah_daging_kg'], 2) . " kg</td>";
                            echo "<td>" . $kategori_display . "</td>";
                            echo "<td>" . htmlspecialchars($row['tanggal_distribusi']) . "</td>";
                            echo '<td>';
                            echo '<form action="" method="POST" class="d-inline-block">'; // Gunakan d-inline-block
                            echo '<input type="hidden" name="action" value="update_status_pengambilan">';
                            echo '<input type="hidden" name="id_pembagian" value="' . htmlspecialchars($row['id']) . '">';
                            echo '<div class="custom-control custom-checkbox">'; // Gunakan Bootstrap custom checkbox
                            echo '<input type="checkbox" class="custom-control-input" id="status_ambil_' . $row['id'] . '" name="status_pengambilan" value="1" ' . ($row['status_pengambilan'] ? 'checked' : '') . ' onchange="this.form.submit()">';
                            echo '<label class="custom-control-label" for="status_ambil_' . $row['id'] . '">' . ($row['status_pengambilan'] ? 'Sudah Diambil' : 'Belum Diambil') . '</label>';
                            echo '</div>';
                            echo '</form>';
                            echo '</td>';
                            echo '<td>
                                    <a href="delete_pembagian.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-danger btn-circle btn-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus catatan pembagian ini?\')" title="Hapus Catatan"><i class="fas fa-trash"></i></a>
                                  </td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Tidak ada rencana pembagian daging. Silakan 'Generate & Simpan Rencana Pembagian' jika daging qurban tersedia.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>