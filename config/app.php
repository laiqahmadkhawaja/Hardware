<?php
// Application settings and environment
if (session_status() === PHP_SESSION_NONE) {
    // Isolate session per project path on localhost to prevent cookie collisions
    session_name('HARDWARE_ERP_SESS');
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Karachi');

// Base URL calculation
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$raw_url = "$proto://$host" . ($script_name === '/' ? '' : $script_name);
// Strip subfolders if executing from inside modules or ajax
$base_url = preg_replace('#/(modules|ajax)(/.*)?$#i', '', $raw_url);
define('BASE_URL', rtrim($base_url, '/'));

define('APP_NAME', 'Pak Hardware ERP');
define('APP_NAME_UR', 'پاک ہارڈویئر ای آر پی');
define('APP_VERSION', '1.0.0');
define('CURRENCY', 'PKR');
define('CURRENCY_UR', 'روپے');
