<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Hanya panitia atau admin yang bisa mengakses halaman ini
if (!is_logged_in() || (!has_role('panitia') && !has_role('admin'))) {
    redirect('/qurban_app/login.php');
}

$message = '';
$daging_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data_input = sanitize_input($_POST['qr_data']);
    // Contoh: QURBAN_RT001_ID:123
    $parts = explode(':', $qr_data_input);
    if (count($parts) === 2 && $parts[0] === 'QURBAN_RT001_ID') {
        $pembagian_id = (int)$parts[1];

        $stmt_check = $conn->prepare("SELECT pd.*, w.nama FROM pembagian_daging pd JOIN warga w ON pd.warga_id = w.id WHERE pd.id = ?");
        $stmt_check->bind_param("i", $pembagian_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $daging_info = $result_check->fetch_assoc();
            if ($daging_info['status_pengambilan'] === 'sudah diambil') {
                $message = "Daging untuk " . $daging_info['nama'] . " sudah diambil pada " . date('d-m-Y H:i', strtotime($daging_info['tanggal_pengambilan'])) . ".";
            } else {
                // Konfirmasi pengambilan
                if (isset($_POST['confirm_take']) && $_POST['confirm_take'] === 'yes') {
                    $stmt_update = $conn->prepare("UPDATE pembagian_daging SET status_pengambilan = 'sudah diambil', tanggal_pengambilan = NOW() WHERE id = ?");
                    $stmt_update->bind_param("i", $pembagian_id);
                    if ($stmt_update->execute()) {
                        $message = "Pengambilan daging untuk " . $daging_info['nama'] . " berhasil dicatat.";
                        $daging_info['status_pengambilan'] = 'sudah diambil'; // Update info untuk tampilan
                        $daging_info['tanggal_pengambilan'] = date('Y-m-d H:i:s');
                    } else {
                        $message = "Gagal mencatat pengambilan: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
            }
        } else {
            $message = "QR Code tidak valid atau data tidak ditemukan.";
        }
        $stmt_check->close();
    } else {
        $message = "Format QR Code tidak dikenal.";
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Verifikasi Pengambilan Daging Qurban</h2>
<?php if ($message): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<p>Panitia dapat memindai atau memasukkan data dari QR Code di sini.</p>
<form action="qr_scan.php" method="post">
    <label for="qr_data">Data QR Code:</label><br>
    <input type="text" id="qr_data" name="qr_data" placeholder="Masukkan data dari QR Code" required><br><br>
    <input type="submit" value="Cari Data">
</form>

<?php if ($daging_info): ?>
    <h3>Detail Pengambilan</h3>
    <p>Nama Warga: <strong><?php echo $daging_info['nama']; ?></strong></p>
    <p>Kategori: <strong><?php echo ucfirst($daging_info['kategori']); ?></strong></p>
    <p>Jumlah Daging: <strong><?php echo $daging_info['jumlah_kg']; ?> kg</strong></p>
    <p>Status: <strong><?php echo ucfirst($daging_info['status_pengambilan']); ?></strong></p>
    <?php if ($daging_info['status_pengambilan'] === 'sudah diambil'): ?>
        <p>Diambil pada: <?php echo date('d-m-Y H:i', strtotime($daging_info['tanggal_pengambilan'])); ?></p>
    <?php else: ?>
        <form action="qr_scan.php" method="post">
            <input type="hidden" name="qr_data" value="<?php echo htmlspecialchars($_POST['qr_data']); ?>">
            <input type="hidden" name="confirm_take" value="yes">
            <p>Konfirmasi pengambilan daging?</p>
            <input type="submit" value="Konfirmasi Pengambilan">
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
