<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hardware_db');
define('DB_PORT', 3306);

// Establish connection using MySQLi
$conn = mysqli_init();
if (!$conn) {
    die("Database init failed");
}

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

if (!@$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
    die("Database Connection Error: " . mysqli_connect_error());
}

$conn->set_charset("utf8mb4");
