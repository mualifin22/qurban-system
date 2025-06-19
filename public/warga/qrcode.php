<?php
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

$nik_warga_login = $_SESSION['nik_warga'] ?? null;
$user_role = $_SESSION['role'];
$target_nik = $nik_warga_login;
$errors = [];

if ((isAdmin() || isPanitia()) && isset($_GET['nik']) && !empty($_GET['nik'])) {
    $requested_nik = sanitizeInput($_GET['nik']);

    $stmt_check_nik = $conn->prepare("SELECT COUNT(*) FROM warga WHERE nik = ?");
    $stmt_check_nik->bind_param("s", $requested_nik);
    $stmt_check_nik->execute();
    $result_check_nik = $stmt_check_nik->get_result();
    if ($result_check_nik->fetch_row()[0] > 0) {
        $target_nik = $requested_nik;
    } else {
        $errors[] = "NIK warga yang dicari tidak ditemukan.";
        $target_nik = $nik_warga_login; 
    }
    $stmt_check_nik->close();
}

if (empty($target_nik)) {
    $_SESSION['message'] = "Tidak ada NIK warga yang valid untuk ditampilkan Kartu Qurban. Pastikan akun Anda terhubung dengan data warga atau cari NIK warga lain.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

$warga_data = null;
$stmt_warga = $conn->prepare("SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga WHERE nik = ?");
$stmt_warga->bind_param("s", $target_nik);
$stmt_warga->execute();
$result_warga = $stmt_warga->get_result();
if ($result_warga->num_rows > 0) {
    $warga_data = $result_warga->fetch_assoc();
} else {
    $errors[] = "Data warga dengan NIK " . htmlspecialchars($target_nik) . " tidak ditemukan.";
    $warga_data = null;
}
$stmt_warga->close();

$jatah_daging = 0;
$status_pengambilan = false;
$tanggal_distribusi_daging = 'Belum ditentukan';

if ($warga_data) {
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
        $errors[] = "Data pembagian daging untuk NIK " . htmlspecialchars($warga_data['nik'] ?? '') . " belum tersedia. Silakan hubungi panitia.";
    }
    $stmt_jatah->close();
}

$qr_content_data = [
    "NIK"                 => $warga_data['nik'] ?? 'N/A',
    "Nama"                => $warga_data['nama'] ?? 'N/A',
    "Jatah_Daging"        => number_format($jatah_daging, 2) . " kg",
    "Status_Pengambilan"  => $status_pengambilan ? "Sudah Diambil" : "Belum Diambil",
    "Tanggal_Distribusi"  => $tanggal_distribusi_daging
];
$qr_content_json = json_encode($qr_content_data); 

$qrcode_placeholder_id = "qrcodeCanvas"; 
include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Kartu Pengambilan Daging Qurban</h1>
</div>

<?php
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

