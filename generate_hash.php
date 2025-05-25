<?php

// Pastikan skrip ini diakses melalui POST request atau dengan parameter GET yang jelas untuk keamanan
// Hindari mengekspos skrip ini secara langsung di lingkungan produksi tanpa pengamanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password_input = $_POST['password'];
    $hashed_password = password_hash($password_input, PASSWORD_DEFAULT); // Menggunakan algoritma default (Bcrypt)

    echo "<h2>Hasil Hash Password</h2>";
    echo "<p>Password Asli: <strong>" . htmlspecialchars($password_input) . "</strong></p>";
    echo "<p>Password Hash: <strong style='word-break: break-all;'>" . htmlspecialchars($hashed_password) . "</strong></p>";
    echo "<p>Anda bisa menggunakan hash ini untuk memperbarui password di database Anda.</p>";
    echo "<p><small>Contoh SQL: UPDATE users SET password = '" . htmlspecialchars($hashed_password) . "' WHERE username = 'nama_user';</small></p>";
} else {
    // Tampilkan formulir untuk memasukkan password
    echo "<h2>Generate Hash Password</h2>";
    echo "<p>Masukkan password yang ingin Anda hash:</p>";
    echo "<form action=\"generate_hash.php\" method=\"post\">";
    echo "    <label for=\"password\">Password:</label><br>";
    echo "    <input type=\"text\" id=\"password\" name=\"password\" required><br><br>";
    echo "    <input type=\"submit\" value=\"Generate Hash\">";
    echo "</form>";
}

?>
