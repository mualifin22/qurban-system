<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Hanya admin yang bisa mengakses halaman ini
if (!is_logged_in() || !has_role('admin')) {
    redirect('/qurban_app/login.php');
}

$message = '';

// Handle tambah/edit transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = sanitize_input($_POST['tanggal']);
    $jenis = sanitize_input($_POST['jenis']);
    $kategori = sanitize_input($_POST['kategori']);
    $keterangan = sanitize_input($_POST['keterangan']);
    $jumlah = sanitize_input($_POST['jumlah']);
    $created_by = $_SESSION['user_id'];

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update transaksi
        $id = sanitize_input($_POST['id']);
        $stmt = $conn->prepare("UPDATE keuangan SET tanggal=?, jenis=?, kategori=?, keterangan=?, jumlah=? WHERE id=?");
        $stmt->bind_param("ssssii", $tanggal, $jenis, $kategori, $keterangan, $jumlah, $id);
        if ($stmt->execute()) {
            $message = "Transaksi berhasil diperbarui.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Tambah transaksi baru
        $stmt = $conn->prepare("INSERT INTO keuangan (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $tanggal, $jenis, $kategori, $keterangan, $jumlah, $created_by);
        if ($stmt->execute()) {
            $message = "Transaksi berhasil ditambahkan.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle hapus transaksi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM keuangan WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Transaksi berhasil dihapus.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
    redirect('/qurban_app/admin/keuangan.php'); // Redirect untuk menghindari resubmission
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Rekapitulasi Keuangan</h2>
<?php if ($message): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<h3>Tambah/Edit Transaksi</h3>
<form action="keuangan.php" method="post">
    <input type="hidden" name="id" value="<?php echo isset($_GET['id']) ? sanitize_input($_GET['id']) : ''; ?>">
    <label for="tanggal">Tanggal:</label><br>
    <input type="date" id="tanggal" name="tanggal" value="<?php echo isset($_GET['id']) ? $conn->query("SELECT tanggal FROM keuangan WHERE id = " . sanitize_input($_GET['id']))->fetch_assoc()['tanggal'] : date('Y-m-d'); ?>" required><br><br>

    <label for="jenis">Jenis Transaksi:</label><br>
    <select id="jenis" name="jenis" required>
        <option value="masuk" <?php echo (isset($_GET['id']) && $conn->query("SELECT jenis FROM keuangan WHERE id = " . sanitize_input($_GET['id']))->fetch_assoc()['jenis'] == 'masuk') ? 'selected' : ''; ?>>Uang Masuk</option>
        <option value="keluar" <?php echo (isset($_GET['id']) && $conn->query("SELECT jenis FROM keuangan WHERE id = " . sanitize_input($_GET['id']))->fetch_assoc()['jenis'] == 'keluar') ? 'selected' : ''; ?>>Uang Keluar</option>
    </select><br><br>

    <label for="kategori">Kategori:</label><br>
    <input type="text" id="kategori" name="kategori" value="<?php echo isset($_GET['id']) ? $conn->query("SELECT kategori FROM keuangan WHERE id = " . sanitize_input($_GET['id']))->fetch_assoc()['kategori'] : ''; ?>" required><br><br>

    <label for="keterangan">Keterangan:</label><br>
    <textarea id="keterangan" name="keterangan"><?php echo isset($_GET['id']) ? $conn->query("SELECT keterangan FROM keuangan WHERE id = " . sanitize_input($_GET['id']))->fetch_assoc()['keterangan'] : ''; ?></textarea><br><br>

    <label for="jumlah">Jumlah (IDR):</label><br>
    <input type="number" id="jumlah" name="jumlah" value="<?php echo isset($_GET['id']) ? $conn->query("SELECT jumlah FROM keuangan WHERE id = " . sanitize_input($_GET['id']))->fetch_assoc()['jumlah'] : ''; ?>" required><br><br>

    <input type="submit" value="<?php echo isset($_GET['id']) ? 'Update Transaksi' : 'Tambah Transaksi'; ?>">
</form>

<h3>Daftar Transaksi</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Tanggal</th>
            <th>Jenis</th>
            <th>Kategori</th>
            <th>Keterangan</th>
            <th>Jumlah</th>
            <th>Dicatat Oleh</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT k.*, u.username FROM keuangan k LEFT JOIN users u ON k.created_by = u.id ORDER BY k.tanggal DESC, k.created_at DESC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['tanggal'] . "</td>";
                echo "<td>" . ($row['jenis'] == 'masuk' ? 'Uang Masuk' : 'Uang Keluar') . "</td>";
                echo "<td>" . $row['kategori'] . "</td>";
                echo "<td>" . $row['keterangan'] . "</td>";
                echo "<td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>";
                echo "<td>" . ($row['username'] ?? 'N/A') . "</td>";
                echo "<td>";
                echo "<a href=\"keuangan.php?id=" . $row['id'] . "\">Edit</a> | ";
                echo "<a href=\"keuangan.php?action=delete&id=" . $row['id'] . "\" onclick=\"return confirm('Yakin ingin menghapus transaksi ini?');\">Hapus</a>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8'>Tidak ada transaksi.</td></tr>";
        }
        ?>
    </tbody>
</table>

<h3>Ringkasan Keuangan</h3>
<?php
$total_masuk = $conn->query("SELECT SUM(jumlah) AS total FROM keuangan WHERE jenis = 'masuk'")->fetch_assoc()['total'] ?? 0;
$total_keluar = $conn->query("SELECT SUM(jumlah) AS total FROM keuangan WHERE jenis = 'keluar'")->fetch_assoc()['total'] ?? 0;
$saldo = $total_masuk - $total_keluar;
?>
<p>Total Uang Masuk: <strong>Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></strong></p>
<p>Total Uang Keluar: <strong>Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></strong></p>
<p>Saldo Saat Ini: <strong>Rp <?php echo number_format($saldo, 0, ',', '.'); ?></strong></p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
