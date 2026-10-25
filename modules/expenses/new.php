<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('expenses');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)$_POST['category_id'];
    $account_id = (int)$_POST['account_id'];
    $amount = (float)$_POST['amount'];
    $date = clean_input($_POST['date']);
    $payee = clean_input($_POST['payee']);
    $description = clean_input($_POST['description']);

    if ($category_id <= 0 || $account_id <= 0 || $amount <= 0) {
        $error = 'Valid category, payment account and expense amount are required.';
    } else {
        $conn->begin_transaction();
        try {
            $expense_no = generate_reference_no($conn, 'expenses', 'EXP', 'expense_no');
            $user_id = $_SESSION['user_id'];

            // 1. Insert expense
            $stmt = $conn->prepare("INSERT INTO expenses (expense_no, category_id, account_id, amount, date, payee, description, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siidsssi", $expense_no, $category_id, $account_id, $amount, $date, $payee, $description, $user_id);
            $stmt->execute();

            // 2. Deduct from financial account
            $conn->query("UPDATE financial_accounts SET balance = balance - $amount WHERE id = $account_id");

            $conn->commit();
            log_audit($conn, 'RECORD_EXPENSE', $expense_no, "Recorded expense of PKR $amount ($payee)");
            set_flash('success', 'Expense recorded successfully.');
            header("Location: " . BASE_URL . "/modules/expenses/index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$categories = $conn->query("SELECT * FROM expense_categories ORDER BY name ASC");
$accounts = $conn->query("SELECT * FROM financial_accounts ORDER BY id ASC");

$page_title = 'Record New Expense';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Record Operating Expense</h4>
        <small class="text-muted">Enter freight, wages, utility bills, or shop repairs</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Expenses
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom" style="max-width: 600px;">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label small fw-bold">Expense Amount (PKR) *</label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger" placeholder="0.00" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Date *</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Expense Category *</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Choose Category --</option>
                        <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= is_rtl() && !empty($c['name_ur']) ? clean_input($c['name_ur']) : clean_input($c['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Disbursed From Account *</label>
                <select name="account_id" class="form-select" required>
                    <?php while ($a = $accounts->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>"><?= clean_input($a['name']) ?> (Balance: PKR <?= number_format($a['balance'], 2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Payee / Vendor</label>
                <input type="text" name="payee" class="form-control" placeholder="e.g. Electric Supply Co. or Mazdoor Jamal">
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Description / Purpose</label>
                <textarea name="description" class="form-control" rows="2" placeholder="e.g. Loading and unloading carriage for cement bags"></textarea>
            </div>

            <button type="submit" class="btn btn-danger px-4 fw-bold">
                <i class="bi bi-check-circle me-1"></i> Save Expense
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
