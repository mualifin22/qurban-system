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

// Hanya Admin atau Panitia yang bisa mengakses halaman ini
if (!isAdmin() && !isPanitia()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

// Ambil data hewan qurban
$sql = "SELECT h.id, h.jenis_hewan, h.harga, h.biaya_administrasi, h.tanggal_beli, h.estimasi_berat_daging_kg, h.nik_peserta_tunggal
        FROM hewan_qurban h ORDER BY h.tanggal_beli DESC";
$result = $conn->query($sql);

$hewan_qurban_data = [];
$total_kambing = 0;
$total_sapi = 0;
$total_estimasi_daging = 0;

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $hewan_qurban_data[] = $row;
        if ($row['jenis_hewan'] === 'kambing') {
            $total_kambing++;
        } elseif ($row['jenis_hewan'] === 'sapi') {
            $total_sapi++;
        }
        $total_estimasi_daging += (float)$row['estimasi_berat_daging_kg'];
    }
}

// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../../includes/header.php'; // Sertakan header SB Admin 2
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manajemen Data Hewan Qurban</h1>
    <div class="d-none d-sm-inline-block">
        <a href="add_kambing.php" class="btn btn-sm btn-primary shadow-sm mr-2">
            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Qurban Kambing
        </a>
        <a href="add_sapi.php" class="btn btn-sm btn-info shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Qurban Sapi
        </a>
    </div>
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
?>

<div class="row">

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Kambing</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_kambing); ?> Ekor</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-sheep fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Sapi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_sapi); ?> Ekor</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-cow fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Estimasi Daging</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_estimasi_daging, 2); ?> Kg</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-weight-hanging fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Hewan Qurban</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jenis Hewan</th>
                        <th>Harga</th>
                        <th>Biaya Admin</th>
                        <th>Tgl. Beli</th>
                        <th>Est. Daging (kg)</th>
                        <th>Peserta</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>ID</th>
                        <th>Jenis Hewan</th>
                        <th>Harga</th>
                        <th>Biaya Admin</th>
                        <th>Tgl. Beli</th>
                        <th>Est. Daging (kg)</th>
                        <th>Peserta</th>
                        <th>Aksi</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    if (!empty($hewan_qurban_data)) {
                        foreach($hewan_qurban_data as $row) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst($row['jenis_hewan'])) . "</td>";
                            echo "<td>" . formatRupiah($row['harga']) . "</td>";
                            echo "<td>" . formatRupiah($row['biaya_administrasi']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['tanggal_beli']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['estimasi_berat_daging_kg']) . "</td>";
                            echo "<td>";
                            if ($row['jenis_hewan'] === 'sapi') {
                                $sql_peserta = "SELECT w.nama FROM peserta_sapi ps JOIN warga w ON ps.nik_warga = w.nik WHERE ps.id_hewan_qurban = ?";
                                $stmt_peserta = $conn->prepare($sql_peserta);
                                $stmt_peserta->bind_param("i", $row['id']);
                                $stmt_peserta->execute();
                                $result_peserta = $stmt_peserta->get_result();
                                $peserta_names = [];
                                while($p = $result_peserta->fetch_assoc()) {
                                    $peserta_names[] = htmlspecialchars($p['nama']);
                                }
                                echo implode(", ", $peserta_names);
                                $stmt_peserta->close();
                            } elseif ($row['jenis_hewan'] === 'kambing' && !empty($row['nik_peserta_tunggal'])) {
                                $sql_nama_peserta_kambing = "SELECT nama FROM warga WHERE nik = ?";
                                $stmt_nama_peserta_kambing = $conn->prepare($sql_nama_peserta_kambing);
                                $stmt_nama_peserta_kambing->bind_param("s", $row['nik_peserta_tunggal']);
                                $stmt_nama_peserta_kambing->execute();
                                $result_nama_peserta_kambing = $stmt_nama_peserta_kambing->get_result();
                                if ($result_nama_peserta_kambing->num_rows > 0) {
                                    $nama_kambing = $result_nama_peserta_kambing->fetch_assoc()['nama'];
                                    echo htmlspecialchars($nama_kambing);
                                } else {
                                    echo "Tidak diketahui (NIK: " . htmlspecialchars($row['nik_peserta_tunggal']) . ")";
                                }
                                $stmt_nama_peserta_kambing->close();
                            } else {
                                echo "N/A";
                            }
                            echo "</td>";
                            echo '<td>
                                    <a href="edit.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-circle btn-sm" title="Edit Hewan Qurban"><i class="fas fa-edit"></i></a>
                                    <a href="delete_qurban.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-danger btn-circle btn-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus hewan qurban ini?\')" title="Hapus Hewan Qurban"><i class="fas fa-trash"></i></a>
                                  </td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Tidak ada data hewan qurban.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php'; // Sertakan footer
?>