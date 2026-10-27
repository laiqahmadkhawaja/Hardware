<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();

$id = (int)($_GET['id'] ?? 0);
$auto_print = isset($_GET['print']);
$paper_size = (isset($_GET['size']) && $_GET['size'] === '58') ? 58 : 80;
// Safe printable content width factoring in thermal head printable width & driver hardware margins
// 80mm roll: 72mm thermal head with 68mm safe printable content width
// 58mm roll: 48mm thermal head with 45mm safe printable content width
$printable_width = ($paper_size === 58) ? '45mm' : '68mm';
$roll_size = ($paper_size === 58) ? '58mm' : '80mm';

// Fetch sale details
$stmt = $conn->prepare("SELECT s.*, c.name as customer_name, c.name_ur as customer_name_ur, c.phone as customer_phone, c.current_balance as customer_balance,
                               u.full_name as cashier_name 
                        FROM sales s 
                        JOIN customers c ON s.customer_id = c.id 
                        LEFT JOIN users u ON s.user_id = u.id 
                        WHERE s.id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die("Sale invoice not found.");
}

// Fetch sale items
$stmt_items = $conn->prepare("SELECT si.*, p.name as product_name, p.name_ur as product_name_ur, p.code as product_code, u.name as unit_name, u.name_ur as unit_name_ur 
                             FROM sale_items si 
                             JOIN products p ON si.product_id = p.id 
                             JOIN units u ON si.unit_id = u.id 
                             WHERE si.sale_id = ?");
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items = $stmt_items->get_result();

// Store & Settings
$biz = $conn->query("SELECT * FROM businesses WHERE id = 1")->fetch_assoc();
$footer_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'receipt_footer'")->fetch_assoc();
$receipt_footer = $footer_res['setting_value'] ?? '';

// Enabled bank accounts
$banks = $conn->query("SELECT * FROM financial_accounts WHERE show_on_invoice = 1 ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thermal Receipt - <?= clean_input($sale['invoice_no']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.css">
    <style>
        * {
            box-sizing: border-box !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            background-color: #e2e8f0;
            color: #000000;
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif;
            margin: 0;
            padding: 20px 10px;
        }

        /* 1 & 2 & 4 & 5: Monoline Sans-Serif, Uniform 700 Bold, Pure #000, Geometric Precision */
        .receipt-container,
        .receipt-container * {
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-weight: 700 !important;
            color: #000000 !important;
            fill: #000000 !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: geometricPrecision;
            box-sizing: border-box !important;
        }

        /* 6: Printable Widths - 45mm safe for 58mm roll, 68mm safe for 80mm roll */
        .receipt-container {
            width: <?= $printable_width ?>;
            max-width: <?= $printable_width ?>;
            margin: 0 auto;
            background: #ffffff;
            padding: <?= $paper_size === 58 ? '4px 3px' : '8px 5px' ?>;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: <?= $paper_size === 58 ? '10px' : '11.5px' ?>;
            line-height: 1.3;
        }

        /* 3: Hierarchy via Font Size Only */
        .receipt-header {
            text-align: center;
            margin-bottom: 6px;
        }
        .store-title {
            font-size: <?= $paper_size === 58 ? '13px' : '16px' ?>;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            letter-spacing: -0.2px;
            line-height: 1.2;
            word-break: break-word;
        }
        .store-title-ur {
            font-size: <?= $paper_size === 58 ? '12px' : '14px' ?>;
            margin: 0 0 3px 0;
            line-height: 1.3;
        }
        .store-sub {
            font-size: <?= $paper_size === 58 ? '9px' : '10.5px' ?>;
            margin: 0;
            line-height: 1.25;
            word-break: break-word;
        }

        .divider {
            border-top: 1px dashed #000000;
            margin: 5px 0;
        }
        .divider-double {
            border-top: 2px solid #000000;
            margin: 5px 0;
        }

        .receipt-meta {
            font-size: <?= $paper_size === 58 ? '10px' : '11.5px' ?>;
            margin-bottom: 5px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .meta-row span:last-child {
            text-align: right;
            padding-right: 1.5mm !important;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 3px 0;
            table-layout: fixed;
        }
        .items-table th {
            border-bottom: 1px dashed #000000;
            border-top: 1px dashed #000000;
            padding: 3px 0;
            font-size: <?= $paper_size === 58 ? '10px' : '11.5px' ?>;
        }
        .items-table th:last-child {
            padding-right: 1.5mm !important;
        }
        .items-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .item-name {
            font-size: <?= $paper_size === 58 ? '10.5px' : '12px' ?>;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.25;
        }
        .item-details {
            font-size: <?= $paper_size === 58 ? '9px' : '10.5px' ?>;
            word-break: break-word;
        }
        .item-total {
            font-size: <?= $paper_size === 58 ? '10.5px' : '12px' ?>;
            text-align: right;
            white-space: nowrap;
            padding-right: 1.5mm !important;
        }

        /* Totals */
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: <?= $paper_size === 58 ? '10px' : '11.5px' ?>;
            padding: 1.5px 0;
        }
        .total-row span:last-child {
            text-align: right;
            white-space: nowrap;
            padding-right: 1.5mm !important;
        }
        .grand-total {
            font-size: <?= $paper_size === 58 ? '13px' : '15px' ?>;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            padding: 3px 0;
            margin: 3px 0;
        }

        .bank-info {
            font-size: <?= $paper_size === 58 ? '9px' : '10.5px' ?>;
            margin-bottom: 2px;
            word-break: break-all;
        }

        .footer-note {
            text-align: center;
            font-size: <?= $paper_size === 58 ? '8.5px' : '10px' ?>;
            margin-top: 6px;
            line-height: 1.25;
            word-break: break-word;
        }

        .barcode-wrapper {
            text-align: center;
            margin: 8px 0 3px 0;
            overflow: hidden;
        }
        .barcode-text {
            font-size: <?= $paper_size === 58 ? '9px' : '10.5px' ?>;
            letter-spacing: 1.5px;
            margin-top: 2px;
        }

        /* Screen Toolbar */
        .action-toolbar {
            max-width: 500px;
            margin: 0 auto 15px auto;
        }

        /* Print Media Settings */
        @media print {
            html, body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .action-toolbar {
                display: none !important;
            }
            .receipt-container {
                width: <?= $printable_width ?> !important;
                max-width: <?= $printable_width ?> !important;
                box-shadow: none !important;
                /* Safe inner gutter so driver hardware boundary never clips right-aligned numbers */
                padding: 0 2mm 0 1.5mm !important;
                margin: 0 auto !important;
                overflow: hidden !important;
            }
            @page {
                size: <?= $roll_size ?> auto;
                margin: 0mm;
            }
        }
    </style>
</head>
<body>

<!-- On-Screen Action Toolbar (d-print-none) -->
<div class="action-toolbar d-print-none">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-2 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="d-flex gap-2">
                <button class="btn btn-dark btn-sm fw-bold px-3" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1"></i> Print Receipt
                </button>
                <div class="btn-group btn-group-sm">
                    <a href="?id=<?= $sale['id'] ?>&size=80<?= $auto_print ? '&print=1' : '' ?>" class="btn <?= $paper_size === 80 ? 'btn-primary' : 'btn-outline-secondary' ?>">80mm</a>
                    <a href="?id=<?= $sale['id'] ?>&size=58<?= $auto_print ? '&print=1' : '' ?>" class="btn <?= $paper_size === 58 ? 'btn-primary' : 'btn-outline-secondary' ?>">58mm</a>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/modules/sales/view.php?id=<?= $sale['id'] ?>" class="btn btn-outline-dark btn-sm" title="View full A4 invoice">
                    <i class="bi bi-file-earmark-text me-1"></i> A4 Version
                </a>
                <a href="<?= BASE_URL ?>/pos.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-cart-plus me-1"></i> New Sale
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Thermal Receipt Paper -->
<div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
        <div class="store-title"><?= clean_input($biz['name']) ?></div>
        <?php if (!empty($biz['name_ur'])): ?>
            <div class="store-title-ur"><?= clean_input($biz['name_ur']) ?></div>
        <?php endif; ?>
        <?php if (!empty($biz['address'])): ?>
            <div class="store-sub"><?= clean_input($biz['address']) ?></div>
        <?php endif; ?>
        <?php if (!empty($biz['phone'])): ?>
            <div class="store-sub">Tel: <?= clean_input($biz['phone']) ?></div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <!-- Receipt Meta -->
    <div class="receipt-meta">
        <div class="meta-row">
            <span><strong>Receipt #:</strong> <?= clean_input($sale['invoice_no']) ?></span>
            <span><?= date('d-m-Y', strtotime($sale['date'])) ?></span>
        </div>
        <div class="meta-row">
            <span><strong>Time:</strong> <?= date('h:i A', strtotime($sale['time'])) ?></span>
            <span><strong>User:</strong> <?= clean_input($sale['cashier_name'] ?? 'Admin') ?></span>
        </div>
        <div class="meta-row">
            <span><strong>Customer:</strong> <?= clean_input($sale['customer_name']) ?></span>
            <?php if (!empty($sale['customer_phone'])): ?>
                <span><?= clean_input($sale['customer_phone']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items List -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 44%; text-align: left;">ITEM</th>
                <th style="width: 26%; text-align: center;">QTY</th>
                <th style="width: 30%; text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $item_count = 0;
            while ($row = $items->fetch_assoc()): 
                $item_count += $row['quantity'];
            ?>
                <tr>
                    <td colspan="3" class="item-name">
                        <?= clean_input($row['product_name']) ?>
                        <?php if (!empty($row['product_name_ur'])): ?>
                            <span>(<?= clean_input($row['product_name_ur']) ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="item-details" style="padding-left: 2px;">
                        @ <?= number_format($row['unit_price'], 2) ?>
                    </td>
                    <td style="text-align: center; font-size: <?= $paper_size === 58 ? '9.5px' : '11px' ?>; white-space: nowrap;">
                        <?= format_qty($row['quantity']) ?> <?= clean_input($row['unit_name']) ?>
                    </td>
                    <td class="item-total"><?= number_format($row['total'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Financial Totals (Hierarchy via Font Size Only) -->
    <div class="total-row">
        <span>Total Items Qty:</span>
        <span><?= format_qty($item_count) ?></span>
    </div>
    <div class="total-row">
        <span>Subtotal:</span>
        <span>PKR <?= number_format($sale['subtotal'], 2) ?></span>
    </div>

    <?php if ($sale['discount_amount'] > 0): ?>
        <div class="total-row">
            <span>Discount:</span>
            <span>- PKR <?= number_format($sale['discount_amount'], 2) ?></span>
        </div>
    <?php endif; ?>

    <div class="total-row grand-total">
        <span>NET PAYABLE:</span>
        <span>PKR <?= number_format($sale['grand_total'], 2) ?></span>
    </div>

    <div class="total-row">
        <span>Paid (<?= strtoupper($sale['payment_method'] ?? 'CASH') ?>):</span>
        <span>PKR <?= number_format($sale['paid_amount'], 2) ?></span>
    </div>

    <?php if ($sale['due_amount'] > 0): ?>
        <div class="total-row">
            <span>Current Balance Due:</span>
            <span>PKR <?= number_format($sale['due_amount'], 2) ?></span>
        </div>
        <div class="total-row" style="font-size: <?= $paper_size === 58 ? '9.5px' : '11px' ?>;">
            <span>Total Khata Balance:</span>
            <span>PKR <?= number_format($sale['customer_balance'], 2) ?></span>
        </div>
    <?php else: ?>
        <?php $change = max(0, $sale['paid_amount'] - $sale['grand_total']); ?>
        <?php if ($change > 0): ?>
            <div class="total-row">
                <span>Change Returned:</span>
                <span>PKR <?= number_format($change, 2) ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Bank Accounts / Online Details if enabled -->
    <?php if ($banks && $banks->num_rows > 0): ?>
        <div class="divider"></div>
        <div style="font-size: <?= $paper_size === 58 ? '9.5px' : '11px' ?>; margin-bottom: 3px; text-align: center;">BANK / DIGITAL PAYMENT ACCOUNTS</div>
        <?php while ($b = $banks->fetch_assoc()): ?>
            <div class="bank-info">
                <span><?= clean_input($b['bank_name'] ?: $b['name']) ?>:</span> <?= clean_input($b['account_number']) ?>
                <?php if (!empty($b['iban'])): ?>
                    <div>IBAN: <?= clean_input($b['iban']) ?></div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <div class="divider"></div>

    <!-- Barcode -->
    <div class="barcode-wrapper">
        <?= generate_barcode_svg($sale['invoice_no'], $paper_size === 58 ? 26 : 30, $paper_size === 58 ? 0.9 : 1.1) ?>
        <div class="barcode-text"><?= clean_input($sale['invoice_no']) ?></div>
    </div>

    <!-- Return Terms & Policy -->
    <div class="footer-note">
        <?php if (!empty($receipt_footer)): ?>
            <div><?= nl2br(clean_input($receipt_footer)) ?></div>
        <?php else: ?>
            <div>Thank you for your business!</div>
            <div>Goods once sold can be exchanged within 3 days with this receipt.</div>
            <div style="margin-top: 2px;">خریدا گیا مال بغیر رسید کے واپس نہیں ہوگا۔ شکریہ!</div>
        <?php endif; ?>
        <div style="font-size: <?= $paper_size === 58 ? '8px' : '9px' ?>; margin-top: 5px;">Software: Pak Hardware ERP & POS</div>
    </div>
</div>

<?php if ($auto_print): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    window.print();
});
</script>
<?php endif; ?>

</body>
</html>
