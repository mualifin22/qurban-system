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
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
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
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Password akan dihash
    $role = sanitize_input($_POST['role']);

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update user
        $id = sanitize_input($_POST['id']);
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $username, $hashed_password, $role, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $role, $id);
        }
        
        if ($stmt->execute()) {
            $message = "Pengguna berhasil diperbarui.";
            redirect(str_replace('?action=edit&id=' . $_POST['id'], '', $_SERVER['PHP_SELF']));
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Tambah user baru
        if (empty($password)) {
            $message = "Password harus diisi untuk pengguna baru.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed_password, $role);
            if ($stmt->execute()) {
                $message = "Pengguna berhasil ditambahkan.";
                redirect($_SERVER['PHP_SELF']);
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle Hapus (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    // Pastikan tidak menghapus akun admin yang sedang login atau satu-satunya admin
    if ($id == $_SESSION['user_id']) {
        $message = "Anda tidak bisa menghapus akun Anda sendiri.";
    } else {
        // Cek jika ini adalah satu-satunya admin
        $admin_count_res = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $admin_count = $admin_count_res->fetch_row()[0];

        $target_role_res = $conn->query("SELECT role FROM users WHERE id = $id");
        $target_role = $target_role_res->fetch_assoc()['role'];

        if ($target_role == 'admin' && $admin_count <= 1) {
            $message = "Tidak bisa menghapus admin terakhir. Harus ada setidaknya satu admin.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Pengguna berhasil dihapus.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    redirect($_SERVER['PHP_SELF']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<h2>Manajemen Pengguna</h2>
<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>

<h3><?php echo empty($edit_data) ? 'Tambah' : 'Edit'; ?> Pengguna</h3>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_data['id'] ?? ''); ?>">
    <label for="username">Username:</label><br>
    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($edit_data['username'] ?? ''); ?>" required><br><br>
    <label for="password">Password: <?php echo empty($edit_data) ? '' : '(Kosongkan jika tidak ingin mengubah)'; ?></label><br>
    <input type="password" id="password" name="password" <?php echo empty($edit_data) ? 'required' : ''; ?>><br><br>
    <label for="role">Role:</label><br>
    <select id="role" name="role" required>
        <option value="admin" <?php echo (isset($edit_data['role']) && $edit_data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
        <option value="panitia" <?php echo (isset($edit_data['role']) && $edit_data['role'] == 'panitia') ? 'selected' : ''; ?>>Panitia</option>
        <option value="berqurban" <?php echo (isset($edit_data['role']) && $edit_data['role'] == 'berqurban') ? 'selected' : ''; ?>>Berqurban</option>
        <option value="warga" <?php echo (isset($edit_data['role']) && $edit_data['role'] == 'warga') ? 'selected' : ''; ?>>Warga</option>
    </select><br><br>
    <input type="submit" value="<?php echo empty($edit_data) ? 'Tambah Pengguna' : 'Update Pengguna'; ?>">
    <?php if (!empty($edit_data)): ?>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Batal Edit</a>
    <?php endif; ?>
</form>

<h3>Daftar Pengguna</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created At</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT id, username, role, created_at FROM users ORDER BY created_at DESC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($row['role'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "<td>";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=edit&id=" . $row['id'] . "\">Edit</a> | ";
                echo "<a href=\"" . $_SERVER['PHP_SELF'] . "?action=delete&id=" . $row['id'] . "\" onclick=\"return confirm('Yakin ingin menghapus pengguna ini?');\">Hapus</a>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>Tidak ada pengguna.</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
