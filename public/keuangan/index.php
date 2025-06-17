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

// Ambil data keuangan
$sql = "SELECT id, jenis, keterangan, jumlah, tanggal, id_hewan_qurban FROM keuangan ORDER BY tanggal DESC, id DESC";
$result = $conn->query($sql);

$total_pemasukan = 0;
$total_pengeluaran = 0;
$saldo_akhir = 0;

$data_keuangan = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data_keuangan[] = $row;
        if ($row['jenis'] === 'pemasukan') {
            $total_pemasukan += $row['jumlah'];
        } else {
            $total_pengeluaran += $row['jumlah'];
        }
    }
}
$saldo_akhir = $total_pemasukan - $total_pengeluaran;

// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../../includes/header.php'; // Sertakan header SB Admin 2
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Rekapan Keuangan Qurban RT 001</h1>
    <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Transaksi Manual
    </a>
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
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Pemasukan</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($total_pemasukan); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Total Pengeluaran</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($total_pengeluaran); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-<?php echo ($saldo_akhir >= 0) ? 'info' : 'warning'; ?> shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-<?php echo ($saldo_akhir >= 0) ? 'info' : 'warning'; ?> text-uppercase mb-1">Saldo Akhir
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($saldo_akhir); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Transaksi Keuangan</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Jumlah</th>
                        <th>Tanggal</th>
                        <th>Terkait Hewan Qurban</th> <th>Aksi</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>ID</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Jumlah</th>
                        <th>Tanggal</th>
                        <th>Terkait Hewan Qurban</th>
                        <th>Aksi</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    if (!empty($data_keuangan)) {
                        foreach($data_keuangan as $row) {
                            $is_linked_to_qurban = !empty($row['id_hewan_qurban']);
                            $row_class = $is_linked_to_qurban ? 'table-secondary' : ''; // Beri warna abu jika terkait Qurban
                            
                            echo "<tr class='".htmlspecialchars($row_class)."'>";
                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst($row['jenis'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['keterangan']) . "</td>";
                            echo "<td>" . formatRupiah($row['jumlah']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['tanggal']) . "</td>";
                            echo "<td>";
                            if ($is_linked_to_qurban) {
                                echo '<span class="badge badge-primary">Ya (ID: ' . htmlspecialchars($row['id_hewan_qurban']) . ')</span>';
                            } else {
                                echo '<span class="badge badge-secondary">Tidak</span>';
                            }
                            echo "</td>";
                            echo '<td>';
                            if (!$is_linked_to_qurban) { // Hanya izinkan edit/hapus jika tidak terkait hewan qurban
                                echo '<a href="edit.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-circle btn-sm" title="Edit Transaksi"><i class="fas fa-edit"></i></a> ';
                                echo '<a href="delete.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-danger btn-circle btn-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus transaksi ini?\')" title="Hapus Transaksi"><i class="fas fa-trash"></i></a>';
                            } else {
                                echo '<span class="text-muted small">Kelola di Data Qurban</span>';
                            }
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Tidak ada data transaksi keuangan.</td></tr>";
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