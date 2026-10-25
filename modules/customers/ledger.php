<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('customers');

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$cust = $stmt->get_result()->fetch_assoc();

if (!$cust) {
    die("Customer not found.");
}

$ledger = $conn->query("SELECT * FROM customer_ledger WHERE customer_id = $id ORDER BY date ASC, id ASC");

$page_title = 'Khata: ' . $cust['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-print-none d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Customer Khata: <?= clean_input($cust['name']) ?></h4>
        <small class="text-muted">Contact: <?= clean_input($cust['phone']) ?> | <?= clean_input($cust['address']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Customers
        </a>
        <a href="<?= BASE_URL ?>/modules/customers/payment.php?id=<?= $cust['id'] ?>" class="btn btn-success btn-sm">
            <i class="bi bi-wallet2 me-1"></i> Receive Payment
        </a>
        <button class="btn btn-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<div class="card card-custom p-4 bg-white printable-invoice">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <div>
            <h4 class="fw-bold text-primary mb-1"><?= __('app_name') ?></h4>
            <div class="small text-muted">Customer Account Ledger & Statement</div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Statement Date: <?= date('d M Y') ?></div>
            <div class="fw-bold fs-5 text-danger">Outstanding Balance: <?= format_currency($cust['current_balance']) ?></div>
        </div>
    </div>

    <!-- Customer Profile Info for Print/Statement -->
    <div class="bg-light p-3 rounded mb-3 border">
        <div class="row align-items-center">
            <div class="col-8">
                <small class="text-muted text-uppercase fw-semibold d-block">Account / Khata Holder (کھاتہ دار):</small>
                <div class="fw-bold fs-5 text-dark">
                    <?= clean_input($cust['name']) ?>
                    <?php if (!empty($cust['name_ur'])): ?>
                        <span class="text-secondary fs-6">(<?= clean_input($cust['name_ur']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-telephone me-1"></i><?= clean_input($cust['phone'] ?: 'N/A') ?> 
                    <?php if (!empty($cust['address'])): ?>
                        | <i class="bi bi-geo-alt me-1"></i><?= clean_input($cust['address']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-secondary text-uppercase mb-1"><?= clean_input($cust['customer_type']) ?></span>
                <div class="small text-muted">Credit Limit: <?= format_currency($cust['credit_limit']) ?></div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference #</th>
                    <th>Description</th>
                    <th class="text-end text-danger">Debit (+)</th>
                    <th class="text-end text-success">Credit (-)</th>
                    <th class="text-end">Balance (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ledger->num_rows > 0): ?>
                    <?php while ($row = $ledger->fetch_assoc()): ?>
                        <tr>
                            <td class="small text-muted"><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= clean_input($row['transaction_type']) ?></span></td>
                            <td class="font-monospace fw-semibold"><?= clean_input($row['reference_no']) ?></td>
                            <td><?= clean_input($row['description']) ?></td>
                            <td class="text-end text-danger fw-semibold">
                                <?= $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' ?>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                <?= $row['credit'] > 0 ? number_format($row['credit'], 2) : '-' ?>
                            </td>
                            <td class="text-end fw-bold"><?= number_format($row['running_balance'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No transactions in ledger.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
