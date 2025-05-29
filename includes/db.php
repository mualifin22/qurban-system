<?php
// includes/db.php

$servername = "localhost";
$username = "root"; // Ganti dengan username database kamu
$password = "root";     // Ganti dengan password database kamu
$dbname = "db_qurban_rt001"; // Nama database yang sudah kita buat

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Mengecek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
// echo "Koneksi database berhasil!"; // Bisa dihapus setelah dipastikan berhasil
?>
