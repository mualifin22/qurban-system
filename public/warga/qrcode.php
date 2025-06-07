<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================================
// Bagian Pemrosesan Logika PHP (Harus di atas, sebelum output HTML dimulai)
// =========================================================================

include '../../includes/db.php';        // Koneksi database
include '../../includes/functions.php';  // Fungsi-fungsi helper

// Pastikan session sudah dimulai. Ini penting untuk mengakses $_SESSION.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan autoload Composer untuk library QR Code
require_once '../../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};

// Cek apakah user sudah login. Jika belum, redirect ke halaman login.
if (!isLoggedIn()) {
    redirectToLogin();
}

// Inisialisasi variabel untuk NIK warga yang akan ditampilkan QR Code-nya.
// Defaultnya adalah NIK dari user yang sedang login.
$nik_warga_login = $_SESSION['nik_warga'] ?? null; // Gunakan null coalescing operator untuk handle jika nik_warga tidak ada di session
$user_role = $_SESSION['role'];
$target_nik = $nik_warga_login;
$errors = []; // Untuk menyimpan pesan error

// Logika untuk Admin atau Panitia yang mencari QR Code warga lain:
// Jika user adalah Admin atau Panitia DAN ada parameter 'nik' di URL (GET),
// maka gunakan NIK dari URL sebagai target.
if ((isAdmin() || isPanitia()) && isset($_GET['nik']) && !empty($_GET['nik'])) {
    $requested_nik = sanitizeInput($_GET['nik']);

    // Validasi tambahan: Pastikan NIK yang diminta memang ada di database.
    $stmt_check_nik = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
    $stmt_check_nik->bind_param("s", $requested_nik);
    $stmt_check_nik->execute();
    $result_check_nik = $stmt_check_nik->get_result();
    if ($result_check_nik->fetch_row()[0] > 0) {
        $target_nik = $requested_nik; // NIK valid, gunakan NIK ini
    } else {
        $errors[] = "NIK warga yang dicari tidak ditemukan.";
        // Jika NIK dari GET tidak valid, tetap tampilkan QR code user yang login (jika ada)
        $target_nik = $nik_warga_login;
    }
    $stmt_check_nik->close();
}

// Jika setelah semua logika di atas, target_nik masih kosong (misal: admin tanpa NIK pribadi dan tidak mencari NIK lain)
if (empty($target_nik)) {
    $_SESSION['message'] = "Tidak ada NIK warga yang valid untuk ditampilkan QR Code-nya. Pastikan akun Anda terhubung dengan data warga atau cari NIK warga lain.";
    $_SESSION['message_type'] = "error";
    // Redirect ke dashboard karena tidak bisa menampilkan QR code tanpa NIK warga
    header("Location: ../dashboard.php");
    exit();
}

// Ambil data detail warga berdasarkan $target_nik
$warga_data = null;
$stmt_warga = $conn->prepare("SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga WHERE nik = ?");
$stmt_warga->bind_param("s", $target_nik);
$stmt_warga->execute();
$result_warga = $stmt_warga->get_result();
if ($result_warga->num_rows > 0) {
    $warga_data = $result_warga->fetch_assoc();
} else {
    // Ini seharusnya tidak terjadi jika $target_nik sudah divalidasi, tapi sebagai safeguard
    $errors[] = "Data warga dengan NIK " . htmlspecialchars($target_nik) . " tidak ditemukan.";
    $warga_data = null; // Pastikan null jika data tidak ditemukan
}
$stmt_warga->close();

// Ambil informasi jatah daging dari tabel `pembagian_daging` untuk warga ini
$jatah_daging = 0;
$status_pengambilan = false;
$tanggal_distribusi_daging = 'Belum ditentukan';

if ($warga_data) { // Hanya jika data warga ditemukan
    $stmt_jatah = $conn->prepare("SELECT jumlah_daging_kg, status_pengambilan, tanggal_distribusi FROM pembagian_daging WHERE nik_warga = ? ORDER BY tanggal_distribusi DESC LIMIT 1");
    $stmt_jatah->bind_param("s", $warga_data['nik']);
    $stmt_jatah->execute();
    $result_jatah = $stmt_jatah->get_result();
    if ($result_jatah->num_rows > 0) {
        $data_jatah = $result_jatah->fetch_assoc();
        $jatah_daging = (float) $data_jatah['jumlah_daging_kg'];
        $status_pengambilan = (bool) $data_jatah['status_pengambilan'];
        $tanggal_distribusi_daging = htmlspecialchars($data_jatah['tanggal_distribusi']);
    } else {
        $errors[] = "Data pembagian daging untuk NIK " . htmlspecialchars($warga_data['nik']) . " belum tersedia. Silakan hubungi panitia.";
    }
    $stmt_jatah->close();
}


