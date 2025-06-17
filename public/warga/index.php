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

// Ambil data user dari database
$sql = "SELECT w.nik, w.nama, w.alamat, w.no_hp, w.status_qurban, w.status_panitia, u.role as user_role
        FROM warga w
        LEFT JOIN users u ON w.nik = u.nik_warga
        ORDER BY w.nama ASC";
$result = $conn->query($sql);

$users_data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $users_data[] = $row;
    }
}

// --- Ambil Data untuk Ringkasan Dashboard ---
// Total Warga Terdaftar
$total_warga_terdaftar = 0;
$result_total_warga = $conn->query("SELECT COUNT(*) AS total FROM warga");
if ($result_total_warga) {
    $total_warga_terdaftar = $result_total_warga->fetch_assoc()['total'];
}

// Total Panitia
$total_panitia = 0;
$result_total_panitia = $conn->query("SELECT COUNT(*) AS total FROM warga WHERE status_panitia = 1");
if ($result_total_panitia) {
    $total_panitia = $result_total_panitia->fetch_assoc()['total'];
}

// Total Pekurban
$total_pekurban = 0;
$result_total_pekurban = $conn->query("SELECT COUNT(*) AS total FROM warga WHERE status_qurban = 'peserta'");
if ($result_total_pekurban) {
    $total_pekurban = $result_total_pekurban->fetch_assoc()['total'];
}

// Total Penerima Daging (bukan pekurban atau panitia yang menjadi peserta qurban)
$total_penerima_daging_biasa = 0;
$result_penerima_biasa = $conn->query("SELECT COUNT(*) AS total FROM warga 
                                       WHERE status_qurban = 'penerima' 
                                       AND status_panitia = 0");
if ($result_penerima_biasa) {
    $total_penerima_daging_biasa = $result_penerima_biasa->fetch_assoc()['total'];
}


// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../../includes/header.php'; // Sertakan header SB Admin 2
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manajemen Data Warga</h1>
    <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Warga Baru
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

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Warga Terdaftar</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_warga_terdaftar); ?> Orang</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Panitia</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_panitia); ?> Orang</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Pekurban</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_pekurban); ?> Orang</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-heart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Penerima Biasa</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($total_penerima_daging_biasa); ?> Orang</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-house-user fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Warga RT 001</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>No. HP</th>
                        <th>Status Qurban</th>
                        <th>Panitia</th>
                        <th>Role User Login</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>No. HP</th>
                        <th>Status Qurban</th>
                        <th>Panitia</th>
                        <th>Role User Login</th>
                        <th>Aksi</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    if (!empty($users_data)) {
                        foreach($users_data as $user) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($user['nik']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['nama']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['alamat'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($user['no_hp'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $user['status_qurban']))) . "</td>";
                            echo "<td>" . ($user['status_panitia'] ? 'Ya' : 'Tidak') . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst($user['user_role'] ?? 'Tidak Ada Akun')) . "</td>";
                            echo '<td>
                                    <a href="edit.php?nik=' . htmlspecialchars($user['nik']) . '" class="btn btn-warning btn-circle btn-sm" title="Edit Warga"><i class="fas fa-edit"></i></a>
                                    <a href="delete_warga.php?nik=' . htmlspecialchars($user['nik']) . '" class="btn btn-danger btn-circle btn-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus warga ini? Ini akan menghapus akun login dan riwayat qurbannya!\')" title="Hapus Warga"><i class="fas fa-trash"></i></a>
                                  </td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Tidak ada data warga.</td></tr>";
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