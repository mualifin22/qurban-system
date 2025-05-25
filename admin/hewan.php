<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Cek akses: hanya admin
check_access(['admin']);

$message = '';
$edit_data = [];

// Handle Edit (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM hewan_qurban WHERE id = ?");
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
    $jenis = sanitize_input($_POST['jenis']);
    $harga = sanitize_input($_POST['harga']);
    $biaya_admin = sanitize_input($_POST['biaya_admin']);
    $total_daging_kg = sanitize_input($_POST['total_daging_kg']);

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update hewan
        $id = sanitize_input($_POST['id']);
        $stmt = $conn->prepare("UPDATE hewan_qurban SET jenis=?, harga=?, biaya_admin=?, total_daging_kg=? WHERE id=?");
        $stmt->bind_param("siiii", $jenis, $harga, $biaya_admin, $total_daging_kg, $id);
        if ($stmt->execute()) {
            $message = "Data hewan qurban berhasil diperbarui.";
            redirect(str_replace('?action=edit&id=' . $_POST['id'], '', $_SERVER['PHP_SELF']));
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Tambah hewan baru
        $stmt = $conn->prepare("INSERT INTO hewan_qurban (jenis, harga, biaya_admin, total_daging_kg) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siii", $jenis, $harga, $biaya_admin, $total_daging_kg);
        if ($stmt->execute()) {
            $message = "Data hewan qurban berhasil ditambahkan.";
            redirect($_SERVER['PHP_SELF']);
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Hapus (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM hewan_qurban WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Data hewan qurban berhasil dihapus.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
    redirect($_SERVER['PHP_SELF']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Manajemen Hewan Qurban</h2>
<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>

<h3><?php echo empty($edit_data) ? 'Tambah' : 'Edit'; ?> Data Hewan Qurban</h3>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_data['id'] ?? ''); ?>">
    <label for="jenis">Jenis Hewan:</label><br>
    <select id="jenis" name="jenis" required>
        <option value="kambing" <?php echo (isset($edit_data['jenis']) && $edit_data['jenis'] == 'kambing') ? 'selected' : ''; ?>>Kambing</option>
        <option value="sapi" <?php echo (isset($edit_data['jenis']) && $edit_data['jenis'] == 'sapi') ? 'selected' : ''; ?>>Sapi</option>
    </select><br><br>
    <label for="harga">Harga (IDR):</label><br>
    <input type="number" id="harga" name="harga" value="<?php echo htmlspecialchars($edit_data['harga'] ?? ''); ?>" required><br><br>
    <label for="biaya_admin">Biaya Administrasi (IDR):</label><br>
    <input type="number" id="biaya_admin" name="biaya_admin" value="<?php echo htmlspecialchars($edit_data['biaya_admin'] ?? ''); ?>" required><br><br>
    <label for="total_daging_kg">Estimasi Total Daging (kg):</label><br>
    <input type="number" step="0.01" id="total_daging_kg" name="total_daging_kg" value="<?php echo htmlspecialchars($edit_data['total_daging_kg'] ?? ''); ?>" required><br><br>
    <input type="submit" value="<?php echo empty($edit_data) ? 'Tambah Hewan' : 'Update Hewan'; ?>">
    <?php if (!empty($edit_data)): ?>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Batal Edit</a>
    <?php endif; ?>
</form>

<h3>Daftar Hewan Qurban</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Jenis</th>
            <th>Harga</th>
            <th>Biaya Admin</th>
            <th>Total Daging (kg)</th>
            <th>Created At</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT * FROM hewan_qurban ORDER BY created_at DESC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($row['jenis'])) . "</td>";
                echo "<td>Rp " . number_format($row['harga'], 0, ',', '.') . "</td>";
                echo "<td>Rp " . number_format($row['biaya_admin'], 0, ',', '.') . "</td>";
                echo "<td>" . htmlspecialchars($row['total_daging_kg']) . "</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "<td>";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=edit&id=" . $row['id'] . "\">Edit</a> | ";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=delete&id=" . $row['id'] . "\" onclick=\"return confirm('Yakin ingin menghapus hewan ini?');\">Hapus</a>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>Tidak ada data hewan qurban.</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
