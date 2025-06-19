<?php
$servername = "localhost";
$username = "root"; 
$password = "root"; 
$dbname = "db_qurban_rt001";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
?>