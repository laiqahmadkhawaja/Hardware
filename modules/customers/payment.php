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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $date = clean_input($_POST['date']);
    $account_id = (int)$_POST['account_id'];
    $payment_method = clean_input($_POST['payment_method']);
    $notes = clean_input($_POST['notes']);

    if ($amount <= 0) {
        $error = 'Valid receipt amount is required.';
    } else {
        $conn->begin_transaction();
        try {
            $new_balance = (float)$cust['current_balance'] - $amount;
            $ref_no = generate_reference_no($conn, 'sale_payments', 'REC', 'reference_no');

            // 1. Customer Ledger Credit
            $conn->query("INSERT INTO customer_ledger (customer_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                          VALUES ($id, '$date', 'payment', '$ref_no', 'Cash Collection Receipt: $notes', 0, $amount, $new_balance)");

            // 2. Customer Table balance update
            $conn->query("UPDATE customers SET current_balance = $new_balance WHERE id = $id");

            // 3. Payment record
            $conn->query("INSERT INTO sale_payments (customer_id, account_id, payment_method, amount, date, reference_no, notes) 
                          VALUES ($id, $account_id, '$payment_method', $amount, '$date', '$ref_no', '$notes')");

            // 4. Increase Financial Account
            $conn->query("UPDATE financial_accounts SET balance = balance + $amount WHERE id = $account_id");

            $conn->commit();
            log_audit($conn, 'CUSTOMER_PAYMENT', $ref_no, "Received PKR $amount from customer #$id");
            set_flash('success', 'Payment received and Khata updated successfully!');
            header("Location: " . BASE_URL . "/modules/customers/ledger.php?id=$id");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$accounts = $conn->query("SELECT * FROM financial_accounts ORDER BY id ASC");

$page_title = 'Receive Payment: ' . $cust['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Receive Payment / Cash Receipt</h4>
        <small class="text-muted">Customer: <?= clean_input($cust['name']) ?> | Outstanding Due: <strong class="text-danger"><?= format_currency($cust['current_balance']) ?></strong></small>
    </div>
    <a href="<?= BASE_URL ?>/modules/customers/ledger.php?id=<?= $cust['id'] ?>" class="btn btn-outline-secondary btn-sm">
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
                <label class="form-label small fw-bold">Receipt Amount (PKR) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success" value="<?= $cust['current_balance'] > 0 ? $cust['current_balance'] : '' ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Date *</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Payment Method *</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">Cash (نقد)</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="easypaisa">Easypaisa</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Deposit Into Account *</label>
                <select name="account_id" class="form-select" required>
                    <?php while ($a = $accounts->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>"><?= clean_input($a['name']) ?> (<?= clean_input($a['account_type']) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Remarks / Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Cleared invoice for Saria delivery"></textarea>
            </div>

            <button type="submit" class="btn btn-success px-4 fw-bold">
                <i class="bi bi-check2-circle me-1"></i> Post Payment Receipt
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
