<?php
include '../../includes/header.php'; // Sesuaikan path
?>
<div class="container">
    <h2>Data Hewan Qurban</h2>
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <p>
        <a href="add_kambing.php" class="btn btn-add">Tambah Qurban Kambing</a>
        <a href="add_sapi.php" class="btn btn-add">Tambah Qurban Sapi</a>
    </p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Jenis Hewan</th>
                <th>Harga</th>
                <th>Biaya Admin</th>
                <th>Tgl. Beli</th>
                <th>Est. Daging (kg)</th>
                <th>Peserta</th> <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Ambil juga kolom nik_peserta_tunggal
            $sql = "SELECT h.id, h.jenis_hewan, h.harga, h.biaya_administrasi, h.tanggal_beli, h.estimasi_berat_daging_kg, h.nik_peserta_tunggal
                    FROM hewan_qurban h ORDER BY h.tanggal_beli DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
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
                        // Ambil nama warga untuk kambing
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
                            <a href="edit.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-edit">Edit</a>
                            <a href="delete_qurban.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus hewan qurban ini?\')">Hapus</a>
                          </td>';
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>Tidak ada data hewan qurban.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include '../../includes/footer.php'; // Sesuaikan path
?>