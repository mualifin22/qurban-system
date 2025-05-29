<?php
// includes/header.php
// Pastikan session sudah dimulai sebelum memanggil header di halaman lain
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/db.php'; // Menggunakan __DIR__ untuk path yang absolut
include_once __DIR__ . '/functions.php';

// Cek apakah user sudah login, jika belum redirect ke halaman login
if (!isLoggedIn()) {
    redirectToLogin();
}

$currentRole = $_SESSION['role'];
$currentUsername = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Qurban RT 001</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .header nav ul li {
            display: inline;
            margin-left: 20px;
        }
        .header nav ul li a {
            color: white;
            text-decoration: none;
        }
        .header nav ul li a:hover {
            text-decoration: underline;
        }
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }
        .btn-add { background-color: #007bff; }
        .btn-edit { background-color: #ffc107; }
        .btn-delete { background-color: #dc3545; }
        .btn-primary { background-color: #007bff; }
        .btn:hover {
            opacity: 0.9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input[type="checkbox"] {
            margin-top: 8px;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistem Qurban RT 001</h1>
        <nav>
            <ul>
                <li><a href="/sistem_qurban/public/dashboard.php">Dashboard</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="/sistem_qurban/public/admin/users.php">Manajemen User</a></li>
                <?php endif; ?>
                <?php if (isAdmin() || isPanitia()): ?>
                    <li><a href="/sistem_qurban/public/warga/index.php">Data Warga</a></li>
                    <li><a href="/sistem_qurban/public/qurban/index.php">Data Qurban</a></li>
                    <li><a href="/sistem_qurban/public/keuangan/index.php">Keuangan</a></li>
                    <li><a href="/sistem_qurban/public/qurban/pembagian.php">Pembagian Daging</a></li>
                <?php endif; ?>
                <?php if (isBerqurban()): ?>
                    <li><a href="/sistem_qurban/public/qurban/my_qurban.php">Qurban Saya</a></li>
                <?php endif; ?>
                <?php if (isWarga() || isBerqurban() || isPanitia()): ?>
                    <li><a href="/sistem_qurban/public/warga/qrcode.php">Kartu Qurban</a></li>
                <?php endif; ?>
                <li><a href="/sistem_qurban/public/auth.php?logout=true">Logout (<?php echo htmlspecialchars($currentUsername); ?>)</a></li>
            </ul>
        </nav>
    </div>
