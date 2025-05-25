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
    $stmt = $conn->prepare("SELECT * FROM warga WHERE id = ?");
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
    $nama = sanitize_input($_POST['nama']);
    $nik = sanitize_input($_POST['nik']);
    $alamat = sanitize_input($_POST['alamat']);
    $telepon = sanitize_input($_POST['telepon']);
    $is_panitia = isset($_POST['is_panitia']) ? 1 : 0;
    $is_berqurban = isset($_POST['is_berqurban']) ? 1 : 0;
    
    // Asumsi: user_id diatur secara manual oleh admin atau bisa dikembangkan login warga/berqurban
    $user_id = empty($_POST['user_id']) ? NULL : sanitize_input($_POST['user_id']);

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update warga
        $id = sanitize_input($_POST['id']);
        $stmt = $conn->prepare("UPDATE warga SET nama=?, nik=?, alamat=?, telepon=?, is_panitia=?, is_berqurban=?, user_id=? WHERE id=?");
        $stmt->bind_param("ssssiiii", $nama, $nik, $alamat, $telepon, $is_panitia, $is_berqurban, $user_id, $id);
        if ($stmt->execute()) {
            $message = "Data warga berhasil diperbarui.";
            redirect(str_replace('?action=edit&id=' . $_POST['id'], '', $_SERVER['PHP_SELF']));
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Tambah warga baru
        $stmt = $conn->prepare("INSERT INTO warga (nama, nik, alamat, telepon, is_panitia, is_berqurban, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiii", $nama, $nik, $alamat, $telepon, $is_panitia, $is_berqurban, $user_id);
        if ($stmt->execute()) {
            $message = "Data warga berhasil ditambahkan.";
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
    $stmt = $conn->prepare("DELETE FROM warga WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Data warga berhasil dihapus.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
    redirect($_SERVER['PHP_SELF']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Manajemen Data Warga</h2>
<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>

<h3><?php echo empty($edit_data) ? 'Tambah' : 'Edit'; ?> Data Warga</h3>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_data['id'] ?? ''); ?>">
    <label for="nama">Nama:</label><br>
    <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>" required><br><br>
    <label for="nik">NIK:</label><br>
    <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($edit_data['nik'] ?? ''); ?>" required><br><br>
    <label for="alamat">Alamat:</label><br>
    <textarea id="alamat" name="alamat"><?php echo htmlspecialchars($edit_data['alamat'] ?? ''); ?></textarea><br><br>
    <label for="telepon">Telepon:</label><br>
    <input type="text" id="telepon" name="telepon" value="<?php echo htmlspecialchars($edit_data['telepon'] ?? ''); ?>"><br><br>
    <input type="checkbox" id="is_panitia" name="is_panitia" <?php echo (isset($edit_data['is_panitia']) && $edit_data['is_panitia']) ? 'checked' : ''; ?>>
    <label for="is_panitia">Panitia?</label><br><br>
    <input type="checkbox" id="is_berqurban" name="is_berqurban" <?php echo (isset($edit_data['is_berqurban']) && $edit_data['is_berqurban']) ? 'checked' : ''; ?>>
    <label for="is_berqurban">Peserta Qurban?</label><br><br>

    <label for="user_id">Akun Pengguna Terkait (opsional):</label><br>
    <select id="user_id" name="user_id">
        <option value="">-- Pilih Pengguna --</option>
        <?php
        $users_res = $conn->query("SELECT id, username, role FROM users");
        while($user_row = $users_res->fetch_assoc()){
            $selected = (isset($edit_data['user_id']) && $edit_data['user_id'] == $user_row['id']) ? 'selected' : '';
            echo "<option value=\"" . $user_row['id'] . "\" " . $selected . ">" . htmlspecialchars($user_row['username']) . " (" . htmlspecialchars($user_row['role']) . ")</option>";
        }
        ?>
    </select><br><br>

    <input type="submit" value="<?php echo empty($edit_data) ? 'Tambah Warga' : 'Update Warga'; ?>">
    <?php if (!empty($edit_data)): ?>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Batal Edit</a>
    <?php endif; ?>
</form>

<h3>Daftar Warga</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>NIK</th>
            <th>Alamat</th>
            <th>Telepon</th>
            <th>Panitia</th>
            <th>Berqurban</th>
            <th>User Login</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT w.*, u.username FROM warga w LEFT JOIN users u ON w.user_id = u.id ORDER BY w.nama ASC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nik']) . "</td>";
                echo "<td>" . htmlspecialchars($row['alamat']) . "</td>";
                echo "<td>" . htmlspecialchars($row['telepon']) . "</td>";
                echo "<td>" . ($row['is_panitia'] ? 'Ya' : 'Tidak') . "</td>";
                echo "<td>" . ($row['is_berqurban'] ? 'Ya' : 'Tidak') . "</td>";
                echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
                echo "<td>";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=edit&id=" . $row['id'] . "\">Edit</a> | ";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=delete&id=" . $row['id'] . "\" onclick=\"return confirm('Yakin ingin menghapus warga ini?');\">Hapus</a>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>Tidak ada data warga.</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
