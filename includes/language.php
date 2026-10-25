<?php
// Translation and language helper functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle GET lang switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ur'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$current_lang = $_SESSION['lang'] ?? 'en';

// Load active language dictionary (lazy load Urdu only when requested)
$en_dict = require __DIR__ . '/../languages/en.php';
$active_dict = ($current_lang === 'ur') ? require __DIR__ . '/../languages/ur.php' : $en_dict;

if (!function_exists('__')) {
    function __($key, $default = '') {
        global $active_dict, $en_dict;
        if (isset($active_dict[$key])) {
            return $active_dict[$key];
        }
        if (isset($en_dict[$key])) {
            return $en_dict[$key];
        }
        return $default ?: $key;
    }
}

if (!function_exists('get_current_lang')) {
    function get_current_lang() {
        global $current_lang;
        return $current_lang;
    }
}

if (!function_exists('is_rtl')) {
    function is_rtl() {
        return get_current_lang() === 'ur';
    }
}