<div class="row justify-content-center">
    <div class="col-lg-6">
        <?php if ($warga_data): ?>
            <div class="card shadow mb-4"> 
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Kartu Qurban RT 001</h6>
                </div>
                <div class="card-body" id="printableArea"> 
                    <div class="text-center mb-4">
                        <img src="/sistem_qurban/public/img/qurban_logo.png" alt="Logo Qurban" style="max-width: 100px; margin-bottom: 15px;">
                        <h4 class="text-gray-900">INFORMASI PENGAMBILAN DAGING</h4>
                        <p class="text-muted">RT 001 - Desa AAAA</p>
                    </div>
                    
                    <div class="row align-items-center mb-3">
                        <div class="col-md-6 text-center">
                            <div id="<?php echo $qrcode_placeholder_id; ?>" style="border: 1px solid #ddd; padding: 5px; max-width: 200px; margin: 0 auto;"></div>
                            <p class="small text-muted mt-2 mb-0">Scan untuk Verifikasi</p>
                        </div>
                        <div class="col-md-6 text-left">
                            <h5 class="text-primary font-weight-bold"><?php echo htmlspecialchars($warga_data['nama']); ?></h5>
                            <p class="text-gray-800 mb-1"><strong>NIK:</strong> <?php echo htmlspecialchars($warga_data['nik']); ?></p>
                            <p class="text-gray-800 mb-1"><strong>Jatah Daging:</strong> <span class="font-weight-bold text-success"><?php echo number_format($jatah_daging, 2); ?> kg</span></p>
                            <p class="text-gray-800 mb-1"><strong>Tgl. Distribusi:</strong> <?php echo htmlspecialchars($tanggal_distribusi_daging); ?></p>
                            <p class="text-gray-800 mb-1"><strong>Status:</strong> 
                                <span class="badge badge-<?php echo $status_pengambilan ? 'success' : 'warning'; ?> p-2">
                                    <?php echo $status_pengambilan ? 'SUDAH DIAMBIL' : 'BELUM DIAMBIL'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="text-center">
                        <p class="small text-gray-700">Harap tunjukkan kartu ini kepada panitia saat pengambilan daging.</p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3 mb-4">
                <button type="button" class="btn btn-info btn-lg" onclick="printCard()">
                    <i class="fas fa-print"></i> Cetak Kartu Ini
                </button>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Tidak ada data warga yang valid untuk ditampilkan Kartu Qurban.
            </div>
        <?php endif; ?>
    </div>

    <?php if (isAdmin() || isPanitia()): ?>
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pencarian Kartu Warga Lain (Panitia/Admin)</h6>
            </div>
            <div class="card-body">
                <form action="" method="GET" class="form-inline justify-content-center">
                    <div class="form-group mb-2 mr-sm-2">
                        <label for="search_nik" class="sr-only">Cari NIK Warga:</label>
                        <input type="text" class="form-control" id="search_nik" name="nik" value="<?php echo htmlspecialchars($target_nik); ?>" placeholder="Masukkan NIK" required>
                    </div>
                    <button type="submit" class="btn btn-primary mb-2">Cari Kartu</button>
                </form>
                <hr class="mt-4">
                <p class="small text-muted text-center">Fitur ini membantu panitia untuk mencari dan memverifikasi kartu qurban warga lain tanpa perlu login sebagai warga tersebut.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
include '../../includes/footer.php';
?>

<script src="/sistem_qurban/public/js/qrcode.min.js"></script>

<script>
var qrContentJson = <?php echo $qr_content_json; ?>;
var qrcodePlaceholderId = "<?php echo $qrcode_placeholder_id; ?>";
var qrcodeInstance = null; 

document.addEventListener('DOMContentLoaded', function() {
    if (qrContentJson) {
        var element = document.getElementById(qrcodePlaceholderId);
        if (element) {
            qrcodeInstance = new QRCode(element, {
                text: JSON.stringify(qrContentJson), 
                width: 200, 
                height: 200,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.L // ECC Level L
            });

            setTimeout(function() {
                var qrCanvas = element.querySelector('canvas');
                if (qrCanvas) {
                    window.qrImageDataUrlForPrint = qrCanvas.toDataURL("image/png");
                }
            }, 100); // Tunda sebentar
        }
    }
});


function printCard() {
    var printContents = document.getElementById('printableArea').innerHTML;
    var printWindow = window.open('', '_blank', 'height=600,width=800');

    printWindow.document.write('<html><head><title>Cetak Kartu Qurban</title>');
    
    printWindow.document.write('<link href="/sistem_qurban/public/css/sb-admin-2.min.css" rel="stylesheet">');
    printWindow.document.write('<link href="/sistem_qurban/public/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">');
    printWindow.document.write('<link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">');

    printWindow.document.write('<link href="/sistem_qurban/public/css/print.css" rel="stylesheet" media="print">');


    printWindow.document.write('</head><body>');
    printWindow.document.write('<div style="width: 100%; max-width: 400px; margin: 0 auto; padding: 10px;">');
    
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = printContents;
    var qrImgElement = tempDiv.querySelector('#' + qrcodePlaceholderId + ' canvas'); // Cari elemen canvas di dalam placeholder
    
    if (qrImgElement && window.qrImageDataUrlForPrint) {
        var imgTag = document.createElement('img');
        imgTag.src = window.qrImageDataUrlForPrint;
        imgTag.alt = "QR Code Pengambilan Daging";
        imgTag.style.maxWidth = '200px'; // Sesuaikan dengan ukuran yang diinginkan
        imgTag.style.height = 'auto';
        imgTag.style.display = 'block';
        imgTag.style.margin = '0 auto';
        
        qrImgElement.parentNode.replaceChild(imgTag, qrImgElement);
    }
    printWindow.document.write(tempDiv.innerHTML);
    
    printWindow.document.write('</div>');
    
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();

    printWindow.onload = function() {
        setTimeout(function() {
            printWindow.print();
        }, 1000);
    };
}
</script>
