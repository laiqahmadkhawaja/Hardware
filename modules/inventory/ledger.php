<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('inventory');

$page_title = __('stock_ledger');

$movements = $conn->query("SELECT sm.*, p.name as product_name, p.name_ur as product_name_ur, p.code as product_code,
                                  u.username
                           FROM stock_movements sm
                           JOIN products p ON sm.product_id = p.id
                           LEFT JOIN users u ON sm.user_id = u.id
                           ORDER BY sm.id DESC LIMIT 100");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('stock_ledger') ?></h4>
        <small class="text-muted">Traceable record of all incoming, outgoing, and adjusted stock movements</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Inventory
    </a>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Reference</th>
                    <th>Product Item</th>
                    <th>Transaction Type</th>
                    <th>Qty In (+)</th>
                    <th>Qty Out (-)</th>
                    <th>Balance Result</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($movements->num_rows > 0): ?>
                    <?php while ($m = $movements->fetch_assoc()): ?>
                        <tr>
                            <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></td>
                            <td class="font-monospace fw-semibold"><?= clean_input($m['reference_no']) ?></td>
                            <td>
                                <div><?= clean_input($m['product_name']) ?></div>
                                <small class="text-muted font-monospace"><?= clean_input($m['product_code']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= strtoupper(clean_input($m['transaction_type'])) ?></span>
                            </td>
                            <td class="text-success fw-bold">
                                <?= $m['qty_in'] > 0 ? '+' . format_qty($m['qty_in']) : '-' ?>
                            </td>
                            <td class="text-danger fw-bold">
                                <?= $m['qty_out'] > 0 ? '-' . format_qty($m['qty_out']) : '-' ?>
                            </td>
                            <td class="fw-bold fs-6"><?= format_qty($m['balance_after']) ?></td>
                            <td class="small text-muted"><?= clean_input($m['notes']) ?></td>
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
