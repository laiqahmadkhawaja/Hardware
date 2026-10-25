<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('settings');

// Handle Toggle Show on Invoice
if (isset($_GET['action']) && $_GET['action'] === 'toggle_invoice' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE financial_accounts SET show_on_invoice = IF(show_on_invoice = 1, 0, 1) WHERE id = $id");
    set_flash('success', 'Account invoice visibility toggled successfully.');
    header("Location: " . BASE_URL . "/modules/accounts/index.php");
    exit;
}

// Handle Add Account Form Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    $name = clean_input($_POST['name']);
    $name_ur = clean_input($_POST['name_ur'] ?? '');
    $account_type = clean_input($_POST['account_type']);
    $bank_name = clean_input($_POST['bank_name'] ?? '');
    $account_number = clean_input($_POST['account_number'] ?? '');
    $iban = clean_input($_POST['iban'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);
    $show_on_invoice = isset($_POST['show_on_invoice']) ? 1 : 0;

    if (empty($name)) {
        $error = 'Account title is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO financial_accounts (name, name_ur, account_type, bank_name, account_number, iban, balance, show_on_invoice) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdi", $name, $name_ur, $account_type, $bank_name, $account_number, $iban, $opening_balance, $show_on_invoice);
        if ($stmt->execute()) {
            set_flash('success', 'Bank / payment account created successfully.');
            header("Location: " . BASE_URL . "/modules/accounts/index.php");
            exit;
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}

// Handle Edit Account Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_account') {
    $id = (int)$_POST['account_id'];
    $name = clean_input($_POST['name']);
    $name_ur = clean_input($_POST['name_ur'] ?? '');
    $account_type = clean_input($_POST['account_type']);
    $bank_name = clean_input($_POST['bank_name'] ?? '');
    $account_number = clean_input($_POST['account_number'] ?? '');
    $iban = clean_input($_POST['iban'] ?? '');
    $show_on_invoice = isset($_POST['show_on_invoice']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE financial_accounts SET name = ?, name_ur = ?, account_type = ?, bank_name = ?, account_number = ?, iban = ?, show_on_invoice = ? WHERE id = ?");
    $stmt->bind_param("ssssssii", $name, $name_ur, $account_type, $bank_name, $account_number, $iban, $show_on_invoice, $id);
    if ($stmt->execute()) {
        set_flash('success', 'Account updated successfully.');
        header("Location: " . BASE_URL . "/modules/accounts/index.php");
        exit;
    } else {
        $error = 'Database error: ' . $conn->error;
    }
}

$accounts = $conn->query("SELECT * FROM financial_accounts ORDER BY id ASC");

$page_title = 'Bank & Payment Accounts';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Bank & Payment Accounts</h4>
        <small class="text-muted">Manage bank accounts, cash registers & toggle visibility on invoices</small>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddAccount">
        <i class="bi bi-plus-circle me-1"></i> Add New Bank Account
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Account Title</th>
                    <th>Type</th>
                    <th>Bank Name</th>
                    <th>Account / IBAN</th>
                    <th class="text-end">Current Balance</th>
                    <th class="text-center">Show on Invoice</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($acc = $accounts->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold">
                            <?= clean_input($acc['name']) ?>
                            <?php if (!empty($acc['name_ur'])): ?>
                                <small class="text-muted d-block"><?= clean_input($acc['name_ur']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $acc['account_type'] === 'bank' ? 'bg-primary' : ($acc['account_type'] === 'mobile_wallet' ? 'bg-warning text-dark' : 'bg-success') ?>">
                                <?= strtoupper(str_replace('_', ' ', $acc['account_type'])) ?>
                            </span>
                        </td>
                        <td><?= clean_input($acc['bank_name'] ?: '-') ?></td>
                        <td>
                            <div class="font-monospace text-primary fw-semibold"><?= clean_input($acc['account_number'] ?: '-') ?></div>
                            <?php if (!empty($acc['iban'])): ?>
                                <small class="text-muted font-monospace d-block"><?= clean_input($acc['iban']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold text-success"><?= format_currency($acc['balance']) ?></td>
                        <td class="text-center">
                            <a href="?action=toggle_invoice&id=<?= $acc['id'] ?>" class="btn btn-sm <?= $acc['show_on_invoice'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                <i class="bi <?= $acc['show_on_invoice'] ? 'bi-check-circle-fill' : 'bi-dash-circle' ?> me-1"></i>
                                <?= $acc['show_on_invoice'] ? 'Showing on Invoice' : 'Hidden' ?>
                            </a>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-light border" onclick='editAccount(<?= json_encode($acc) ?>)' title="Edit Details">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Account -->
<div class="modal fade" id="modalAddAccount" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_account">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-bank me-1 text-primary"></i> Add New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Account Title *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Meezan Bank Current" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Account Type *</label>
                            <select name="account_type" class="form-select" required>
                                <option value="bank">Bank Account</option>
                                <option value="mobile_wallet">Mobile Wallet (JazzCash/Easypaisa)</option>
                                <option value="cash">Cash Register</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g. Meezan Bank Ltd / HBL">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Account #</label>
                            <input type="text" name="account_number" class="form-control" placeholder="01010102938475">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">IBAN</label>
                            <input type="text" name="iban" class="form-control" placeholder="PK36MEZN...">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Opening Balance (PKR)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-center pt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_on_invoice" value="1" id="chkShowInvoice" checked>
                                <label class="form-check-label small fw-bold" for="chkShowInvoice">Show Bank details on Invoices</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Account -->
<div class="modal fade" id="modalEditAccount" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_account">
                <input type="hidden" name="account_id" id="editAccountId">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Account Title *</label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Account Type *</label>
                            <select name="account_type" id="editType" class="form-select" required>
                                <option value="bank">Bank Account</option>
                                <option value="mobile_wallet">Mobile Wallet</option>
                                <option value="cash">Cash Register</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Bank Name</label>
                            <input type="text" name="bank_name" id="editBankName" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Account #</label>
                            <input type="text" name="account_number" id="editAccNo" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">IBAN</label>
                            <input type="text" name="iban" id="editIban" class="form-control">
                        </div>
                        <div class="col-12 pt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_on_invoice" value="1" id="editShowInvoice">
                                <label class="form-check-label small fw-bold" for="editShowInvoice">Show Bank details on Invoices</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAccount(acc) {
    $('#editAccountId').val(acc.id);
    $('#editName').val(acc.name);
    $('#editType').val(acc.account_type);
    $('#editBankName').val(acc.bank_name || '');
    $('#editAccNo').val(acc.account_number || '');
    $('#editIban').val(acc.iban || '');
    $('#editShowInvoice').prop('checked', parseInt(acc.show_on_invoice) === 1);
    
    new bootstrap.Modal(document.getElementById('modalEditAccount')).show();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
