<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<nav>
    <ul>
        <?php if (is_logged_in()): ?>
            <li><a href="/qurban_app/dashboard.php">Dashboard</a></li>
            <?php if (has_role('admin')): ?>
                <li><a href="/qurban_app/admin/users.php">Manajemen Pengguna</a></li>
                <li><a href="/qurban_app/admin/warga.php">Manajemen Warga</a></li>
                <li><a href="/qurban_app/admin/hewan.php">Manajemen Hewan Qurban</a></li>
                <li><a href="/qurban_app/admin/keuangan.php">Rekap Keuangan</a></li>
                <li><a href="/qurban_app/admin/pembagian.php">Pembagian Daging</a></li>
            <?php elseif (has_role('panitia')): ?>
                <li><a href="/qurban_app/panitia/keuangan.php">Pencatatan Keuangan</a></li>
                <li><a href="/qurban_app/panitia/pembagian.php">Pencatatan Pembagian Daging</a></li>
                <li><a href="/qurban_app/panitia/qr_scan.php">Verifikasi Pengambilan Daging</a></li>
            <?php elseif (has_role('berqurban')): ?>
                <li><a href="/qurban_app/berqurban/my_qurban.php">Qurban Saya</a></li>
            <?php elseif (has_role('warga')): ?>
                <li><a href="/qurban_app/warga/my_card.php">Kartu Pengambilan Daging</a></li>
            <?php endif; ?>
            <li><a href="/qurban_app/logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
        <?php else: ?>
            <li><a href="/qurban_app/login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
<hr>
