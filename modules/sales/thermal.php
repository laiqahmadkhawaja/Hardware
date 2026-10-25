<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();

$id = (int)($_GET['id'] ?? 0);
$auto_print = isset($_GET['print']);
$paper_size = (isset($_GET['size']) && $_GET['size'] === '58') ? 58 : 80;

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
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            background-color: #e2e8f0;
            color: #000000;
            font-family: 'Courier New', Courier, monospace, system-ui;
            margin: 0;
            padding: 20px 10px;
        }

        .receipt-container {
            width: <?= $paper_size === 58 ? '58mm' : '80mm' ?>;
            max-width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: <?= $paper_size === 58 ? '8px 5px' : '14px 10px' ?>;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: <?= $paper_size === 58 ? '11px' : '12px' ?>;
            line-height: 1.35;
            color: #000000;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 8px;
        }
        .store-title {
            font-size: <?= $paper_size === 58 ? '15px' : '18px' ?>;
            font-weight: 900;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            letter-spacing: -0.5px;
        }
        .store-title-ur {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 4px 0;
            font-family: system-ui, sans-serif;
        }
        .store-sub {
            font-size: 10px;
            margin: 0;
            color: #222;
        }

        .divider {
            border-top: 1px dashed #000000;
            margin: 6px 0;
        }
        .divider-double {
            border-top: 2px solid #000000;
            margin: 6px 0;
        }

        .receipt-meta {
            font-size: 11px;
            margin-bottom: 6px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .items-table th {
            border-bottom: 1px dashed #000;
            border-top: 1px dashed #000;
            padding: 4px 0;
            font-size: 11px;
            text-align: left;
        }
        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .item-name {
            font-weight: bold;
            font-size: 11.5px;
            word-break: break-word;
        }
        .item-details {
            font-size: 10.5px;
            color: #111;
        }

        /* Totals */
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }
        .grand-total {
            font-size: <?= $paper_size === 58 ? '15px' : '17px' ?>;
            font-weight: 900;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin: 4px 0;
        }

        .footer-note {
            text-align: center;
            font-size: 10px;
            margin-top: 8px;
            line-height: 1.3;
        }

        .barcode-wrapper {
            text-align: center;
            margin: 10px 0 4px 0;
        }
        .barcode-text {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        /* Screen Toolbar */
        .action-toolbar {
            max-width: 500px;
            margin: 0 auto 15px auto;
        }

        /* Print Media Settings */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .action-toolbar {
                display: none !important;
            }
            .receipt-container {
                width: 100% !important;
                max-width: <?= $paper_size === 58 ? '58mm' : '80mm' ?> !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 auto !important;
            }
            @page {
                size: <?= $paper_size === 58 ? '58mm' : '80mm' ?> auto;
                margin: 0;
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
                <th style="width: 50%;">ITEM</th>
                <th style="width: 25%; text-align: center;">QTY</th>
                <th style="width: 25%; text-align: right;">TOTAL</th>
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
                            <small style="font-family: system-ui;">(<?= clean_input($row['product_name_ur']) ?>)</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="item-details" style="padding-left: 6px;">
                        <?= format_qty($row['quantity']) ?> <?= clean_input($row['unit_name']) ?> @ <?= number_format($row['unit_price'], 2) ?>
                    </td>
                    <td></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($row['total'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Financial Totals -->
    <div class="total-row">
        <span>Total Items Qty:</span>
        <span><?= format_qty($item_count) ?></span>
    </div>
    <div class="total-row">
        <span>Subtotal:</span>
        <span>PKR <?= number_format($sale['subtotal'], 2) ?></span>
    </div>

    <?php if ($sale['discount_amount'] > 0): ?>
        <div class="total-row" style="color: #000;">
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
        <span style="font-weight: bold;">PKR <?= number_format($sale['paid_amount'], 2) ?></span>
    </div>

    <?php if ($sale['due_amount'] > 0): ?>
        <div class="total-row" style="font-weight: bold;">
            <span>Current Balance Due:</span>
            <span>PKR <?= number_format($sale['due_amount'], 2) ?></span>
        </div>
        <div class="total-row" style="font-size: 10.5px;">
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
        <div style="font-size: 10px; margin-bottom: 3px; font-weight: bold; text-align: center;">BANK / DIGITAL PAYMENT ACCOUNTS</div>
        <?php while ($b = $banks->fetch_assoc()): ?>
            <div style="font-size: 9.5px; margin-bottom: 2px;">
                <strong><?= clean_input($b['bank_name'] ?: $b['name']) ?>:</strong> <?= clean_input($b['account_number']) ?>
                <?php if (!empty($b['iban'])): ?>
                    <div style="font-size: 8.5px;">IBAN: <?= clean_input($b['iban']) ?></div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <div class="divider"></div>

    <!-- Barcode -->
    <div class="barcode-wrapper">
        <?= generate_barcode_svg($sale['invoice_no'], 32, 1.2) ?>
        <div class="barcode-text"><?= clean_input($sale['invoice_no']) ?></div>
    </div>

    <!-- Return Terms & Policy -->
    <div class="footer-note">
        <?php if (!empty($receipt_footer)): ?>
            <div><?= nl2br(clean_input($receipt_footer)) ?></div>
        <?php else: ?>
            <div>Thank you for your business!</div>
            <div>Goods once sold can be exchanged within 3 days with this receipt.</div>
            <div style="font-family: system-ui; margin-top: 2px;">خریدا گیا مال بغیر رسید کے واپس نہیں ہوگا۔ شکریہ!</div>
        <?php endif; ?>
        <div style="font-size: 8.5px; margin-top: 6px; color: #555;">Software: Pak Hardware ERP & POS</div>
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
