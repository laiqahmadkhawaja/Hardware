<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('customers');

$page_title = __('customers') . ' (کھاتہ)';

$search = clean_input($_GET['search'] ?? '');
$where = "1=1";
if ($search) {
    $where .= " AND (name LIKE '%$search%' OR name_ur LIKE '%$search%' OR phone LIKE '%$search%')";
}

$customers = $conn->query("SELECT * FROM customers WHERE $where ORDER BY name ASC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('customers') ?> & Khata Ledgers</h4>
        <small class="text-muted">Track customer profiles, credit limits, balances, and transaction statements</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/customers/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Add New Customer
    </a>
</div>

<!-- Search -->
<div class="card card-custom mb-3">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search customer by name or phone..." value="<?= clean_input($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> <?= __('search') ?></button>
            <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-light btn-sm border"><i class="bi bi-arrow-clockwise"></i></a>
        </form>
    </div>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone #</th>
                    <th>Customer Type</th>
                    <th>Credit Limit</th>
                    <th>Current Khata Balance</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customers->num_rows > 0): ?>
                    <?php while ($c = $customers->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= clean_input($c['name']) ?></div>
                                <?php if (!empty($c['name_ur'])): ?>
                                    <small class="text-muted"><?= clean_input($c['name_ur']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= clean_input($c['phone']) ?></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= clean_input($c['customer_type']) ?></span></td>
                            <td class="text-muted"><?= format_currency($c['credit_limit']) ?></td>
                            <td>
                                <span class="fw-bold fs-6 <?= $c['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($c['current_balance']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/customers/ledger.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-info text-white me-1" title="Khata Statement">
                                    <i class="bi bi-journal-text me-1"></i> Khata
                                </a>
                                <a href="<?= BASE_URL ?>/modules/customers/payment.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-success" title="Receive Payment">
                                    <i class="bi bi-cash me-1"></i> Receive
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
