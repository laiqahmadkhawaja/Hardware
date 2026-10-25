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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $date = clean_input($_POST['date']);
    $account_id = (int)$_POST['account_id'];
    $payment_method = clean_input($_POST['payment_method']);
    $notes = clean_input($_POST['notes']);

    if ($amount <= 0) {
        $error = 'Valid payment amount is required.';
    } else {
        $conn->begin_transaction();
        try {
            $new_balance = (float)$sup['current_balance'] - $amount;
            $ref_no = generate_reference_no($conn, 'purchase_payments', 'PAY', 'reference_no');

            // 1. Supplier Ledger Debit
            $conn->query("INSERT INTO supplier_ledger (supplier_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                          VALUES ($id, '$date', 'payment', '$ref_no', 'Supplier Payment Voucher: $notes', $amount, 0, $new_balance)");

            // 2. Supplier balance update
            $conn->query("UPDATE suppliers SET current_balance = $new_balance WHERE id = $id");

            // 3. Purchase payment entry
            $conn->query("INSERT INTO purchase_payments (supplier_id, account_id, payment_method, amount, date, reference_no, notes) 
                          VALUES ($id, $account_id, '$payment_method', $amount, '$date', '$ref_no', '$notes')");

            // 4. Deduct Financial Account
            $conn->query("UPDATE financial_accounts SET balance = balance - $amount WHERE id = $account_id");

            $conn->commit();
            log_audit($conn, 'SUPPLIER_PAYMENT', $ref_no, "Paid PKR $amount to supplier #$id");
            set_flash('success', 'Supplier payment disbursed and Khata updated successfully!');
            header("Location: " . BASE_URL . "/modules/suppliers/ledger.php?id=$id");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$accounts = $conn->query("SELECT * FROM financial_accounts ORDER BY id ASC");

$page_title = 'Pay Supplier: ' . $sup['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Disburse Supplier Payment</h4>
        <small class="text-muted">Supplier: <?= clean_input($sup['name']) ?> | Outstanding Payable: <strong class="text-danger"><?= format_currency($sup['current_balance']) ?></strong></small>
    </div>
    <a href="<?= BASE_URL ?>/modules/suppliers/ledger.php?id=<?= $sup['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Ledger
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom" style="max-width: 600px;">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label small fw-bold">Payment Amount (PKR) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger" value="<?= $sup['current_balance'] > 0 ? $sup['current_balance'] : '' ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Date *</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Payment Method *</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">Cash (کاؤنٹر کیش)</option>
                        <option value="bank">Bank Transfer (آن لائن)</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="easypaisa">Easypaisa</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Disburse From Account *</label>
                <select name="account_id" class="form-select" required>
                    <?php while ($a = $accounts->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>"><?= clean_input($a['name']) ?> (Balance: PKR <?= number_format($a['balance'], 2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Cheque # / Transaction Ref / Remarks</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Paid against Saria delivery"></textarea>
            </div>

            <button type="submit" class="btn btn-danger px-4 fw-bold">
                <i class="bi bi-send-check me-1"></i> Disburse Payment
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
