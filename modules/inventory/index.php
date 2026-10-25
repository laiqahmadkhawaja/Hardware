<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('inventory');

$page_title = __('inventory');

$stock_list = $conn->query("SELECT p.id, p.code, p.barcode, p.name, p.name_ur, p.cost_price, p.retail_price, p.alert_quantity,
                                    u.name as unit_name, u.name_ur as unit_name_ur,
                                    c.name as category_name,
                                    sb.quantity as current_stock, sb.average_cost,
                                    (sb.quantity * sb.average_cost) as total_valuation
                             FROM products p
                             JOIN stock_balances sb ON p.id = sb.product_id
                             JOIN units u ON p.base_unit_id = u.id
                             LEFT JOIN categories c ON p.category_id = c.id
                             ORDER BY p.name ASC");

// Valuation totals
$val_query = $conn->query("SELECT SUM(quantity * average_cost) as total_val, SUM(quantity) as total_qty FROM stock_balances");
$summary = $val_query->fetch_assoc();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('inventory') ?> & <?= __('warehouse') ?></h4>
        <small class="text-muted">Real-time stock quantities, weighted average cost & valuation</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php" class="btn btn-warning btn-sm">
            <i class="bi bi-sliders me-1"></i> Stock Adjustment
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/ledger.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i> Stock Movements Ledger
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <div class="stat-title">Total Product Line Items</div>
                <div class="stat-value"><?= $stock_list->num_rows ?> Items</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('stock_value') ?></div>
                <div class="stat-value text-success"><?= format_currency($summary['total_val']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Table -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Product Description</th>
                    <th>Category</th>
                    <th>Current Balance</th>
                    <th>Avg Cost</th>
                    <th>Retail Price</th>
                    <th>Valuation (PKR)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $stock_list->fetch_assoc()): ?>
                    <?php 
                    $is_low = $row['current_stock'] <= $row['alert_quantity'];
                    $is_out = $row['current_stock'] <= 0;
                    ?>
                    <tr>
                        <td class="font-monospace text-primary fw-semibold"><?= clean_input($row['code']) ?></td>
                        <td>
                            <div class="fw-bold"><?= clean_input($row['name']) ?></div>
                            <?php if (!empty($row['name_ur'])): ?>
                                <small class="text-muted"><?= clean_input($row['name_ur']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= clean_input($row['category_name'] ?? 'General') ?></td>
                        <td class="fw-bold fs-6">
                            <?= format_qty($row['current_stock']) ?> 
                            <span class="text-muted fw-normal small"><?= is_rtl() && !empty($row['unit_name_ur']) ? clean_input($row['unit_name_ur']) : clean_input($row['unit_name']) ?></span>
                        </td>
                        <td class="text-muted"><?= format_currency($row['average_cost']) ?></td>
                        <td class="text-secondary"><?= format_currency($row['retail_price']) ?></td>
                        <td class="fw-bold text-dark"><?= format_currency($row['total_valuation']) ?></td>
                        <td>
                            <?php if ($is_out): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($is_low): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
