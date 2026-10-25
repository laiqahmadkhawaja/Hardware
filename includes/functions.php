<?php
// Core Helper & Utility Functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/language.php';

// Safe sanitization
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8', false);
}

// Format currency
function format_currency($amount) {
    $formatted = number_format((float)$amount, 2);
    return CURRENCY . ' ' . $formatted;
}

// Format quantity with dynamic decimals
function format_qty($qty, $allow_decimal = false) {
    if ($allow_decimal) {
        return rtrim(rtrim(number_format((float)$qty, 4), '0'), '.');
    }
    return number_format((float)$qty, 0);
}

// Generate unique invoice / reference numbers
function generate_reference_no($conn, $table, $prefix, $column = 'invoice_no') {
    $prefix_with_date = $prefix . '-' . date('Ymd') . '-';
    $stmt = $conn->prepare("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1");
    $search = $prefix_with_date . '%';
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $next_seq = $row ? str_pad((int)substr($row[$column], -4) + 1, 4, '0', STR_PAD_LEFT) : '0001';
    return $prefix_with_date . $next_seq;
}

// Log audit trail
function log_audit($conn, $action, $ref = '', $details = '') {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, reference, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action, $ref, $details, $ip);
    $stmt->execute();
}

// JSON Response helper
function json_response($success, $message, $data = [], $errors = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors
    ]);
    exit;
}

// Flash messages
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Generate high-resolution vector Code39 barcode SVG
function generate_barcode_svg($text, $height = 36, $barWidth = 1.3) {
    $text = strtoupper(trim((string)$text));
    $chars = [
        '0'=>'000110100', '1'=>'100100001', '2'=>'001100001', '3'=>'101100000', '4'=>'000110001',
        '5'=>'100110000', '6'=>'001110000', '7'=>'000100101', '8'=>'100100100', '9'=>'001100100',
        'A'=>'100001001', 'B'=>'001001001', 'C'=>'101001000', 'D'=>'000011001', 'E'=>'100011000',
        'F'=>'001011000', 'G'=>'000001101', 'H'=>'100001100', 'I'=>'001001100', 'J'=>'000011100',
        'K'=>'100000011', 'L'=>'001000011', 'M'=>'101000010', 'N'=>'000010011', 'O'=>'100010010',
        'P'=>'001010010', 'Q'=>'000000111', 'R'=>'100000110', 'S'=>'001000110', 'T'=>'000010110',
        'U'=>'110000001', 'V'=>'011000001', 'W'=>'111000000', 'X'=>'010010001', 'Y'=>'110010000',
        'Z'=>'011010000', '-'=>'010000101', '.'=>110000100, ' '=>'011000100', '*'=>'010010100'
    ];
    $code = '*' . $text . '*';
    $rects = '';
    $curX = 4;
    for ($i = 0; $i < strlen($code); $i++) {
        $c = $code[$i];
        if (!isset($chars[$c])) continue;
        $pattern = (string)$chars[$c];
        for ($p = 0; $p < 9; $p++) {
            $isBar = ($p % 2 == 0);
            $w = ($pattern[$p] === '1') ? ($barWidth * 2.2) : $barWidth;
            if ($isBar) {
                $rects .= "<rect x='$curX' y='0' width='$w' height='$height' fill='#000'/>";
            }
            $curX += $w;
        }
        $curX += $barWidth;
    }
    $totalWidth = $curX + 4;
    return "<svg viewBox='0 0 $totalWidth $height' width='$totalWidth' height='$height' xmlns='http://www.w3.org/2000/svg' style='display:inline-block; max-width: 100%; height: auto;'>$rects</svg>";
}
