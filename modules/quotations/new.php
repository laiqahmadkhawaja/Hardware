<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('quotations');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $date = clean_input($_POST['date']);
    $notes = clean_input($_POST['notes']);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    if ($customer_id <= 0 || empty($product_ids)) {
        $error = 'Please select a customer and at least one item.';
    } else {
        $conn->begin_transaction();
        try {
            $subtotal = 0.00;
            $items_to_save = [];

            for ($i = 0; $i < count($product_ids); $i++) {
                $pid = (int)$product_ids[$i];
                $qty = (float)$quantities[$i];
                $price = (float)$unit_prices[$i];

                if ($qty <= 0) continue;

                $line_total = $qty * $price;
                $subtotal += $line_total;

                $p_info = $conn->query("SELECT base_unit_id FROM products WHERE id = $pid")->fetch_assoc();
                $unit_id = $p_info['base_unit_id'];

                $items_to_save[] = [
                    'product_id' => $pid,
                    'unit_id' => $unit_id,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $line_total
                ];
            }

            $quotation_no = generate_reference_no($conn, 'quotations', 'QUO', 'quotation_no');

            $stmt_q = $conn->prepare("INSERT INTO quotations (quotation_no, customer_id, date, subtotal, grand_total, status, notes) 
                                      VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt_q->bind_param("sisdds", $quotation_no, $customer_id, $date, $subtotal, $subtotal, $notes);
            $stmt_q->execute();
            $quotation_id = $conn->insert_id;

            foreach ($items_to_save as $item) {
                $stmt_qi = $conn->prepare("INSERT INTO quotation_items (quotation_id, product_id, unit_id, quantity, unit_price, total) 
                                           VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_qi->bind_param("iiiddd", $quotation_id, $item['product_id'], $item['unit_id'], $item['qty'], $item['price'], $item['total']);
                $stmt_qi->execute();
            }

            $conn->commit();
            log_audit($conn, 'CREATE_QUOTATION', $quotation_no, "Created quotation for customer #$customer_id");
            set_flash('success', 'Quotation created successfully without altering inventory stock.');
            header("Location: " . BASE_URL . "/modules/quotations/view.php?id=$quotation_id");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$customers = $conn->query("SELECT id, name, name_ur, phone FROM customers WHERE status = 'active' ORDER BY name ASC");
$products = $conn->query("SELECT p.id, p.name, p.code, p.retail_price, u.name as unit_name FROM products p JOIN units u ON p.base_unit_id = u.id ORDER BY p.name ASC");

$page_title = 'Create Quotation';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Create Material Quotation</h4>
        <small class="text-muted">Issue price estimates for construction and building projects</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Quotations
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="card card-custom mb-3">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Select Customer / Contractor *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Choose Customer --</option>
                        <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= clean_input($c['name']) ?> (<?= clean_input($c['phone']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Quotation Date *</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-custom mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-check me-1 text-primary"></i> Estimated Product Lines</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddQRow"><i class="bi bi-plus-lg me-1"></i> Add Line</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="quotationTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50%;">Hardware Product</th>
                            <th style="width: 20%;">Estimated Quantity</th>
                            <th style="width: 20%;">Quoted Unit Price (PKR)</th>
                            <th style="width: 10%;"></th>
                        </tr>
                    </thead>
                    <tbody id="quotationBody">
                        <tr>
                            <td>
                                <select name="product_id[]" class="form-select" required onchange="handleQProd(this)">
                                    <option value="">-- Select Product --</option>
                                    <?php 
                                    $products->data_seek(0);
                                    while ($p = $products->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $p['id'] ?>" data-price="<?= $p['retail_price'] ?>">
                                            <?= clean_input($p['name']) ?> [<?= clean_input($p['code']) ?>]
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="quantity[]" class="form-control" value="1" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="unit_price[]" class="form-control price-q" value="0.00" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeQRow(this)"><i class="bi bi-x-circle fs-5"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-custom mb-3">
        <div class="card-body p-3">
            <label class="form-label small fw-bold">Terms, Validity & Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Rates valid for 7 days. Cement & steel prices subject to mill fluctuation."></textarea>
        </div>
    </div>

    <div class="text-end mb-4">
        <button type="submit" class="btn btn-primary px-4 fw-bold">
            <i class="bi bi-check-circle me-1"></i> Save & Generate Quotation
        </button>
    </div>
</form>

<script>
function handleQProd(sel) {
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt.getAttribute('data-price') || 0);
    const row = sel.closest('tr');
    row.querySelector('.price-q').value = price.toFixed(2);
}

function removeQRow(btn) {
    const tbody = document.getElementById('quotationBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
    }
}

document.getElementById('btnAddQRow').addEventListener('click', function() {
    const tbody = document.getElementById('quotationBody');
    const firstRow = tbody.children[0];
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('input[type="number"]').value = '1';
    tbody.appendChild(newRow);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
