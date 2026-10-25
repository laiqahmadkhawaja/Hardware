<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('suppliers');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name']);
    $name_ur = clean_input($_POST['name_ur']);
    $company = clean_input($_POST['company']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name)) {
        $error = 'Supplier Name is required.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, name_ur, company, phone, address, opening_balance, current_balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdd", $name, $name_ur, $company, $phone, $address, $opening_balance, $opening_balance);
            $stmt->execute();
            $sup_id = $conn->insert_id;

            if ($opening_balance > 0) {
                $ref = 'OPN-SUPP-' . $sup_id;
                $today = date('Y-m-d');
                $conn->query("INSERT INTO supplier_ledger (supplier_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                              VALUES ($sup_id, '$today', 'opening', '$ref', 'B/F Opening Payable Balance', 0, $opening_balance, $opening_balance)");
            }

            $conn->commit();
            log_audit($conn, 'CREATE_SUPPLIER', 'SUPP-' . $sup_id, "Created supplier $name");
            set_flash('success', 'Supplier profile added successfully.');
            header("Location: " . BASE_URL . "/modules/suppliers/index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$page_title = 'Add New Supplier';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Register Supplier / Distributor</h4>
        <small class="text-muted">Set up steel mills, cement distributors or wholesale vendor accounts</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Suppliers
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
                    <label class="form-label small fw-bold">Supplier Contact Name (English) *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Mughal Steels Mills Ltd" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Supplier Name (Urdu)</label>
                    <input type="text" name="name_ur" class="form-control text-end" placeholder="مثال: مغل اسٹیل ملز لمیٹڈ">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Company / Mill / Business Name</label>
                    <input type="text" name="company" class="form-control" placeholder="e.g. Mughal Group">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="042-111-MUGHAL">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Address / Factory Location</label>
                    <input type="text" name="address" class="form-control" placeholder="Mill or distributor office address">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Opening Payable Balance (PKR)</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-check-lg me-1"></i> Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
