<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('suppliers');

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$sup = $stmt->get_result()->fetch_assoc();

if (!$sup) {
    die("Supplier not found.");
}

$ledger = $conn->query("SELECT * FROM supplier_ledger WHERE supplier_id = $id ORDER BY date ASC, id ASC");

$page_title = 'Supplier Khata: ' . $sup['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-print-none d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Supplier Khata: <?= clean_input($sup['name']) ?></h4>
        <small class="text-muted">Company: <?= clean_input($sup['company']) ?> | Phone: <?= clean_input($sup['phone']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Suppliers
        </a>
        <a href="<?= BASE_URL ?>/modules/suppliers/payment.php?id=<?= $sup['id'] ?>" class="btn btn-danger btn-sm">
            <i class="bi bi-wallet2 me-1"></i> Pay Supplier
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
            <div class="small text-muted">Supplier Accounts Payable Ledger</div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Statement Date: <?= date('d M Y') ?></div>
            <div class="fw-bold fs-5 text-danger">Total Payable: <?= format_currency($sup['current_balance']) ?></div>
        </div>
    </div>

    <!-- Supplier Profile Info for Print/Statement -->
    <div class="bg-light p-3 rounded mb-3 border">
        <div class="row align-items-center">
            <div class="col-8">
                <small class="text-muted text-uppercase fw-semibold d-block">Supplier / Distributor (ڈسٹری بیوٹر):</small>
                <div class="fw-bold fs-5 text-dark">
                    <?= clean_input($sup['name']) ?>
                    <?php if (!empty($sup['name_ur'])): ?>
                        <span class="text-secondary fs-6">(<?= clean_input($sup['name_ur']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted">
                    <?= clean_input($sup['company']) ?> | <i class="bi bi-telephone me-1"></i><?= clean_input($sup['phone'] ?: 'N/A') ?>
                    <?php if (!empty($sup['address'])): ?>
                        | <i class="bi bi-geo-alt me-1"></i><?= clean_input($sup['address']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-danger text-uppercase mb-1">Accounts Payable</span>
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
                    <th class="text-end text-success">Debit (-) Paid</th>
                    <th class="text-end text-danger">Credit (+) Bill</th>
                    <th class="text-end">Payable Balance (PKR)</th>
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
                            <td class="text-end text-success fw-semibold">
                                <?= $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' ?>
                            </td>
                            <td class="text-end text-danger fw-semibold">
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
