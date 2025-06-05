<?php
include '../../includes/header.php'; // Sesuaikan path
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
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT nik, nama, alamat, no_hp, status_qurban, status_panitia FROM warga ORDER BY nama ASC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['nik']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['alamat']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['no_hp']) . "</td>";
                    echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status_qurban']))) . "</td>";
                    echo "<td>" . ($row['status_panitia'] ? 'Ya' : 'Tidak') . "</td>";
                    echo '<td>
                            <a href="edit.php?nik=' . htmlspecialchars($row['nik']) . '" class="btn btn-edit">Edit</a>
                            <a href="delete_warga.php?nik=' . htmlspecialchars($row['nik']) . '" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus warga ini?\')">Hapus</a>
                          </td>';
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>Tidak ada data warga.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include '../../includes/footer.php'; // Sesuaikan path
?>