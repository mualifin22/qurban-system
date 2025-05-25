<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Cek akses: admin dan panitia
check_access(['admin', 'panitia']);

$message = '';
$edit_data = [];

// Handle Edit (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM pembagian_daging WHERE id = ?");
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
    $warga_id = sanitize_input($_POST['warga_id']);
    $kategori = sanitize_input($_POST['kategori']);
    $jumlah_kg = sanitize_input($_POST['jumlah_kg']);
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update pembagian
        $id = sanitize_input($_POST['id']);
        $stmt = $conn->prepare("UPDATE pembagian_daging SET warga_id=?, kategori=?, jumlah_kg=? WHERE id=?");
        $stmt->bind_param("isdi", $warga_id, $kategori, $jumlah_kg, $id);
        if ($stmt->execute()) {
            $message = "Pembagian daging berhasil diperbarui.";
            redirect(str_replace('?action=edit&id=' . $_POST['id'], '', $_SERVER['PHP_SELF']));
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Tambah pembagian baru
        $stmt = $conn->prepare("INSERT INTO pembagian_daging (warga_id, kategori, jumlah_kg) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $warga_id, $kategori, $jumlah_kg);
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $qr_filename = generate_qr_code("QURBAN_RT001_ID:" . $new_id, 'qurban_' . $new_id . '.png');
            // Update qr_code di database
            $stmt_update_qr = $conn->prepare("UPDATE pembagian_daging SET qr_code = ? WHERE id = ?");
            $stmt_update_qr->bind_param("si", $qr_filename, $new_id);
            $stmt_update_qr->execute();
            $stmt_update_qr->close();

            $message = "Pembagian daging berhasil ditambahkan dan QR Code dibuat.";
            redirect($_SERVER['PHP_SELF']);
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Hapus (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (has_role('admin')) { // Hanya admin yang bisa hapus
        $id = sanitize_input($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM pembagian_daging WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Pembagian daging berhasil dihapus.";
            // Hapus file QR juga jika ada
            // unlink(__DIR__ . '/../assets/qrcodes/qurban_' . $id . '.png'); // uncomment this line
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Anda tidak memiliki izin untuk menghapus data pembagian.";
    }
    redirect($_SERVER['PHP_SELF']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Manajemen Pembagian Daging Qurban</h2>
<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>

<h3><?php echo empty($edit_data) ? 'Tambah' : 'Edit'; ?> Pembagian Daging</h3>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_data['id'] ?? ''); ?>">
    <label for="warga_id">Penerima Daging:</label><br>
    <select id="warga_id" name="warga_id" required>
        <option value="">-- Pilih Warga --</option>
        <?php
        $warga_res = $conn->query("SELECT id, nama, is_panitia, is_berqurban FROM warga ORDER BY nama ASC");
        while($warga_row = $warga_res->fetch_assoc()){
            $selected = (isset($edit_data['warga_id']) && $edit_data['warga_id'] == $warga_row['id']) ? 'selected' : '';
            $kategori_warga = '';
            if($warga_row['is_panitia']) $kategori_warga .= ' (Panitia)';
            if($warga_row['is_berqurban']) $kategori_warga .= ' (Berqurban)';
            echo "<option value=\"" . $warga_row['id'] . "\" " . $selected . ">" . htmlspecialchars($warga_row['nama']) . $kategori_warga . "</option>";
        }
        ?>
    </select><br><br>

    <label for="kategori">Kategori Penerima:</label><br>
    <select id="kategori" name="kategori" required>
        <option value="warga" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'warga') ? 'selected' : ''; ?>>Warga Umum</option>
        <option value="panitia" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'panitia') ? 'selected' : ''; ?>>Panitia</option>
        <option value="berqurban" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'berqurban') ? 'selected' : ''; ?>>Peserta Qurban</option>
    </select><br><br>

    <label for="jumlah_kg">Jumlah Daging (kg):</label><br>
    <input type="number" step="0.01" id="jumlah_kg" name="jumlah_kg" value="<?php echo htmlspecialchars($edit_data['jumlah_kg'] ?? ''); ?>" required><br><br>

    <input type="submit" value="<?php echo empty($edit_data) ? 'Tambah Pembagian' : 'Update Pembagian'; ?>">
    <?php if (!empty($edit_data)): ?>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Batal Edit</a>
    <?php endif; ?>
</form>

<h3>Daftar Pembagian Daging</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Warga Penerima</th>
            <th>Kategori</th>
            <th>Jumlah (kg)</th>
            <th>Status Pengambilan</th>
            <th>Tanggal Pengambilan</th>
            <th>QR Code</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT pd.*, w.nama FROM pembagian_daging pd JOIN warga w ON pd.warga_id = w.id ORDER BY pd.created_at DESC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($row['kategori'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['jumlah_kg']) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($row['status_pengambilan'])) . "</td>";
                echo "<td>" . ($row['tanggal_pengambilan'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_pengambilan']))) : '-') . "</td>";
                echo "<td>";
                if (!empty($row['qr_code']) && file_exists(__DIR__ . '/../assets/qrcodes/' . $row['qr_code'])) {
                    echo "<img src=\"/qurban_app/assets/qrcodes/" . htmlspecialchars($row['qr_code']) . "\" width=\"50\" height=\"50\" alt=\"QR\">";
                } else {
                    echo "Belum ada QR";
                }
                echo "</td>";
                echo "<td>";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=edit&id=" . $row['id'] . "\">Edit</a>";
                if (has_role('admin')) {
                    echo " | <a href=\"" . $_SERVER['PHP_SELF'] . "?action=delete&id=" . $row['id'] . "\" onclick=\"return confirm('Yakin ingin menghapus pembagian ini?');\">Hapus</a>";
                }
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8'>Tidak ada data pembagian daging.</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
