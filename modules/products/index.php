<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('products');

$page_title = __('products');

$search = clean_input($_GET['search'] ?? '');
$category_id = clean_input($_GET['category_id'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(p.name LIKE ? OR p.name_ur LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($category_id !== '') {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

$where_sql = implode(' AND ', $where_clauses);
$sql = "SELECT p.*, c.name as category_name, c.name_ur as category_name_ur, 
               u.name as unit_name, u.name_ur as unit_name_ur,
               IFNULL(sb.quantity, 0) as stock_qty
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN units u ON p.base_unit_id = u.id
        LEFT JOIN stock_balances sb ON p.id = sb.product_id
        WHERE $where_sql
        ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('products') ?></h4>
        <small class="text-muted">Manage product catalogue, prices, and stock units</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/products/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Add New Product
    </a>
</div>

<!-- Filter card -->
<div class="card card-custom mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, code, barcode..." value="<?= clean_input($search) ?>">
            </div>
            <div class="col-12 col-md-4">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= is_rtl() && !empty($cat['name_ur']) ? clean_input($cat['name_ur']) : clean_input($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> <?= __('filter') ?></button>
                <a href="<?= BASE_URL ?>/modules/products/index.php" class="btn btn-light btn-sm border"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Products table -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Code / Barcode</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Cost Price</th>
                    <th>Retail Price</th>
                    <th>Wholesale</th>
                    <th>Current Stock</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products->num_rows > 0): ?>
                    <?php while ($prod = $products->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold text-primary font-monospace"><?= clean_input($prod['code']) ?></span>
                                <?php if (!empty($prod['barcode'])): ?>
                                    <div class="small text-muted font-monospace"><i class="bi bi-upc-scan"></i> <?= clean_input($prod['barcode']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= clean_input($prod['name']) ?></div>
                                <?php if (!empty($prod['name_ur'])): ?>
                                    <div class="text-muted small"><?= clean_input($prod['name_ur']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= clean_input($prod['category_name'] ?? 'General') ?></td>
                            <td class="text-muted"><?= format_currency($prod['cost_price']) ?></td>
                            <td class="fw-bold text-success"><?= format_currency($prod['retail_price']) ?></td>
                            <td class="text-secondary"><?= format_currency($prod['wholesale_price']) ?></td>
                            <td>
                                <?php 
                                $is_low = $prod['stock_qty'] <= $prod['alert_quantity'];
                                ?>
                                <span class="badge <?= $is_low ? 'bg-danger' : 'bg-success' ?> fs-6">
                                    <?= format_qty($prod['stock_qty']) ?> <?= clean_input($prod['unit_name']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/products/edit.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-light border" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted"><?= __('no_records') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
