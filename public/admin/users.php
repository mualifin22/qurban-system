<?php
include '../../includes/db.php';
include '../../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

$sql = "SELECT u.id, u.username, u.role, u.created_at, w.nama as warga_nama, w.nik as warga_nik, w.status_qurban, w.status_panitia
        FROM users u
        LEFT JOIN warga w ON u.nik_warga = w.nik
        ORDER BY u.role DESC, u.username ASC";
$result = $conn->query($sql);

$users_data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $users_data[] = $row;
    }
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manajemen User</h1>
    <a href="add_user.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah User Baru
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

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar User Sistem</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>NIK Warga</th>
                        <th>Nama Warga</th>
                        <th>Status Qurban</th>
                        <th>Panitia</th>
                        <th>Dibuat Pada</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>NIK Warga</th>
                        <th>Nama Warga</th>
                        <th>Status Qurban</th>
                        <th>Panitia</th>
                        <th>Dibuat Pada</th>
                        <th>Aksi</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    if (!empty($users_data)) {
                        foreach($users_data as $user) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst($user['role'])) . "</td>";
                            echo "<td>" . htmlspecialchars($user['warga_nik'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($user['warga_nama'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $user['status_qurban'] ?? 'N/A'))) . "</td>";
                            echo "<td>" . (($user['status_panitia'] ?? false) ? 'Ya' : 'Tidak') . "</td>";
                            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                            echo '<td>';
                            if ($user['id'] == 1 && $user['username'] == 'admin') {
                                echo '<span class="text-info font-weight-bold">(Admin Utama)</span>';
                            } else {
                                echo '<a href="edit_user.php?id=' . htmlspecialchars($user['id']) . '" class="btn btn-warning btn-circle btn-sm" title="Edit User"><i class="fas fa-edit"></i></a> ';
                                echo '<a href="delete_user.php?id=' . htmlspecialchars($user['id']) . '" class="btn btn-danger btn-circle btn-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus user ini?\')" title="Hapus User"><i class="fas fa-trash"></i></a>';
                            }
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center'>Tidak ada data user.</td></tr>";
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