<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('customers');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name']);
    $name_ur = clean_input($_POST['name_ur']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $customer_type = clean_input($_POST['customer_type']);
    $credit_limit = (float)($_POST['credit_limit'] ?? 0);
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name)) {
        $error = 'Customer Name is required.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO customers (name, name_ur, phone, address, customer_type, credit_limit, opening_balance, current_balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssddd", $name, $name_ur, $phone, $address, $customer_type, $credit_limit, $opening_balance, $opening_balance);
            $stmt->execute();
            $cust_id = $conn->insert_id;

            if ($opening_balance > 0) {
                $ref = 'OPN-CUST-' . $cust_id;
                $today = date('Y-m-d');
                $conn->query("INSERT INTO customer_ledger (customer_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                              VALUES ($cust_id, '$today', 'opening', '$ref', 'B/F Opening Khata Balance', $opening_balance, 0, $opening_balance)");
            }

            $conn->commit();
            log_audit($conn, 'CREATE_CUSTOMER', 'CUST-' . $cust_id, "Created customer $name");
            set_flash('success', 'Customer profile registered successfully.');
            header("Location: " . BASE_URL . "/modules/customers/index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$page_title = 'Add New Customer';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Register Customer / Contractor</h4>
        <small class="text-muted">Create Khata account for retail, wholesale or construction contractors</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Customers
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom" style="max-width: 650px;">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Customer Name (English) *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Haji Tariq Mehmood" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Customer Name (Urdu)</label>
                    <input type="text" name="name_ur" class="form-control text-end" placeholder="مثال: حاجی طارق محمود">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Customer Type</label>
                    <select name="customer_type" class="form-select">
                        <option value="retail">Retail (عام گاہک)</option>
                        <option value="contractor">Contractor (ٹھیکیدار)</option>
                        <option value="wholesale">Wholesale (تھوک خریدار)</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Address / Site Location</label>
                    <input type="text" name="address" class="form-control" placeholder="Plot / Site location">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Credit Limit (PKR)</label>
                    <input type="number" step="0.01" name="credit_limit" class="form-control" value="0.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Opening Balance (PKR)</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-check-lg me-1"></i> Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
