<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('suppliers');

$page_title = __('suppliers') . ' (کھاتہ)';

$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('suppliers') ?> & Payable Ledgers</h4>
        <small class="text-muted">Steel mills, cement distributors, and hardware wholesale manufacturers</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/suppliers/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Add New Supplier
    </a>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Supplier / Distributor</th>
                    <th>Company</th>
                    <th>Phone #</th>
                    <th>Address</th>
                    <th>Payable Balance (PKR)</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($suppliers->num_rows > 0): ?>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= clean_input($s['name']) ?></div>
                                <?php if (!empty($s['name_ur'])): ?>
                                    <small class="text-muted"><?= clean_input($s['name_ur']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= clean_input($s['company']) ?></td>
                            <td><?= clean_input($s['phone']) ?></td>
                            <td class="small text-muted"><?= clean_input($s['address']) ?></td>
                            <td>
                                <span class="fw-bold fs-6 <?= $s['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($s['current_balance']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/suppliers/ledger.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-info text-white me-1" title="Khata Ledger">
                                    <i class="bi bi-journal-text me-1"></i> Khata
                                </a>
                                <a href="<?= BASE_URL ?>/modules/suppliers/payment.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" title="Pay Supplier">
                                    <i class="bi bi-cash me-1"></i> Pay
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted"><?= __('no_records') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