// Siapkan konten untuk QR Code (dalam format JSON string)
$qr_content_data = [
    "NIK"                 => $warga_data['nik'] ?? 'N/A',
    "Nama"                => $warga_data['nama'] ?? 'N/A',
    "Jatah_Daging"        => number_format($jatah_daging, 2) . " kg",
    "Status_Pengambilan"  => $status_pengambilan ? "Sudah Diambil" : "Belum Diambil",
    "Tanggal_Distribusi"  => $tanggal_distribusi_daging
];
$qr_content = json_encode($qr_content_data);

// Generate QR Code
$options = new QROptions([
    'outputType'  => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel'    => QRCode::ECC_L,       // Error Correction Capability Low
    'scale'       => 8,                   // Ukuran QR Code
    'imageBase64' => true,                // Output sebagai Base64 string agar bisa langsung di-embed di HTML
    'bgColor'     => [255, 255, 255],     // Latar belakang putih
    'fgColor'     => [0, 0, 0]            // Foreground hitam
]);

// Handle kasus jika qr_content kosong atau ada masalah saat generate
$qrcode = '';
try {
    $qrcode = (new QRCode($options))->render($qr_content);
} catch (\Throwable $th) {
    $errors[] = "Gagal membuat QR Code: " . $th->getMessage();
    $qrcode = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; // Placeholder blank image
}


// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../../includes/header.php'; // Sertakan header setelah semua logika PHP selesai
?>

<div class="container">
    <h2>Kartu Pengambilan Daging Qurban</h2>
    <?php
    // Tampilkan pesan error jika ada (dari validasi NIK, atau gagal generate QR)
    if (!empty($errors)) {
        echo '<div class="message error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    // Tampilkan pesan session jika ada (dari redirect sebelumnya)
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>

    <?php if ($warga_data): // Tampilkan kartu hanya jika data warga valid ?>
        <div style="text-align: center; border: 1px solid #ccc; padding: 20px; border-radius: 8px; max-width: 400px; margin: 20px auto; background-color: #fff;">
            <h3>Kartu Qurban RT 001</h3>
            <p><strong>Nama:</strong> <?php echo htmlspecialchars($warga_data['nama']); ?></p>
            <p><strong>NIK:</strong> <?php echo htmlspecialchars($warga_data['nik']); ?></p>
            <p><strong>Jatah Daging:</strong> <?php echo number_format($jatah_daging, 2); ?> kg</p>
            <p><strong>Status Pengambilan:</strong> <span style="font-weight: bold; color: <?php echo $status_pengambilan ? 'green' : 'orange'; ?>;"><?php echo $status_pengambilan ? 'Sudah Diambil' : 'Belum Diambil'; ?></span></p>
            <p><strong>Tanggal Distribusi:</strong> <?php echo htmlspecialchars($tanggal_distribusi_daging); ?></p>
            <hr>
            <p>Tunjukkan QR Code ini ke Panitia untuk pengambilan daging.</p>
            <img src="<?php echo $qrcode; ?>" alt="QR Code Pengambilan Daging" style="max-width: 100%; height: auto; display: block; margin: 15px auto;">
            <p><small>Isi QR Code: <code><?php echo htmlspecialchars($qr_content); ?></code></small></p>
            <a href="#" onclick="window.print()" class="btn btn-primary" style="margin-top: 15px;">Cetak Kartu</a>
        </div>
    <?php else: ?>
        <div class="message error">Tidak ada data warga yang valid untuk ditampilkan kartunya.</div>
    <?php endif; ?>

    <?php if (isAdmin() || isPanitia()): ?>
    <div style="margin-top: 30px; text-align: center; border: 1px solid #ddd; padding: 15px; border-radius: 8px; max-width: 500px; margin: 30px auto; background-color: #f9f9f9;">
        <h3>Pencarian Kartu Warga Lain (Panitia/Admin)</h3>
        <form action="" method="GET">
            <div class="form-group" style="display: inline-block; margin-right: 10px; width: calc(100% - 130px);">
                <label for="search_nik" style="display: block; text-align: left; margin-bottom: 5px;">Cari NIK Warga:</label>
                <input type="text" id="search_nik" name="nik" value="<?php echo htmlspecialchars($target_nik); ?>" placeholder="Masukkan NIK" required style="width: 100%; padding: 8px;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 8px 15px;">Cari Kartu</button>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php
include '../../includes/footer.php'; // Sertakan footer
?>