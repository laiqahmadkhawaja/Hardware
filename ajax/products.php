<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$action = clean_input($_GET['action'] ?? ($_POST['action'] ?? ''));

if ($action === 'search' || $action === 'get_all') {
    $term = clean_input($_GET['term'] ?? '');
    $category_id = (int)($_GET['category_id'] ?? 0);

    $where = ["p.status = 'active'"];
    $params = [];
    $types = "";

    if ($category_id > 0) {
        $where[] = "p.category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }

    if (!empty($term)) {
        $like = "%$term%";
        $where[] = "(
            p.code = ? 
            OR p.barcode = ? 
            OR p.code LIKE ? 
            OR p.barcode LIKE ?
            OR p.name LIKE ? 
            OR p.name_ur LIKE ? 
            OR p.description LIKE ?
            OR c.name LIKE ?
            OR c.name_ur LIKE ?
            OR b.name LIKE ?
            OR b.name_ur LIKE ?
        )";
        $params = array_merge($params, [$term, $term, $like, $like, $like, $like, $like, $like, $like, $like, $like]);
        $types .= "sssssssssss";
    }

    $where_sql = implode(" AND ", $where);
    $order_sql = !empty($term) 
        ? "ORDER BY (p.code = ? OR p.barcode = ?) DESC, p.name ASC" 
        : "ORDER BY p.name ASC";

    $sql = "SELECT p.id, p.code, p.barcode, p.name, p.name_ur, p.cost_price, 
                   p.retail_price, p.wholesale_price, p.contractor_price,
                   p.category_id,
                   u.id as unit_id, u.name as unit_name, u.name_ur as unit_name_ur, u.allow_decimal,
                   c.name as category_name, c.name_ur as category_name_ur,
                   b.name as brand_name, b.name_ur as brand_name_ur,
                   IFNULL(sb.quantity, 0) as stock_qty
            FROM products p
            JOIN units u ON p.base_unit_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.warehouse_id = 1
            WHERE $where_sql
            $order_sql
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(false, 'Database query error: ' . $conn->error);
    }

    if (!empty($term)) {
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    
    $products = [];
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }

    json_response(true, 'Found products', $products);
}

if ($action === 'quick_add_unit') {
    $name = clean_input($_POST['name'] ?? '');
    $name_ur = clean_input($_POST['name_ur'] ?? '');
    $code = clean_input($_POST['code'] ?? '');
    $allow_decimal = (int)($_POST['allow_decimal'] ?? 0);

    if (empty($name)) {
        json_response(false, 'Unit name is required.');
    }
    if (empty($name_ur)) {
        $name_ur = $name;
    }
    if (empty($code)) {
        $code = strtolower(substr($name, 0, 4));
    }

    $stmt = $conn->prepare("INSERT INTO units (name, name_ur, code, allow_decimal) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $name_ur, $code, $allow_decimal);
    if ($stmt->execute()) {
        json_response(true, 'Unit added successfully', [
            'id' => $conn->insert_id,
            'name' => $name,
            'name_ur' => $name_ur,
            'code' => $code
        ]);
    } else {
        json_response(false, 'Error adding unit: ' . $conn->error);
    }
}

if ($action === 'quick_add_category') {
    $name = clean_input($_POST['name'] ?? '');
    $name_ur = clean_input($_POST['name_ur'] ?? '');

    if (empty($name)) {
        json_response(false, 'Category name is required.');
    }
    if (empty($name_ur)) {
        $name_ur = $name;
    }

    $stmt = $conn->prepare("INSERT INTO categories (name, name_ur) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $name_ur);
    if ($stmt->execute()) {
        json_response(true, 'Category added successfully', [
            'id' => $conn->insert_id,
            'name' => $name,
            'name_ur' => $name_ur
        ]);
    } else {
        json_response(false, 'Error adding category: ' . $conn->error);
    }
}

if ($action === 'quick_add_brand') {
    $name = clean_input($_POST['name'] ?? '');
    $name_ur = clean_input($_POST['name_ur'] ?? '');

    if (empty($name)) {
        json_response(false, 'Brand name is required.');
    }
    if (empty($name_ur)) {
        $name_ur = $name;
    }

    $stmt = $conn->prepare("INSERT INTO brands (name, name_ur) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $name_ur);
    if ($stmt->execute()) {
        json_response(true, 'Brand added successfully', [
            'id' => $conn->insert_id,
            'name' => $name,
            'name_ur' => $name_ur
        ]);
    } else {
        json_response(false, 'Error adding brand: ' . $conn->error);
    }
}

json_response(false, 'Invalid action');
