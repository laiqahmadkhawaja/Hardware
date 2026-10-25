<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('expenses');

$page_title = __('expenses');

$expenses = $conn->query("SELECT e.*, ec.name as category_name, ec.name_ur as category_name_ur, fa.name as account_name, u.username 
                          FROM expenses e 
                          JOIN expense_categories ec ON e.category_id = ec.id 
                          JOIN financial_accounts fa ON e.account_id = fa.id 
                          LEFT JOIN users u ON e.user_id = u.id 
                          ORDER BY e.date DESC, e.id DESC LIMIT 100");

// Total expenses
$tot_exp = $conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM expenses")->fetch_assoc()['total'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('expenses') ?> Management</h4>
        <small class="text-muted">Shop rent, electricity, loading/unloading, carriage, staff wages & refreshments</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/expenses/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> <?= __('new_expense') ?>
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('total_expenses') ?></div>
                <div class="stat-value text-danger"><?= format_currency($tot_exp) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Expense #</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Account Disbursed</th>
                    <th>Payee / Description</th>
                    <th class="text-end">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expenses->num_rows > 0): ?>
                    <?php while ($e = $expenses->fetch_assoc()): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary"><?= clean_input($e['expense_no']) ?></td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($e['date'])) ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= is_rtl() && !empty($e['category_name_ur']) ? clean_input($e['category_name_ur']) : clean_input($e['category_name']) ?>
                                </span>
                            </td>
                            <td><?= clean_input($e['account_name']) ?></td>
                            <td>
                                <div><?= clean_input($e['payee'] ?: 'General') ?></div>
                                <small class="text-muted"><?= clean_input($e['description']) ?></small>
                            </td>
                            <td class="text-end fw-bold text-danger"><?= format_currency($e['amount']) ?></td>
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
