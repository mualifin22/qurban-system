<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Cek apakah sudah login sebagai warga atau berqurban
if (!is_logged_in() || (!has_role('warga') && !has_role('berqurban') && !has_role('admin'))) { // Admin juga bisa lihat untuk testing
    redirect('/qurban_app/login.php');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';

$user_id = $_SESSION['user_id'];
$warga_id = null;

// Dapatkan warga_id dari user_id
$stmt_warga = $conn->prepare("SELECT id FROM warga WHERE user_id = ?");
$stmt_warga->bind_param("i", $user_id);
$stmt_warga->execute();
$result_warga = $stmt_warga->get_result();
if ($result_warga->num_rows > 0) {
    $warga_id = $result_warga->fetch_assoc()['id'];
}
$stmt_warga->close();

if (!$warga_id) {
    echo "<p>Data warga Anda tidak ditemukan. Silakan hubungi admin.</p>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

// Ambil data pembagian daging untuk warga ini
$stmt_daging = $conn->prepare("SELECT * FROM pembagian_daging WHERE warga_id = ?");
$stmt_daging->bind_param("i", $warga_id);
$stmt_daging->execute();
$result_daging = $stmt_daging->get_result();

?>

<h2>Kartu Pengambilan Daging Qurban</h2>

<?php if ($result_daging->num_rows > 0): ?>
    <?php while ($daging = $result_daging->fetch_assoc()): ?>
        <div style="border: 1px solid black; padding: 10px; margin-bottom: 20px;">
            <h3>Untuk: <?php echo ucfirst($daging['kategori']); ?></h3>
            <p>Jumlah Daging: <strong><?php echo $daging['jumlah_kg']; ?> kg</strong></p>
            <p>Status Pengambilan: <strong><?php echo ucfirst($daging['status_pengambilan']); ?></strong></p>
            <?php if ($daging['status_pengambilan'] == 'sudah diambil' && $daging['tanggal_pengambilan']): ?>
                <p>Tanggal Pengambilan: <?php echo date('d-m-Y H:i', strtotime($daging['tanggal_pengambilan'])); ?></p>
            <?php endif; ?>

            <?php
            // Pastikan QR code sudah ada atau generate jika belum
            $qr_filename = 'qurban_' . $daging['id'] . '.png';
            $qr_filepath_relative = '/qurban_app/assets/qrcodes/' . $qr_filename;
            $qr_filepath_absolute = __DIR__ . '/../assets/qrcodes/' . $qr_filename;

            if (!file_exists($qr_filepath_absolute) || empty($daging['qr_code'])) {
                // Generate QR code jika belum ada
                $qr_data = "QURBAN_RT001_ID:" . $daging['id'];
                generate_qr_code($qr_data, $qr_filename);
                // Update database dengan nama file QR code
                $stmt_update_qr = $conn->prepare("UPDATE pembagian_daging SET qr_code = ? WHERE id = ?");
                $stmt_update_qr->bind_param("si", $qr_filename, $daging['id']);
                $stmt_update_qr->execute();
                $stmt_update_qr->close();
            }
            ?>
            <p>Tunjukkan QR Code ini kepada panitia saat pengambilan daging:</p>
            <img src="<?php echo $qr_filepath_relative; ?>" alt="QR Code Pengambilan Daging" width="200" height="200"><br>
            <a href="<?php echo $qr_filepath_relative; ?>" download="kartu_qurban_<?php echo $daging['id']; ?>.png">Download QR Code</a>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>Anda belum terdaftar untuk mendapatkan bagian daging qurban atau data belum diinput.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
