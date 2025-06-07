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

// Hanya Admin yang bisa mengakses halaman ini
if (!isAdmin()) {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "error";
    header("Location: ../dashboard.php");
    exit();
}

// Ambil data user dari database
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

// =========================================================================
// Bagian Tampilan HTML (Dimulai setelah semua logika PHP selesai)
// =========================================================================
include '../../includes/header.php'; // Sertakan header setelah semua logika PHP selesai
?>

<div class="container">
    <h2>Manajemen User</h2>
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <p>
        <a href="add_user.php" class="btn btn-add">Tambah User Baru</a>
    </p>

    <table>
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
                    // Admin tidak bisa menghapus/mengedit akun admin utama (ID 1)
                    if ($user['id'] == 1 && $user['username'] == 'admin') {
                        echo '<span style="color: grey;">(Admin Utama)</span>';
                    } else {
                        echo '<a href="edit_user.php?id=' . htmlspecialchars($user['id']) . '" class="btn btn-edit">Edit</a> ';
                        echo '<a href="delete_user.php?id=' . htmlspecialchars($user['id']) . '" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus user ini?\')">Hapus</a>';
                    }
                    echo '</td>';
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='9'>Tidak ada data user.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php
include '../../includes/footer.php'; // Sertakan footer
?>