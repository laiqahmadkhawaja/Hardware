<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

check_auth();

$page_title = __('dashboard');
$today = date('Y-m-d');

// 1. Today's Gross Sales
$q1 = $conn->query("SELECT IFNULL(SUM(grand_total), 0) as today_sales FROM sales WHERE date = '$today'");
$today_sales = $q1->fetch_assoc()['today_sales'];

// 2. Today's Cash Collected (from payments today)
$q2 = $conn->query("SELECT IFNULL(SUM(amount), 0) as cash_collected FROM sale_payments WHERE date = '$today'");
$cash_collected = $q2->fetch_assoc()['cash_collected'];

// 3. Customer Receivables
$q3 = $conn->query("SELECT IFNULL(SUM(current_balance), 0) as receivables FROM customers WHERE current_balance > 0");
$receivables = $q3->fetch_assoc()['receivables'];

// 4. Supplier Payables
$q4 = $conn->query("SELECT IFNULL(SUM(current_balance), 0) as payables FROM suppliers WHERE current_balance > 0");
$payables = $q4->fetch_assoc()['payables'];

// 5. Total Inventory Valuation (sum of quantity * average_cost)
$q5 = $conn->query("SELECT IFNULL(SUM(quantity * average_cost), 0) as stock_val FROM stock_balances");
$stock_value = $q5->fetch_assoc()['stock_val'];

