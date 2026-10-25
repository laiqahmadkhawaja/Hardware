<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('inventory');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)$_POST['product_id'];
    $type = clean_input($_POST['type']); // addition or subtraction
    $qty = (float)$_POST['quantity'];
    $reason = clean_input($_POST['reason']);

    if ($product_id <= 0 || $qty <= 0 || empty($reason)) {
        $error = 'Product, quantity and reason are required.';
    } else {
        $conn->begin_transaction();
        try {
            // Get current stock & cost
            $sb_stmt = $conn->prepare("SELECT quantity, average_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = 1 FOR UPDATE");
            $sb_stmt->bind_param("i", $product_id);
            $sb_stmt->execute();
            $sb = $sb_stmt->get_result()->fetch_assoc();

            $curr_qty = $sb ? (float)$sb['quantity'] : 0.00;
            $avg_cost = $sb ? (float)$sb['average_cost'] : 0.00;

            if ($type === 'subtraction' && $curr_qty < $qty) {
                throw new Exception("Cannot deduct $qty. Current stock is only $curr_qty.");
            }

            $new_qty = ($type === 'addition') ? ($curr_qty + $qty) : ($curr_qty - $qty);
            $qty_in = ($type === 'addition') ? $qty : 0.00;
            $qty_out = ($type === 'subtraction') ? $qty : 0.00;

            // Update balance
            $up_stmt = $conn->prepare("UPDATE stock_balances SET quantity = ? WHERE product_id = ? AND warehouse_id = 1");
            $up_stmt->bind_param("di", $new_qty, $product_id);
            $up_stmt->execute();

            // Record movement
            $ref_no = generate_reference_no($conn, 'stock_movements', 'ADJ', 'reference_no');
            $uid = $_SESSION['user_id'];
            $sm_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, warehouse_id, transaction_type, reference_no, qty_in, qty_out, balance_after, unit_cost, notes, user_id) 
                                       VALUES (?, 1, 'adjustment', ?, ?, ?, ?, ?, ?, ?)");
            $sm_stmt->bind_param("isddddsi", $product_id, $ref_no, $qty_in, $qty_out, $new_qty, $avg_cost, $reason, $uid);
            $sm_stmt->execute();

            $conn->commit();
            log_audit($conn, 'STOCK_ADJUSTMENT', $ref_no, "Adjusted stock for product #$product_id: $type $qty");
            set_flash('success', 'Stock adjusted successfully.');
            header("Location: " . BASE_URL . "/modules/inventory/index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$products = $conn->query("SELECT p.id, p.name, p.code, sb.quantity as current_stock, u.name as unit_name 
                          FROM products p 
                          JOIN stock_balances sb ON p.id = sb.product_id 
                          JOIN units u ON p.base_unit_id = u.id 
                          ORDER BY p.name ASC");

$page_title = 'Stock Adjustment';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Record Stock Adjustment</h4>
        <small class="text-muted">Manually reconcile physical counts, damaged stock or inventory variances</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Inventory
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom" style="max-width: 650px;">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label small fw-bold">Select Product *</label>
                <select name="product_id" class="form-select" required>
                    <option value="">-- Choose Hardware Product --</option>
                    <?php while ($p = $products->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= clean_input($p['name']) ?> [<?= clean_input($p['code']) ?>] - Current Stock: <?= format_qty($p['current_stock']) ?> <?= clean_input($p['unit_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Adjustment Type *</label>
                    <select name="type" class="form-select" required>
                        <option value="addition">Addition (+) Increase Stock</option>
                        <option value="subtraction">Subtraction (-) Reduce Stock</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Quantity *</label>
                    <input type="number" step="0.01" name="quantity" class="form-control" required placeholder="0.00">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Reason / Notes *</label>
                <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Physical inventory count correction, rusted material, damaged cement bags" required></textarea>
            </div>

            <button type="submit" class="btn btn-warning px-4 fw-semibold">
                <i class="bi bi-check-circle me-1"></i> Post Adjustment
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
