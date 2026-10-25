<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('purchases');

$page_title = __('purchases_list');

$purchases = $conn->query("SELECT p.*, s.name as supplier_name, s.name_ur as supplier_name_ur, u.username 
                           FROM purchases p 
                           JOIN suppliers s ON p.supplier_id = s.id 
                           LEFT JOIN users u ON p.user_id = u.id 
                           ORDER BY p.id DESC LIMIT 100");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('purchases_list') ?></h4>
        <small class="text-muted">Procurement bills from steel mills, cement distributors & wholesale suppliers</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/purchases/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> <?= __('new_purchase') ?>
    </a>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Purchase #</th>
                    <th>Supplier Bill #</th>
                    <th>Date</th>
                    <th>Supplier Name</th>
                    <th>Total Bill</th>
                    <th>Paid</th>
                    <th>Payable Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($purchases->num_rows > 0): ?>
                    <?php while ($p = $purchases->fetch_assoc()): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary"><?= clean_input($p['purchase_no']) ?></td>
                            <td class="font-monospace text-muted"><?= clean_input($p['supplier_invoice_no'] ?: '-') ?></td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($p['date'])) ?></td>
                            <td><?= is_rtl() && !empty($p['supplier_name_ur']) ? clean_input($p['supplier_name_ur']) : clean_input($p['supplier_name']) ?></td>
                            <td class="fw-bold text-dark"><?= format_currency($p['grand_total']) ?></td>
                            <td class="text-success fw-semibold"><?= format_currency($p['paid_amount']) ?></td>
                            <td class="text-danger fw-semibold"><?= format_currency($p['due_amount']) ?></td>
                            <td>
                                <?php if ($p['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                <?php elseif ($p['payment_status'] === 'partial'): ?>
                                    <span class="badge bg-warning text-dark">Partial</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Payable</span>
                                <?php endif; ?>
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
