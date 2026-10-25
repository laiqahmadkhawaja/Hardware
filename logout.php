<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    log_audit($conn, 'LOGOUT', 'USER-' . $_SESSION['user_id'], 'User logged out');
}

session_unset();
session_destroy();

header("Location: " . BASE_URL . "/login.php");
exit;
