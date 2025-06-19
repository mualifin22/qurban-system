<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin() && !isPanitia()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

$sql = "SELECT id, nama_barang, jumlah, harga_satuan, tanggal_beli FROM perlengkapan ORDER BY tanggal_beli DESC, nama_barang ASC";
$result = $conn->query($sql);

$perlengkapan_data = [];
$total_biaya_perlengkapan = 0;
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $perlengkapan_data[] = $row;
        $total_biaya_perlengkapan += ($row['jumlah'] * $row['harga_satuan']);
    }
}

include '../../includes/header.php'; 
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manajemen Perlengkapan Qurban</h1>
    <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Perlengkapan Baru
    </a>
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
?>

<div class="row">
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Biaya Perlengkapan Tercatat</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($total_biaya_perlengkapan); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Perlengkapan</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                        <th>Tanggal Beli</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>ID</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                        <th>Tanggal Beli</th>
                        <th>Aksi</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    if (!empty($perlengkapan_data)) {
                        foreach($perlengkapan_data as $row) {
                            $total_harga_item = $row['jumlah'] * $row['harga_satuan'];
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['jumlah']) . "</td>";
                            echo "<td>" . formatRupiah($row['harga_satuan']) . "</td>";
                            echo "<td>" . formatRupiah($total_harga_item) . "</td>";
                            echo "<td>" . htmlspecialchars($row['tanggal_beli']) . "</td>";
                            echo '<td>
                                    <a href="edit.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-circle btn-sm" title="Edit Perlengkapan"><i class="fas fa-edit"></i></a>
                                    <a href="delete.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-danger btn-circle btn-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus perlengkapan ini?\')" title="Hapus Perlengkapan"><i class="fas fa-trash"></i></a>
                                  </td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Tidak ada data perlengkapan.</td></tr>";
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
