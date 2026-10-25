<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Authentication guard and session manager
if (session_status() === PHP_SESSION_NONE) {
    session_name('HARDWARE_ERP_SESS');
    session_start();
}

function check_auth() {
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }

    // Refresh user session data from DB if not fully populated or expired
    if ((!isset($_SESSION['permissions']) || !isset($_SESSION['full_name'])) && isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $q = $conn->query("SELECT u.*, r.name as role_slug, r.display_name as role_name, r.permissions 
                           FROM users u 
                           JOIN roles r ON u.role_id = r.id 
                           WHERE u.id = $uid AND u.status = 'active' LIMIT 1");
        if ($u = $q->fetch_assoc()) {
            $_SESSION['username'] = $u['username'];
            $_SESSION['full_name'] = $u['full_name'];
            $_SESSION['role_id'] = $u['role_id'];
            $_SESSION['role_slug'] = $u['role_slug'];
            $_SESSION['role_name'] = $u['role_name'];
            $_SESSION['permissions'] = $u['permissions'];
            $_SESSION['branch_id'] = $u['branch_id'];
            $_SESSION['lang'] = $u['language'] ?? 'en';
        } else {
            session_destroy();
            header("Location: " . BASE_URL . "/login.php");
            exit;
        }
    }
}

function has_permission($permission) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    if ($_SESSION['permissions'] === 'all' || (is_array($_SESSION['permissions']) && in_array('all', $_SESSION['permissions']))) {
        return true;
    }
    $perms = is_array($_SESSION['permissions']) 
        ? $_SESSION['permissions'] 
        : explode(',', (string)$_SESSION['permissions']);
    return in_array(trim($permission), array_map('trim', $perms));
}

function require_permission($permission) {
    check_auth();
    if (!has_permission($permission)) {
        header("HTTP/1.1 403 Forbidden");
        die("<h3>403 Access Denied: You do not have permission to view this module.</h3>");
    }
}
