<?php
// Aktifkan pelaporan error untuk debugging. Hapus ini di lingkungan produksi.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/header.php'; // Sertakan header yang berisi koneksi DB dan fungsi helper

// Ambil data keuangan
$sql = "SELECT id, jenis, keterangan, jumlah, tanggal FROM keuangan ORDER BY tanggal DESC, id DESC";
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
?>

<div class="container">
    <h2>Rekapan Keuangan Qurban RT 001</h2>
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="message ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <p>
        <a href="add.php" class="btn btn-add">Tambah Transaksi Manual</a>
    </p>

    <div style="background-color: #f0f8ff; border: 1px solid #d0e8ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h3>Ringkasan Keuangan</h3>
        <p><strong>Total Pemasukan:</strong> <?php echo formatRupiah($total_pemasukan); ?></p>
        <p><strong>Total Pengeluaran:</strong> <?php echo formatRupiah($total_pengeluaran); ?></p>
        <p><strong>Saldo Akhir:</strong> <span style="font-weight: bold; color: <?php echo ($saldo_akhir >= 0) ? 'green' : 'red'; ?>;"><?php echo formatRupiah($saldo_akhir); ?></span></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Jenis</th>
                <th>Keterangan</th>
                <th>Jumlah</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($data_keuangan)) {
                foreach($data_keuangan as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars(ucfirst($row['jenis'])) . "</td>";
                    echo "<td>" . htmlspecialchars($row['keterangan']) . "</td>";
                    echo "<td>" . formatRupiah($row['jumlah']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['tanggal']) . "</td>";
                    echo '<td>
                            <a href="edit.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-edit">Edit</a>
                            <a href="delete.php?id=' . htmlspecialchars($row['id']) . '" class="btn btn-delete" onclick="return confirm(\'Apakah Anda yakin ingin menghapus transaksi ini?\')">Hapus</a>
                          </td>';
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>Tidak ada data transaksi keuangan.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php
include '../../includes/footer.php'; // Sertakan footer
?>