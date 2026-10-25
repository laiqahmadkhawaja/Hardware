<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('quotations');

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT q.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address 
                        FROM quotations q 
                        JOIN customers c ON q.customer_id = c.id 
                        WHERE q.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$quo = $stmt->get_result()->fetch_assoc();

if (!$quo) {
    die("Quotation not found.");
}

$items = $conn->query("SELECT qi.*, p.name as product_name, p.code as product_code, u.name as unit_name 
                       FROM quotation_items qi 
                       JOIN products p ON qi.product_id = p.id 
                       JOIN units u ON qi.unit_id = u.id 
                       WHERE qi.quotation_id = $id");

$page_title = 'Quotation ' . $quo['quotation_no'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-print-none d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Quotation / Rate Estimation: <?= clean_input($quo['quotation_no']) ?></h4>
        <small class="text-muted">Issued to <?= clean_input($quo['customer_name']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Quotations
        </a>
        <button class="btn btn-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Quotation
        </button>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8">
        <div class="card card-custom p-4 bg-white border printable-invoice">
            <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1"><?= __('app_name') ?></h4>
                    <div class="small text-muted"><?= __('app_tagline') ?></div>
                    <div class="small text-muted">Building Material Estimates & Supplies</div>
                </div>
                <div class="text-end">
                    <h5 class="fw-bold mb-1 font-monospace"><?= clean_input($quo['quotation_no']) ?></h5>
                    <div class="small text-muted">Date: <?= date('d-m-Y', strtotime($quo['date'])) ?></div>
                    <div class="badge bg-warning text-dark text-uppercase"><?= clean_input($quo['status']) ?></div>
                </div>
            </div>

            <div class="bg-light p-3 rounded mb-3">
                <small class="text-muted text-uppercase fw-semibold">Quoted For:</small>
                <div class="fw-bold fs-6"><?= clean_input($quo['customer_name']) ?></div>
                <div class="small text-muted"><?= clean_input($quo['customer_phone']) ?> | <?= clean_input($quo['customer_address']) ?></div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Hardware Product</th>
                            <th class="text-center">Estimated Qty</th>
                            <th class="text-end">Quoted Unit Rate</th>
                            <th class="text-end">Total (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sr = 1;
                        while ($row = $items->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?= $sr++ ?></td>
                                <td>
                                    <div class="fw-semibold"><?= clean_input($row['product_name']) ?></div>
                                    <small class="text-muted font-monospace"><?= clean_input($row['product_code']) ?></small>
                                </td>
                                <td class="text-center"><?= format_qty($row['quantity']) ?> <?= clean_input($row['unit_name']) ?></td>
                                <td class="text-end"><?= number_format($row['unit_price'], 2) ?></td>
                                <td class="text-end fw-semibold"><?= number_format($row['total'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-end mb-4">
                <h5 class="fw-bold text-primary">Estimated Grand Total: <?= format_currency($quo['grand_total']) ?></h5>
            </div>

            <?php if (!empty($quo['notes'])): ?>
                <div class="border rounded p-3 mb-3 bg-light">
                    <small class="fw-bold d-block text-secondary">Notes & Validity:</small>
                    <span class="small"><?= nl2br(clean_input($quo['notes'])) ?></span>
                </div>
            <?php endif; ?>

            <div class="border-top pt-3 text-center small text-muted">
                This is an official hardware estimate and does not alter stock inventory balances until finalized as a bill.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
