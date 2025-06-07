<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/header.php'; // Sertakan header yang berisi koneksi DB dan fungsi helper
?>
<div class="container">
    <h2>Data Warga RT 001</h2>
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <p>
        <a href="add.php" class="btn btn-add">Tambah Warga Baru</a>
    </p>
    <table>
        <thead>
            <tr>
                <th>NIK</th>
                <th>Nama</th>
                <th>Alamat</th>
                <th>No. HP</th>
                <th>Status Qurban</th>
                <th>Panitia</th>
                <th>Role User Login</th> <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Gabungkan dengan tabel users untuk menampilkan role
            $sql = "SELECT w.nik, w.nama, w.alamat, w.no_hp, w.status_qurban, w.status_panitia, u.role as user_role
                    FROM warga w
                    LEFT JOIN users u ON w.nik = u.nik_warga
                    ORDER BY w.nama ASC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['nik']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['alamat'] ?? 'N/A') . "</td>"; // Handle NULL
                    echo "<td>" . htmlspecialchars($row['no_hp'] ?? 'N/A') . "</td>"; // Handle NULL
                    echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status_qurban']))) . "</td>";
                    echo "<td>" . ($row['status_panitia'] ? 'Ya' : 'Tidak') . "</td>";
                    echo "<td>" . htmlspecialchars(ucfirst($row['user_role'] ?? 'Tidak Ada Akun')) . "</td>"; // Tampilkan role user
                    echo '<td>
                            <a href="edit.php?nik=' . htmlspecialchars($row['nik']) . '" class="btn btn-edit">Edit</a>
                            <a href="delete_warga.php?nik=' . htmlspecialchars($row['nik']) . '" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus warga ini? Ini akan menghapus akun login dan riwayat qurbannya!\')">Hapus</a>
                          </td>';
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>Tidak ada data warga.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include '../../includes/footer.php'; // Sertakan footer
?>