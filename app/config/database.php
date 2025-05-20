<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'my_database');

// Etablishing a connection to the Database
function getConnection() {
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  return $conn;
}

// Function for preparing and executing SQL statements
function executeQuery($sql, $types = "", $params = []) {
  $conn = getConnection();

  $stmt = $conn->prepare($sql);

  if (!empty($types) && !empty($params)) {
    $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();

  $result = $stmt->get_result();

  $stmt->close();
  $conn->close();

  return $result;
}
?>
