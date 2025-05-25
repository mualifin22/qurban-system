<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Cek akses: hanya admin dan panitia
check_access(['admin', 'panitia']);

$message = '';
$edit_data = [];

// Handle Edit (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM keuangan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $edit_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle Tambah/Update (POST request)
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
            redirect(str_replace('?action=edit&id=' . $_POST['id'], '', $_SERVER['PHP_SELF'])); // Redirect to clean URL
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
            redirect($_SERVER['PHP_SELF']); // Redirect to clean URL
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Hapus (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    // Hanya admin yang bisa menghapus
    if (has_role('admin')) {
        $id = sanitize_input($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM keuangan WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Transaksi berhasil dihapus.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Anda tidak memiliki izin untuk menghapus transaksi.";
    }
    redirect($_SERVER['PHP_SELF']); // Redirect untuk menghindari resubmission
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Rekapitulasi Keuangan</h2>
<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>

<h3><?php echo empty($edit_data) ? 'Tambah' : 'Edit'; ?> Transaksi</h3>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_data['id'] ?? ''); ?>">
    <label for="tanggal">Tanggal:</label><br>
    <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($edit_data['tanggal'] ?? date('Y-m-d')); ?>" required><br><br>

    <label for="jenis">Jenis Transaksi:</label><br>
    <select id="jenis" name="jenis" required>
        <option value="masuk" <?php echo (isset($edit_data['jenis']) && $edit_data['jenis'] == 'masuk') ? 'selected' : ''; ?>>Uang Masuk</option>
        <option value="keluar" <?php echo (isset($edit_data['jenis']) && $edit_data['jenis'] == 'keluar') ? 'selected' : ''; ?>>Uang Keluar</option>
    </select><br><br>

    <label for="kategori">Kategori:</label><br>
    <input type="text" id="kategori" name="kategori" value="<?php echo htmlspecialchars($edit_data['kategori'] ?? ''); ?>" required><br><br>

    <label for="keterangan">Keterangan:</label><br>
    <textarea id="keterangan" name="keterangan"><?php echo htmlspecialchars($edit_data['keterangan'] ?? ''); ?></textarea><br><br>

    <label for="jumlah">Jumlah (IDR):</label><br>
    <input type="number" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($edit_data['jumlah'] ?? ''); ?>" required><br><br>

    <input type="submit" value="<?php echo empty($edit_data) ? 'Tambah Transaksi' : 'Update Transaksi'; ?>">
    <?php if (!empty($edit_data)): ?>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Batal Edit</a>
    <?php endif; ?>
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
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['tanggal']) . "</td>";
                echo "<td>" . ($row['jenis'] == 'masuk' ? 'Uang Masuk' : 'Uang Keluar') . "</td>";
                echo "<td>" . htmlspecialchars($row['kategori']) . "</td>";
                echo "<td>" . htmlspecialchars($row['keterangan']) . "</td>";
                echo "<td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>";
                echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
                echo "<td>";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=edit&id=" . $row['id'] . "\">Edit</a>";
                if (has_role('admin')) { // Hanya admin yang bisa hapus
                    echo " | <a href=\"" . $_SERVER['PHP_SELF'] . "?action=delete&id=" . $row['id'] . "\" onclick=\"return confirm('Yakin ingin menghapus transaksi ini?');\">Hapus</a>";
                }
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