// 6. Low stock products count
$q6 = $conn->query("SELECT COUNT(*) as low_stock_cnt FROM products p 
                    JOIN stock_balances sb ON p.id = sb.product_id 
                    WHERE sb.quantity <= p.alert_quantity");
$low_stock_count = $q6->fetch_assoc()['low_stock_cnt'];

// 7. Recent Invoices
$recent_sales = $conn->query("SELECT s.*, c.name as customer_name, c.name_ur as customer_name_ur 
                              FROM sales s 
                              JOIN customers c ON s.customer_id = c.id 
                              ORDER BY s.id DESC LIMIT 5");

// 8. Low Stock Alert Items
$low_stock_items = $conn->query("SELECT p.*, sb.quantity as current_stock, u.name as unit_name, u.name_ur as unit_name_ur 
                                 FROM products p 
                                 JOIN stock_balances sb ON p.id = sb.product_id 
                                 JOIN units u ON p.base_unit_id = u.id 
                                 WHERE sb.quantity <= p.alert_quantity 
                                 ORDER BY sb.quantity ASC LIMIT 5");

// 9. Chart Analytics Data
$enable_charts = true;

// 7-Day Sales & Cash Collections
$start_7d = date('Y-m-d', strtotime('-6 days'));
$sales_by_day = [];
$q_sales = $conn->query("SELECT date, SUM(grand_total) as total FROM sales WHERE date >= '$start_7d' GROUP BY date");
if ($q_sales) {
    while ($r = $q_sales->fetch_assoc()) {
        $sales_by_day[$r['date']] = (float)$r['total'];
    }
}

$payments_by_day = [];
$q_pay = $conn->query("SELECT date, SUM(amount) as total FROM sale_payments WHERE date >= '$start_7d' GROUP BY date");
if ($q_pay) {
    while ($r = $q_pay->fetch_assoc()) {
        $payments_by_day[$r['date']] = (float)$r['total'];
    }
}

$chart_labels = [];
$chart_sales = [];
$chart_collections = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D, j M', strtotime($d));
    $chart_sales[] = $sales_by_day[$d] ?? 0;
    $chart_collections[] = $payments_by_day[$d] ?? 0;
}

// Category Stock Valuation
$cat_labels = [];
$cat_values = [];
$cat_q = $conn->query("SELECT c.name, c.name_ur, IFNULL(SUM(sb.quantity * sb.average_cost), 0) as cat_val 
                       FROM categories c 
                       JOIN products p ON c.id = p.category_id 
                       JOIN stock_balances sb ON p.id = sb.product_id 
                       GROUP BY c.id 
                       HAVING cat_val > 0 
                       ORDER BY cat_val DESC LIMIT 6");
if ($cat_q) {
    while ($c = $cat_q->fetch_assoc()) {
        $cat_labels[] = is_rtl() && !empty($c['name_ur']) ? $c['name_ur'] : $c['name'];
        $cat_values[] = round((float)$c['cat_val'], 2);
    }
}
if (empty($cat_labels)) {
    $cat_labels = [__('all')];
    $cat_values = [$stock_value > 0 ? round($stock_value, 2) : 100];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><?= __('dashboard') ?></h4>
        <p class="text-muted small mb-0"><?= __('welcome') ?>, <?= clean_input($_SESSION['full_name'] ?? 'User') ?> | <?= date('D, d M Y') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/pos.php" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-cart-plus me-1"></i><?= __('quick_pos') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/purchases/new.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-bag-plus me-1"></i><?= __('new_purchase') ?>
        </a>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="row g-3 mb-4">
    <!-- Today's Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('today_sales') ?></div>
                <div class="stat-value"><?= format_currency($today_sales) ?></div>
            </div>
        </div>
    </div>

    <!-- Cash Collected -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-wallet-fill"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('cash_collected') ?></div>
                <div class="stat-value"><?= format_currency($cash_collected) ?></div>
            </div>
        </div>
    </div>

    <!-- Customer Receivables -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-person-exclamation"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('receivables') ?></div>
                <div class="stat-value text-danger"><?= format_currency($receivables) ?></div>
            </div>
        </div>
    </div>

    <!-- Supplier Payables -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-truck-front-fill"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('payables') ?></div>
                <div class="stat-value"><?= format_currency($payables) ?></div>
            </div>
        </div>
    </div>

    <!-- Inventory Valuation -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-box-fill"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('stock_value') ?></div>
                <div class="stat-value"><?= format_currency($stock_value) ?></div>
            </div>
        </div>
    </div>

    <!-- Low Stock Count -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-exclamation-octagon"></i>
            </div>
            <div>
                <div class="stat-title"><?= __('low_stock') ?></div>
                <div class="stat-value text-danger"><?= $low_stock_count ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Visual Analytics & Charts Section -->
<div class="row g-4 mb-4">
    <!-- 7-Day Sales & Cash Collection Trend -->
    <div class="col-12 col-lg-8">
        <div class="card card-custom h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="fw-semibold">
                    <i class="bi bi-graph-up-arrow text-primary me-2"></i><?= __('sales_trend') ?>
                </span>
                <span class="badge bg-primary bg-opacity-10 text-primary fw-medium px-2 py-1">
                    <i class="bi bi-calendar3 me-1"></i>Last 7 Days
                </span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Inventory Valuation Breakdown -->
    <div class="col-12 col-lg-4">
        <div class="card card-custom h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="fw-semibold">
                    <i class="bi bi-pie-chart-fill text-success me-2"></i><?= __('category_distribution') ?>
                </span>
                <span class="badge bg-success bg-opacity-10 text-success fw-medium px-2 py-1">
                    <?= count($cat_labels) ?> Categories
                </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div style="position: relative; height: 230px; width: 100%;">
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="text-center pt-2 border-top mt-2 small text-muted">
                    Total Stock Value: <strong class="text-dark"><?= format_currency($stock_value) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Sales Invoices -->
    <div class="col-12 col-lg-8">
        <div class="card card-custom">
            <div class="card-header">
                <span><i class="bi bi-receipt me-2"></i><?= __('recent_sales') ?></span>
                <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-sm btn-link text-decoration-none"><?= __('view') ?> <?= __('all') ?></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-custom mb-0">
                    <thead>
                        <tr>
                            <th><?= __('invoice_no') ?></th>
                            <th><?= __('customer') ?></th>
                            <th><?= __('total') ?></th>
                            <th><?= __('paid') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('action') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_sales->num_rows > 0): ?>
                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-semibold text-primary"><?= clean_input($sale['invoice_no']) ?></td>
                                    <td><?= is_rtl() && !empty($sale['customer_name_ur']) ? clean_input($sale['customer_name_ur']) : clean_input($sale['customer_name']) ?></td>
                                    <td class="fw-bold"><?= format_currency($sale['grand_total']) ?></td>
                                    <td class="text-success"><?= format_currency($sale['paid_amount']) ?></td>
                                    <td>
                                        <?php if ($sale['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success"><?= __('paid') ?></span>
                                        <?php elseif ($sale['payment_status'] === 'partial'): ?>
                                            <span class="badge bg-warning text-dark"><?= __('partial') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= __('due') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/sales/view.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-light border" title="<?= __('view') ?>">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4"><?= __('no_records') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Warning Panel -->
    <div class="col-12 col-lg-4">
        <div class="card card-custom">
            <div class="card-header text-danger">
                <span><i class="bi bi-exclamation-triangle-fill me-2"></i><?= __('low_stock_alert') ?></span>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-sm btn-link text-danger text-decoration-none"><?= __('inventory') ?></a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if ($low_stock_items->num_rows > 0): ?>
                        <?php while ($prod = $low_stock_items->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-0 fw-semibold">
                                        <?= is_rtl() && !empty($prod['name_ur']) ? clean_input($prod['name_ur']) : clean_input($prod['name']) ?>
                                    </h6>
                                    <small class="text-muted font-monospace"><?= clean_input($prod['code']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger">
                                        <?= format_qty($prod['current_stock']) ?> <?= is_rtl() && !empty($prod['unit_name_ur']) ? clean_input($prod['unit_name_ur']) : clean_input($prod['unit_name']) ?>
                                    </span>
                                    <div class="small text-muted mt-1">Alert: <?= format_qty($prod['alert_quantity']) ?></div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-check-circle fs-3 text-success d-block mb-1"></i>
                            All inventory levels healthy!
                        </div>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Sales & Cash Collection Trend Chart
    const ctxTrend = document.getElementById('salesTrendChart');
    if (ctxTrend && typeof Chart !== 'undefined') {
        new Chart(ctxTrend.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Gross Sales (PKR)',
                        data: <?= json_encode($chart_sales) ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 1.5,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Cash Inflow (PKR)',
                        data: <?= json_encode($chart_collections) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        borderDash: [5, 4],
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 1.5,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            usePointStyle: true,
                            font: { family: 'inherit', size: 12, weight: 500 },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 10,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ' + Number(context.raw).toLocaleString('en-PK', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000) {
                                    return (value / 1000).toLocaleString() + 'k';
                                }
                                return value;
                            },
                            font: { size: 11 }
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11 }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 2. Category Inventory Valuation Doughnut Chart
    const ctxCat = document.getElementById('categoryChart');
    if (ctxCat && typeof Chart !== 'undefined') {
        new Chart(ctxCat.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($cat_labels) ?>,
                datasets: [{
                    data: <?= json_encode($cat_values) ?>,
                    backgroundColor: [
                        '#2563eb', // Steel / Blue
                        '#10b981', // Cement / Green
                        '#f59e0b', // Paints / Amber
                        '#8b5cf6', // Tools / Purple
                        '#ec4899', // Fasteners / Pink
                        '#06b6d4'  // Sanitary / Cyan
                    ],
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
                        labels: {
                            boxWidth: 10,
                            font: { family: 'inherit', size: 11 },
                            padding: 8
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': PKR ' + Number(context.raw).toLocaleString('en-PK');
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
