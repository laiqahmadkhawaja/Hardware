<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();

$id = (int)($_GET['id'] ?? 0);
$auto_print = isset($_GET['print']);

// Fetch sale details
$stmt = $conn->prepare("SELECT s.*, c.name as customer_name, c.name_ur as customer_name_ur, c.phone as customer_phone, c.address as customer_address, u.username, u.full_name as cashier_name 
                        FROM sales s 
                        JOIN customers c ON s.customer_id = c.id 
                        LEFT JOIN users u ON s.user_id = u.id 
                        WHERE s.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die("Invoice not found.");
}

// Fetch sale items
$stmt_items = $conn->prepare("SELECT si.*, p.name as product_name, p.name_ur as product_name_ur, p.code as product_code, u.name as unit_name 
                             FROM sale_items si 
                             JOIN products p ON si.product_id = p.id 
                             JOIN units u ON si.unit_id = u.id 
                             WHERE si.sale_id = ?");
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items = $stmt_items->get_result();

$page_title = 'Invoice ' . $sale['invoice_no'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-print-none d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Sales Invoice: <?= clean_input($sale['invoice_no']) ?></h4>
        <small class="text-muted">Dated: <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Sales
        </a>
        <a href="<?= BASE_URL ?>/pos.php" class="btn btn-primary btn-sm">
            <i class="bi bi-cart-plus me-1"></i> New POS Sale
        </a>
        <a href="<?= BASE_URL ?>/modules/sales/thermal.php?id=<?= $sale['id'] ?>&print=true" target="_blank" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-receipt-cutoff me-1"></i> Thermal Receipt (80mm)
        </a>
        <button class="btn btn-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print A4 Invoice
        </button>
    </div>
</div>

<div class="row justify-content-center">
    <!-- Printable Invoice Card (A4 / Standard) -->
    <div class="col-12 col-md-10 col-lg-8">
        <div class="card card-custom p-4 bg-white border printable-invoice">
            <!-- Header -->
            <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                <div>
                    <h4 class="fw-bold text-primary mb-1"><?= __('app_name') ?></h4>
                    <div class="small text-muted"><?= __('app_tagline') ?></div>
                    <div class="small text-muted">Main GT Road, Lahore | 0300-1234567</div>
                </div>
                <div class="text-end">
                    <h5 class="fw-bold mb-1 font-monospace"><?= clean_input($sale['invoice_no']) ?></h5>
                    <div class="small text-muted">Date: <?= date('d-m-Y', strtotime($sale['date'])) ?></div>
                    <div class="small text-muted">Time: <?= date('h:i A', strtotime($sale['time'])) ?></div>
                    <div class="small text-muted">Cashier: <?= clean_input($sale['cashier_name'] ?? 'Admin') ?></div>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="bg-light p-3 rounded mb-3">
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-semibold">Billed To (خریدار):</small>
                        <div class="fw-bold fs-6">
                            <?= is_rtl() && !empty($sale['customer_name_ur']) ? clean_input($sale['customer_name_ur']) : clean_input($sale['customer_name']) ?>
                        </div>
                        <div class="small text-muted"><?= clean_input($sale['customer_phone']) ?></div>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted text-uppercase fw-semibold">Payment Status:</small>
                        <div>
                            <?php if ($sale['payment_status'] === 'paid'): ?>
                                <span class="badge bg-success">Fully Paid (نقد ادا شدہ)</span>
                            <?php elseif ($sale['payment_status'] === 'partial'): ?>
                                <span class="badge bg-warning text-dark">Partial (نامکمل ادائیگی)</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Khata Credit / Due (ادھار)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-3">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 45%;">Item Description</th>
                            <th class="text-center" style="width: 15%;">Quantity</th>
                            <th class="text-end" style="width: 15%;">Price (PKR)</th>
                            <th class="text-end" style="width: 20%;">Total</th>
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
                                    <?php if (!empty($row['product_name_ur'])): ?>
                                        <small class="text-muted"><?= clean_input($row['product_name_ur']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= format_qty($row['quantity']) ?> <?= clean_input($row['unit_name']) ?>
                                </td>
                                <td class="text-end"><?= number_format($row['unit_price'], 2) ?></td>
                                <td class="text-end fw-semibold"><?= number_format($row['total'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals, Payment & Bank Account Details -->
            <div class="row align-items-start mb-4">
                <div class="col-7">
                    <?php
                    // Fetch bank accounts enabled for invoice display
                    $invoice_banks = $conn->query("SELECT * FROM financial_accounts WHERE show_on_invoice = 1 ORDER BY id ASC");
                    if ($invoice_banks && $invoice_banks->num_rows > 0):
                    ?>
                        <div class="p-3 rounded border bg-light">
                            <small class="fw-bold text-uppercase d-block text-primary mb-1">
                                <i class="bi bi-bank me-1"></i> Bank Accounts & Digital Payment Details
                            </small>
                            <div class="row g-2">
                                <?php while ($bk = $invoice_banks->fetch_assoc()): ?>
                                    <div class="col-12 col-sm-6">
                                        <div class="border-start border-2 border-primary ps-2 py-1 bg-white rounded shadow-sm">
                                            <div class="fw-bold small text-dark"><?= clean_input($bk['bank_name'] ?: $bk['name']) ?></div>
                                            <div class="small text-muted font-monospace">A/C: <?= clean_input($bk['account_number']) ?></div>
                                            <?php if (!empty($bk['iban'])): ?>
                                                <div class="small text-muted font-monospace" style="font-size: 0.75rem;">IBAN: <?= clean_input($bk['iban']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-5">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-semibold">PKR <?= number_format($sale['subtotal'], 2) ?></span>
                    </div>
                    <?php if ($sale['discount_amount'] > 0): ?>
                        <div class="d-flex justify-content-between py-1 border-bottom text-danger">
                            <span>Discount:</span>
                            <span>- PKR <?= number_format($sale['discount_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="fw-bold fs-6">Grand Total:</span>
                        <span class="fw-bold fs-6 text-primary">PKR <?= number_format($sale['grand_total'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom text-success">
                        <span>Paid Amount:</span>
                        <span class="fw-bold">PKR <?= number_format($sale['paid_amount'], 2) ?></span>
                    </div>
                    <?php if ($sale['due_amount'] > 0): ?>
                        <div class="d-flex justify-content-between py-1 text-danger">
                            <span class="fw-bold">Remaining Due:</span>
                            <span class="fw-bold">PKR <?= number_format($sale['due_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer Notes -->
            <?php
            $footer_q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'receipt_footer' LIMIT 1");
            $receipt_footer = $footer_q ? ($footer_q->fetch_assoc()['setting_value'] ?? '') : '';
            ?>
            <div class="border-top pt-3 text-center small text-muted">
                <?php if (!empty($receipt_footer)): ?>
                    <p class="mb-0"><?= nl2br(clean_input($receipt_footer)) ?></p>
                <?php else: ?>
                    <p class="mb-1">Thank you for your business! Goods once sold cannot be returned without original cash receipt within 3 days.</p>
                    <p class="mb-0">خریدا گیا مال بغیر رسید کے تبدیل یا واپس نہیں ہوگا۔ شکریہ!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($auto_print): ?>
<script>
window.onload = function() {
    window.print();
};
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
