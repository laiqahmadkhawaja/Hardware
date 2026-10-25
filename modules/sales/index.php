<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('sales');

$page_title = __('sales_list');

$sales = $conn->query("SELECT s.*, c.name as customer_name, c.name_ur as customer_name_ur, u.username 
                       FROM sales s 
                       JOIN customers c ON s.customer_id = c.id 
                       LEFT JOIN users u ON s.user_id = u.id 
                       ORDER BY s.id DESC LIMIT 100");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('sales_list') ?></h4>
        <small class="text-muted">History of all customer invoices, payments, and balances</small>
    </div>
    <a href="<?= BASE_URL ?>/pos.php" class="btn btn-primary btn-sm">
        <i class="bi bi-cart4 me-1"></i> Open POS Terminal
    </a>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date & Time</th>
                    <th>Customer Name</th>
                    <th>Grand Total</th>
                    <th>Paid</th>
                    <th>Due</th>
                    <th>Payment Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sales->num_rows > 0): ?>
                    <?php while ($s = $sales->fetch_assoc()): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary"><?= clean_input($s['invoice_no']) ?></td>
                            <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></td>
                            <td><?= is_rtl() && !empty($s['customer_name_ur']) ? clean_input($s['customer_name_ur']) : clean_input($s['customer_name']) ?></td>
                            <td class="fw-bold text-dark"><?= format_currency($s['grand_total']) ?></td>
                            <td class="text-success fw-semibold"><?= format_currency($s['paid_amount']) ?></td>
                            <td class="text-danger fw-semibold"><?= format_currency($s['due_amount']) ?></td>
                            <td>
                                <?php if ($s['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success">Fully Paid</span>
                                <?php elseif ($s['payment_status'] === 'partial'): ?>
                                    <span class="badge bg-warning text-dark">Partial</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Unpaid / Due</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= BASE_URL ?>/modules/sales/thermal.php?id=<?= $s['id'] ?>&print=true" target="_blank" class="btn btn-sm btn-outline-dark" title="Thermal Receipt (80mm/58mm)">
                                    <i class="bi bi-receipt-cutoff"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/modules/sales/view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-light border" title="A4 Invoice & Details">
                                    <i class="bi bi-eye"></i>
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
