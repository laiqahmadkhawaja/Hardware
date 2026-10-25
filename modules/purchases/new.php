<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('purchases');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)$_POST['supplier_id'];
    $supplier_inv = clean_input($_POST['supplier_invoice_no']);
    $date = clean_input($_POST['date']);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_costs = $_POST['unit_cost'] ?? [];
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 1);
    $payment_method = clean_input($_POST['payment_method'] ?? 'cash');

    if ($supplier_id <= 0 || empty($product_ids)) {
        $error = 'Supplier and at least one product line item are required.';
    } else {
        $conn->begin_transaction();
        try {
            $subtotal = 0.00;
            $items_to_save = [];

            for ($i = 0; $i < count($product_ids); $i++) {
                $pid = (int)$product_ids[$i];
                $qty = (float)$quantities[$i];
                $cost = (float)$unit_costs[$i];

                if ($qty <= 0) continue;

                $line_total = $qty * $cost;
                $subtotal += $line_total;

                $items_to_save[] = [
                    'product_id' => $pid,
                    'qty' => $qty,
                    'cost' => $cost,
                    'total' => $line_total
                ];
            }

            if (empty($items_to_save)) {
                throw new Exception("Please enter valid quantities.");
            }

            $grand_total = $subtotal;
            $due_amount = max(0, $grand_total - $paid_amount);

            $payment_status = 'paid';
            if ($paid_amount <= 0) {
                $payment_status = 'due';
            } elseif ($paid_amount < $grand_total) {
                $payment_status = 'partial';
            }

            $purchase_no = generate_reference_no($conn, 'purchases', 'PUR', 'purchase_no');
            $user_id = $_SESSION['user_id'];

            // 1. Insert Purchase
            $stmt_pur = $conn->prepare("INSERT INTO purchases (purchase_no, supplier_invoice_no, supplier_id, warehouse_id, user_id, date, subtotal, grand_total, paid_amount, due_amount, payment_status) 
                                        VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_pur->bind_param("ssiisdddds", $purchase_no, $supplier_inv, $supplier_id, $user_id, $date, $subtotal, $grand_total, $paid_amount, $due_amount, $payment_status);
            $stmt_pur->execute();
            $purchase_id = $conn->insert_id;

            // 2. Insert Items & Update Stock (Weighted Average Cost)
            foreach ($items_to_save as $item) {
                $pid = $item['product_id'];
                $qty = $item['qty'];
                $cost = $item['cost'];
                $total = $item['total'];

                // Get product unit
                $p_info = $conn->query("SELECT base_unit_id FROM products WHERE id = $pid")->fetch_assoc();
                $unit_id = $p_info['base_unit_id'];

                $stmt_item = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, unit_id, quantity, base_quantity, unit_cost, total) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_item->bind_param("iiidddd", $purchase_id, $pid, $unit_id, $qty, $qty, $cost, $total);
                $stmt_item->execute();

                // Weighted Average Cost & Balance update
                $sb_stmt = $conn->prepare("SELECT quantity, average_cost FROM stock_balances WHERE product_id = ? AND warehouse_id = 1 FOR UPDATE");
                $sb_stmt->bind_param("i", $pid);
                $sb_stmt->execute();
                $sb = $sb_stmt->get_result()->fetch_assoc();

                $old_qty = $sb ? (float)$sb['quantity'] : 0.00;
                $old_cost = $sb ? (float)$sb['average_cost'] : 0.00;

                $new_qty = $old_qty + $qty;
                // Weighted average: ((old_qty * old_cost) + (incoming_qty * incoming_cost)) / new_qty
                $new_avg_cost = ($new_qty > 0) ? ((($old_qty * $old_cost) + ($qty * $cost)) / $new_qty) : $cost;

                $up_sb = $conn->prepare("UPDATE stock_balances SET quantity = ?, average_cost = ? WHERE product_id = ? AND warehouse_id = 1");
                $up_sb->bind_param("ddi", $new_qty, $new_avg_cost, $pid);
                $up_sb->execute();

                // Also update cost_price in products table
                $conn->query("UPDATE products SET cost_price = $cost WHERE id = $pid");

                // Stock Movement
                $sm_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, warehouse_id, transaction_type, reference_no, qty_in, qty_out, balance_after, unit_cost, notes, user_id) 
                                           VALUES (?, 1, 'purchase', ?, ?, 0, ?, ?, 'Goods Receipt Purchase', ?)");
                $sm_stmt->bind_param("isdddi", $pid, $purchase_no, $qty, $new_qty, $cost, $user_id);
                $sm_stmt->execute();
            }

            // 3. Supplier Khata Ledger
            $sup_stmt = $conn->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
            $sup_stmt->bind_param("i", $supplier_id);
            $sup_stmt->execute();
            $sup = $sup_stmt->get_result()->fetch_assoc();

            $running_bal = $sup ? (float)$sup['current_balance'] : 0.00;
            // Purchase bill increases payable (credit)
            $running_bal += $grand_total;
            $conn->query("INSERT INTO supplier_ledger (supplier_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                          VALUES ($supplier_id, '$date', 'purchase', '$purchase_no', 'Goods Purchase Bill', 0, $grand_total, $running_bal)");

            // If cash paid to supplier
            if ($paid_amount > 0) {
                $running_bal -= $paid_amount;
                $conn->query("INSERT INTO supplier_ledger (supplier_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                              VALUES ($supplier_id, '$date', 'payment', '$purchase_no', 'Payment against purchase', $paid_amount, 0, $running_bal)");

                $conn->query("INSERT INTO purchase_payments (purchase_id, supplier_id, account_id, payment_method, amount, date, reference_no) 
                              VALUES ($purchase_id, $supplier_id, $account_id, '$payment_method', $paid_amount, '$date', '$purchase_no')");

                // Deduct cash from account
                $conn->query("UPDATE financial_accounts SET balance = balance - $paid_amount WHERE id = $account_id");
            }

            // Update supplier balance
            $conn->query("UPDATE suppliers SET current_balance = $running_bal WHERE id = $supplier_id");

            $conn->commit();
            log_audit($conn, 'PURCHASE_RECEIPT', $purchase_no, "Recorded purchase: Total $grand_total, Paid $paid_amount");
            set_flash('success', 'Purchase recorded and inventory quantities updated successfully!');
            header("Location: " . BASE_URL . "/modules/purchases/index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$suppliers = $conn->query("SELECT id, name, name_ur, company FROM suppliers WHERE status = 'active' ORDER BY name ASC");
$products = $conn->query("SELECT p.id, p.name, p.code, p.cost_price, u.name as unit_name FROM products p JOIN units u ON p.base_unit_id = u.id ORDER BY p.name ASC");
$accounts = $conn->query("SELECT id, name, account_type FROM financial_accounts ORDER BY id ASC");

$page_title = __('new_purchase');
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Record Purchase / Goods Receipt</h4>
        <small class="text-muted">Receive bulk materials, saria, cement, or sanitary goods from distributors</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Purchases
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="card card-custom mb-3">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Select Supplier (ڈسٹری بیوٹر) *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- Choose Supplier --</option>
                        <?php while ($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>"><?= clean_input($s['name']) ?> (<?= clean_input($s['company']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Supplier Invoice / Bilty #</label>
                    <input type="text" name="supplier_invoice_no" class="form-control" placeholder="e.g. BILTY-88492">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Date *</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Line Items -->
    <div class="card card-custom mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-box-arrow-in-down me-1 text-primary"></i> Received Material Items</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow"><i class="bi bi-plus-lg me-1"></i> Add Item Line</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="purchaseTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45%;">Hardware Product</th>
                            <th style="width: 20%;">Quantity Received</th>
                            <th style="width: 20%;">Purchase Unit Cost (PKR)</th>
                            <th style="width: 15%;">Line Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="purchaseBody">
                        <tr>
                            <td>
                                <select name="product_id[]" class="form-select prod-select" required onchange="handleProductChange(this)">
                                    <option value="">-- Select Product --</option>
                                    <?php 
                                    $products->data_seek(0);
                                    while ($p = $products->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $p['id'] ?>" data-cost="<?= $p['cost_price'] ?>" data-unit="<?= $p['unit_name'] ?>">
                                            <?= clean_input($p['name']) ?> [<?= clean_input($p['code']) ?>]
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="quantity[]" class="form-control qty-input" value="1" required oninput="calcRow(this)">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="unit_cost[]" class="form-control cost-input" value="0.00" required oninput="calcRow(this)">
                            </td>
                            <td class="fw-bold line-total text-end">0.00</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-x-circle fs-5"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment & Settlement -->
    <div class="row justify-content-end mb-4">
        <div class="col-12 col-md-5">
            <div class="card card-custom p-3 bg-light">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold">Total Bill:</span>
                    <span class="fw-bold fs-5 text-primary" id="lblGrandTotal">PKR 0.00</span>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Amount Paid Now (PKR)</label>
                    <input type="number" step="0.01" name="paid_amount" id="txtPaidAmount" class="form-control text-success fw-bold" value="0.00">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Payment Method</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Account</label>
                        <select name="account_id" class="form-select form-select-sm">
                            <?php while ($acc = $accounts->fetch_assoc()): ?>
                                <option value="<?= $acc['id'] ?>"><?= clean_input($acc['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Confirm & Receive Stock
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function handleProductChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    const cost = parseFloat(opt.getAttribute('data-cost') || 0);
    const row = sel.closest('tr');
    row.querySelector('.cost-input').value = cost.toFixed(2);
    calcRow(sel);
}

function calcRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    const total = qty * cost;
    row.querySelector('.line-total').textContent = total.toFixed(2);
    recalcTotal();
}

function removeRow(btn) {
    const tbody = document.getElementById('purchaseBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        recalcTotal();
    }
}

function recalcTotal() {
    let grand = 0;
    document.querySelectorAll('.line-total').forEach(el => {
        grand += parseFloat(el.textContent) || 0;
    });
    document.getElementById('lblGrandTotal').textContent = 'PKR ' + grand.toFixed(2);
}

document.getElementById('btnAddRow').addEventListener('click', function() {
    const tbody = document.getElementById('purchaseBody');
    const firstRow = tbody.children[0];
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.qty-input').value = '1';
    newRow.querySelector('.line-total').textContent = '0.00';
    tbody.appendChild(newRow);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
