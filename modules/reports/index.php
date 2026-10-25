<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('reports');

$page_title = __('reports') . ' & Accounting Balance Sheets';

$start_date = clean_input($_GET['start_date'] ?? date('Y-m-01'));
$end_date = clean_input($_GET['end_date'] ?? date('Y-m-d'));
$report_tab = clean_input($_GET['tab'] ?? 'pnl');

// --- 1. P&L Statement Queries ---
$sales_stmt = $conn->prepare("SELECT IFNULL(SUM(grand_total), 0) as total_sales,
                                     IFNULL(SUM(paid_amount), 0) as total_cash_received,
                                     IFNULL(SUM(due_amount), 0) as total_credit_sales
                              FROM sales WHERE date BETWEEN ? AND ?");
$sales_stmt->bind_param("ss", $start_date, $end_date);
$sales_stmt->execute();
$sales_data = $sales_stmt->get_result()->fetch_assoc();

$cogs_stmt = $conn->prepare("SELECT IFNULL(SUM(si.quantity * si.cost_price), 0) as cogs 
                             FROM sale_items si 
                             JOIN sales s ON si.sale_id = s.id 
                             WHERE s.date BETWEEN ? AND ?");
$cogs_stmt->bind_param("ss", $start_date, $end_date);
$cogs_stmt->execute();
$cogs = (float)$cogs_stmt->get_result()->fetch_assoc()['cogs'];

$gross_profit = (float)$sales_data['total_sales'] - $cogs;

$exp_stmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as total_expenses FROM expenses WHERE date BETWEEN ? AND ?");
$exp_stmt->bind_param("ss", $start_date, $end_date);
$exp_stmt->execute();
$total_expenses = (float)$exp_stmt->get_result()->fetch_assoc()['total_expenses'];

$net_profit = $gross_profit - $total_expenses;

$pur_stmt = $conn->prepare("SELECT IFNULL(SUM(grand_total), 0) as total_purchases FROM purchases WHERE date BETWEEN ? AND ?");
$pur_stmt->bind_param("ss", $start_date, $end_date);
$pur_stmt->execute();
$total_purchases = (float)$pur_stmt->get_result()->fetch_assoc()['total_purchases'];

// Detailed category expenses breakdown
$exp_breakdown_rows = [];
$exp_cat_labels = [];
$exp_cat_values = [];
$exp_breakdown = $conn->query("SELECT ec.name, ec.name_ur, IFNULL(SUM(e.amount), 0) as cat_amount 
                               FROM expense_categories ec 
                               LEFT JOIN expenses e ON ec.id = e.category_id AND e.date BETWEEN '$start_date' AND '$end_date' 
                               GROUP BY ec.id 
                               HAVING cat_amount > 0");
if ($exp_breakdown) {
    while ($eb = $exp_breakdown->fetch_assoc()) {
        $exp_breakdown_rows[] = $eb;
        $exp_cat_labels[] = is_rtl() && !empty($eb['name_ur']) ? $eb['name_ur'] : $eb['name'];
        $exp_cat_values[] = round((float)$eb['cat_amount'], 2);
    }
}
if (empty($exp_cat_labels)) {
    $exp_cat_labels = ['General Expenses'];
    $exp_cat_values = [$total_expenses > 0 ? round($total_expenses, 2) : 0];
}

// --- 2. Balance Sheet & Financial Health (Matched State) ---
// Assets:
// 1. Cash & Bank Accounts
$bank_q = $conn->query("SELECT * FROM financial_accounts ORDER BY id ASC");
$total_cash_bank = 0.00;
$bank_list = [];
while ($b = $bank_q->fetch_assoc()) {
    $total_cash_bank += (float)$b['balance'];
    $bank_list[] = $b;
}

// 2. Customer Receivables (Debtors)
$cust_q = $conn->query("SELECT IFNULL(SUM(current_balance), 0) as receivables FROM customers WHERE current_balance > 0");
$customer_receivables = (float)$cust_q->fetch_assoc()['receivables'];

// 3. Current Inventory Valuation (Stock)
$inv_q = $conn->query("SELECT IFNULL(SUM(quantity * average_cost), 0) as stock_val FROM stock_balances");
$inventory_valuation = (float)$inv_q->fetch_assoc()['stock_val'];

$total_assets = $total_cash_bank + $customer_receivables + $inventory_valuation;

// Liabilities:
// 1. Supplier Payables (Creditors)
$supp_q = $conn->query("SELECT IFNULL(SUM(current_balance), 0) as payables FROM suppliers WHERE current_balance > 0");
$supplier_payables = (float)$supp_q->fetch_assoc()['payables'];

// Equity / Capital Fund (Balances equation: Assets = Liabilities + Capital)
$owner_equity = $total_assets - $supplier_payables;

$total_liabilities_equity = $supplier_payables + $owner_equity;

$enable_charts = true;
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Financial & Accounting Reports</h4>
        <small class="text-muted">Balanced financial statements, Profit & Loss, Trial Balance & Balance Sheet</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-dark btn-sm d-print-none" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Tabs & Date Filter -->
<div class="card card-custom mb-3 d-print-none">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link <?= $report_tab === 'pnl' ? 'active' : '' ?>" href="?tab=pnl&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                        <i class="bi bi-graph-up me-1"></i> Profit & Loss (نفع و نقصان)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $report_tab === 'balance_sheet' ? 'active' : '' ?>" href="?tab=balance_sheet&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                        <i class="bi bi-scale me-1"></i> Balance Sheet (ترازنامہ)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $report_tab === 'trial_balance' ? 'active' : '' ?>" href="?tab=trial_balance&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                        <i class="bi bi-journal-check me-1"></i> Trial Balance (میزان چٹھا)
                    </a>
                </li>
            </ul>

            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center w-100 w-md-auto">
                <input type="hidden" name="tab" value="<?= clean_input($report_tab) ?>">
                <div class="input-group input-group-sm flex-grow-1" style="max-width: 320px; min-width: 240px;">
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm flex-shrink-0"><i class="bi bi-filter"></i> Apply</button>
            </form>
        </div>
    </div>
</div>

<?php if ($report_tab === 'pnl'): ?>
    <!-- P&L Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <div class="stat-title">Net Sales Revenue</div>
                    <div class="stat-value"><?= format_currency($sales_data['total_sales']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-box-arrow-right"></i>
                </div>
                <div>
                    <div class="stat-title">Cost of Goods Sold (COGS)</div>
                    <div class="stat-value text-secondary"><?= format_currency($cogs) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <div class="stat-title">Gross Profit</div>
                    <div class="stat-value text-success"><?= format_currency($gross_profit) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <div>
                    <div class="stat-title">Net Operating Profit</div>
                    <div class="stat-value <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= format_currency($net_profit) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- P&L Visual Breakdown Charts (d-print-none) -->
    <div class="row g-3 mb-4 d-print-none">
        <!-- Revenue, COGS, Expenses & Profit Breakdown -->
        <div class="col-12 col-lg-8">
            <div class="card card-custom h-100 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="bi bi-bar-chart-line text-primary me-2"></i>Financial Performance & Margin Breakdown
                    </span>
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-medium">Income Statement Graph</span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="pnlBreakdownChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operating Expenses by Category -->
        <div class="col-12 col-lg-4">
            <div class="card card-custom h-100 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="bi bi-pie-chart text-danger me-2"></i>Expenses Distribution
                    </span>
                    <span class="badge bg-danger bg-opacity-10 text-danger fw-medium">PKR <?= number_format($total_expenses, 0) ?></span>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div style="position: relative; height: 230px; width: 100%;">
                        <canvas id="pnlExpensesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profit & Loss Statement -->
    <div class="card card-custom p-4 bg-white printable-invoice">
        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><?= __('app_name') ?></h4>
                <div class="small text-muted">Profit & Loss Income Statement (آمدن و خرچ گوشوارہ)</div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Period: <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Account Category / Line Item</th>
                        <th class="text-end" style="width: 25%;">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Gross Sales Revenue (فروخت آمدن)</td>
                        <td class="text-end fw-bold text-dark"><?= number_format($sales_data['total_sales'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-danger ps-4">Less: Cost of Goods Sold (لاگت برائے فروخت)</td>
                        <td class="text-end text-danger fw-semibold">- <?= number_format($cogs, 2) ?></td>
                    </tr>
                    <tr class="table-light">
                        <td class="fw-bold">Gross Profit (مجموعی منافع)</td>
                        <td class="text-end fw-bold text-success fs-6"><?= number_format($gross_profit, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-danger ps-4">Less: Operating Expenses - Rent, Wages, Freight (کاروباری اخراجات)</td>
                        <td class="text-end text-danger fw-semibold">- <?= number_format($total_expenses, 2) ?></td>
                    </tr>
                    <?php if (!empty($exp_breakdown_rows)): ?>
                        <?php foreach ($exp_breakdown_rows as $eb): ?>
                            <tr>
                                <td class="ps-5 small text-muted">↳ <?= clean_input($eb['name']) ?> (<?= clean_input($eb['name_ur']) ?>)</td>
                                <td class="text-end small text-muted">- <?= number_format($eb['cat_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td class="fw-bold fs-5">Net Operating Profit / Loss (خالص منافع)</td>
                        <td class="text-end fw-bold fs-5 <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                            PKR <?= number_format($net_profit, 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($report_tab === 'balance_sheet'): ?>
    <!-- Balance Sheet Visual Charts (d-print-none) -->
    <div class="row g-3 mb-4 d-print-none">
        <!-- Asset Allocation Doughnut -->
        <div class="col-12 col-lg-6">
            <div class="card card-custom h-100 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="bi bi-pie-chart text-primary me-2"></i>Asset Allocation Breakdown
                    </span>
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-medium">
                        Total Assets: PKR <?= number_format($total_assets, 0) ?>
                    </span>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div style="position: relative; height: 240px; width: 100%;">
                        <canvas id="assetsAllocationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balanced Equality: Assets vs Liabilities & Equity -->
        <div class="col-12 col-lg-6">
            <div class="card card-custom h-100 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="bi bi-bar-chart text-success me-2"></i>Balanced Accounting Equality (100% Match)
                    </span>
                    <span class="badge bg-success bg-opacity-10 text-success fw-medium">
                        <i class="bi bi-check2-circle me-1"></i>Assets = Liabilities + Equity
                    </span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 240px; width: 100%;">
                        <canvas id="balanceEqualityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Balance Sheet (Assets = Liabilities + Equity) -->
    <div class="card card-custom p-4 bg-white printable-invoice">
        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><?= __('app_name') ?></h4>
                <div class="small text-muted">Balance Sheet Statement (ترازنامہ / بیلنس شیٹ)</div>
            </div>
            <div class="text-end">
                <div class="small text-muted">As on: <?= date('d M Y') ?></div>
                <div class="badge bg-success fs-6"><i class="bi bi-check-all me-1"></i> Balanced & Reconciled</div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Side: Assets -->
            <div class="col-12 col-md-6">
                <div class="p-3 bg-light rounded border h-100 d-flex flex-column">
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="bi bi-wallet2 me-1"></i> Current Assets (اثاثہ جات)
                    </h5>
                    
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr class="fw-semibold">
                                    <td colspan="2" class="text-dark">Cash & Bank Balances (نقد و بینک اکاؤنٹس):</td>
                                </tr>
                                <?php foreach ($bank_list as $bk): ?>
                                    <tr>
                                        <td class="ps-3 small text-muted"><?= clean_input($bk['name']) ?> (<?= strtoupper($bk['account_type']) ?>)</td>
                                        <td class="text-end font-monospace"><?= number_format($bk['balance'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="border-bottom">
                                    <td class="ps-3 fw-bold">Total Cash & Bank:</td>
                                    <td class="text-end fw-bold"><?= number_format($total_cash_bank, 2) ?></td>
                                </tr>

                                <tr class="fw-semibold">
                                    <td class="pt-3">Accounts Receivable (گاہکوں کے واجبات):</td>
                                    <td class="text-end pt-3 font-monospace fw-bold"><?= number_format($customer_receivables, 2) ?></td>
                                </tr>

                                <tr class="fw-semibold">
                                    <td class="pt-2">Inventory Stock Valuation (موجودہ مالیت اسٹاک):</td>
                                    <td class="text-end pt-2 font-monospace fw-bold"><?= number_format($inventory_valuation, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between p-3 bg-primary text-white rounded mt-3">
                        <span class="fw-bold fs-5">TOTAL ASSETS:</span>
                        <span class="fw-bold fs-5 font-monospace">PKR <?= number_format($total_assets, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Liabilities & Equity -->
            <div class="col-12 col-md-6">
                <div class="p-3 bg-light rounded border h-100 d-flex flex-column">
                    <h5 class="fw-bold text-danger border-bottom pb-2 mb-3">
                        <i class="bi bi-shield-exclamation me-1"></i> Liabilities & Equity (واجبات و سرمایہ)
                    </h5>

                    <div class="table-responsive flex-grow-1">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr class="fw-semibold text-danger">
                                    <td colspan="2">Current Liabilities (واجب الادا رقوم):</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-muted">Accounts Payable to Suppliers (سپلائرز کو ادائیگیاں):</td>
                                    <td class="text-end font-monospace text-danger fw-bold"><?= number_format($supplier_payables, 2) ?></td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="ps-3 fw-bold text-danger">Total Liabilities:</td>
                                    <td class="text-end fw-bold text-danger"><?= number_format($supplier_payables, 2) ?></td>
                                </tr>

                                <tr class="fw-semibold text-success pt-3">
                                    <td colspan="2" class="pt-3">Owner's Equity & Capital Fund (خالص سرمایہ):</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-muted">Retained Business Capital (کاروباری پونجی):</td>
                                    <td class="text-end font-monospace text-success fw-bold"><?= number_format($owner_equity, 2) ?></td>
                                </tr>
                                <tr class="border-bottom">
                                    <td class="ps-3 fw-bold text-success">Total Equity:</td>
                                    <td class="text-end fw-bold text-success"><?= number_format($owner_equity, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between p-3 bg-primary text-white rounded mt-3">
                        <span class="fw-bold fs-5">TOTAL LIABILITIES & EQUITY:</span>
                        <span class="fw-bold fs-5 font-monospace">PKR <?= number_format($total_liabilities_equity, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-success mt-4 mb-0 py-2 small d-flex align-items-center">
            <i class="bi bi-shield-check fs-5 me-2"></i>
            <span><strong>Accounting Balance Guarantee:</strong> Total Assets (PKR <?= number_format($total_assets, 2) ?>) = Total Liabilities & Owner Equity (PKR <?= number_format($total_liabilities_equity, 2) ?>) in exact matched state.</span>
        </div>
    </div>

<?php elseif ($report_tab === 'trial_balance'): ?>
    <!-- Trial Balance Sheet (Debit == Credit) -->
    <div class="card card-custom p-4 bg-white printable-invoice">
        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><?= __('app_name') ?></h4>
                <div class="small text-muted">Trial Balance Statement (میزان چٹھا)</div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Date: <?= date('d M Y') ?></div>
                <div class="badge bg-success fs-6"><i class="bi bi-check2-all me-1"></i> Debits Equal Credits</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Account Title</th>
                        <th>Classification</th>
                        <th class="text-end" style="width: 25%;">Debit (PKR)</th>
                        <th class="text-end" style="width: 25%;">Credit (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Cash & Bank accounts -->
                    <?php foreach ($bank_list as $bk): ?>
                        <tr>
                            <td><?= clean_input($bk['name']) ?></td>
                            <td><span class="badge bg-light text-dark border">Asset</span></td>
                            <td class="text-end font-monospace text-primary fw-semibold"><?= number_format($bk['balance'], 2) ?></td>
                            <td class="text-end font-monospace text-muted">-</td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Accounts Receivable -->
                    <tr>
                        <td>Trade Debtors (Customers Receivable)</td>
                        <td><span class="badge bg-light text-dark border">Asset</span></td>
                        <td class="text-end font-monospace text-primary fw-semibold"><?= number_format($customer_receivables, 2) ?></td>
                        <td class="text-end font-monospace text-muted">-</td>
                    </tr>

                    <!-- Stock Inventory -->
                    <tr>
                        <td>Merchandise Inventory (Warehouse Stock)</td>
                        <td><span class="badge bg-light text-dark border">Asset</span></td>
                        <td class="text-end font-monospace text-primary fw-semibold"><?= number_format($inventory_valuation, 2) ?></td>
                        <td class="text-end font-monospace text-muted">-</td>
                    </tr>

                    <!-- Accounts Payable -->
                    <tr>
                        <td>Trade Creditors (Suppliers Payable)</td>
                        <td><span class="badge bg-light text-dark border">Liability</span></td>
                        <td class="text-end font-monospace text-muted">-</td>
                        <td class="text-end font-monospace text-danger fw-semibold"><?= number_format($supplier_payables, 2) ?></td>
                    </tr>

                    <!-- Owner Equity -->
                    <tr>
                        <td>Owner's Capital Fund & Accumulated Reserves</td>
                        <td><span class="badge bg-light text-dark border">Equity</span></td>
                        <td class="text-end font-monospace text-muted">-</td>
                        <td class="text-end font-monospace text-success fw-semibold"><?= number_format($owner_equity, 2) ?></td>
                    </tr>

                    <tr class="table-dark fw-bold fs-5">
                        <td colspan="2">TOTAL BALANCED EQUALITY:</td>
                        <td class="text-end font-monospace text-info">PKR <?= number_format($total_assets, 2) ?></td>
                        <td class="text-end font-monospace text-info">PKR <?= number_format($total_liabilities_equity, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    // --- Tab 1: P&L Charts ---
    const ctxPnl = document.getElementById('pnlBreakdownChart');
    if (ctxPnl) {
        new Chart(ctxPnl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Net Sales', 'Cost of Goods', 'Gross Profit', 'Expenses', 'Net Profit'],
                datasets: [{
                    label: 'Amount (PKR)',
                    data: [
                        <?= round($sales_data['total_sales'], 2) ?>,
                        <?= round($cogs, 2) ?>,
                        <?= round($gross_profit, 2) ?>,
                        <?= round($total_expenses, 2) ?>,
                        <?= round($net_profit, 2) ?>
                    ],
                    backgroundColor: [
                        '#2563eb', // Sales (Blue)
                        '#f97316', // COGS (Orange)
                        '#059669', // Gross Profit (Emerald)
                        '#ef4444', // Expenses (Red)
                        '<?= $net_profit >= 0 ? '#10b981' : '#dc2626' ?>' // Net Profit (Green or Dark Red)
                    ],
                    borderRadius: 6,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' PKR ' + Number(ctx.raw).toLocaleString('en-PK', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                if (v >= 1000) return (v / 1000).toLocaleString() + 'k';
                                return v;
                            }
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    const ctxExp = document.getElementById('pnlExpensesChart');
    if (ctxExp) {
        new Chart(ctxExp.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($exp_cat_labels) ?>,
                datasets: [{
                    data: <?= json_encode($exp_cat_values) ?>,
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#6b7280'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 11 }, padding: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': PKR ' + Number(ctx.raw).toLocaleString('en-PK');
                            }
                        }
                    }
                }
            }
        });
    }

    // --- Tab 2: Balance Sheet Charts ---
    const ctxAsset = document.getElementById('assetsAllocationChart');
    if (ctxAsset) {
        new Chart(ctxAsset.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Cash & Bank', 'Accounts Receivable', 'Inventory Stock'],
                datasets: [{
                    data: [
                        <?= round($total_cash_bank, 2) ?>,
                        <?= round($customer_receivables, 2) ?>,
                        <?= round($inventory_valuation, 2) ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#2563eb'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 11 }, padding: 10 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': PKR ' + Number(ctx.raw).toLocaleString('en-PK');
                            }
                        }
                    }
                }
            }
        });
    }

    const ctxEquality = document.getElementById('balanceEqualityChart');
    if (ctxEquality) {
        new Chart(ctxEquality.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Total Assets', 'Liabilities + Owner Equity'],
                datasets: [{
                    label: 'Balance (PKR)',
                    data: [
                        <?= round($total_assets, 2) ?>,
                        <?= round($total_liabilities_equity, 2) ?>
                    ],
                    backgroundColor: ['#2563eb', '#10b981'],
                    borderRadius: 6,
                    barThickness: 60
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' PKR ' + Number(ctx.raw).toLocaleString('en-PK', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                if (v >= 1000) return (v / 1000).toLocaleString() + 'k';
                                return v;
                            }
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
