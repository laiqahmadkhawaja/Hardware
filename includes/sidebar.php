<?php
$current_uri = $current_uri ?? '';
$current_self = $_SERVER['PHP_SELF'] ?? '';
?>
<aside class="sidebar d-print-none" id="appSidebar">
    <ul class="sidebar-menu">
        <li class="<?= basename($current_self) === 'dashboard.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span><?= __('dashboard') ?></span>
            </a>
        </li>

        <li>
            <a href="<?= BASE_URL ?>/pos.php">
                <i class="bi bi-cart4 text-warning"></i>
                <span class="fw-bold"><?= __('pos') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/sales') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/sales/index.php">
                <i class="bi bi-receipt"></i>
                <span><?= __('sales_list') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/quotations') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/quotations/index.php">
                <i class="bi bi-file-earmark-text"></i>
                <span><?= __('quotations') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/products') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/products/index.php">
                <i class="bi bi-box-seam"></i>
                <span><?= __('products') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/inventory') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/inventory/index.php">
                <i class="bi bi-boxes"></i>
                <span><?= __('inventory') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/purchases') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/purchases/index.php">
                <i class="bi bi-bag-check"></i>
                <span><?= __('purchases') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/customers') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/customers/index.php">
                <i class="bi bi-people"></i>
                <span><?= __('customers') ?> (کھاتہ)</span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/suppliers') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/suppliers/index.php">
                <i class="bi bi-truck"></i>
                <span><?= __('suppliers') ?> (کھاتہ)</span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/expenses') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/expenses/index.php">
                <i class="bi bi-wallet2"></i>
                <span><?= __('expenses') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/accounts') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/accounts/index.php">
                <i class="bi bi-bank"></i>
                <span>Bank Accounts (بینک اکاؤنٹس)</span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/reports') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/reports/index.php">
                <i class="bi bi-bar-chart-line"></i>
                <span><?= __('reports') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/staff') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/staff/index.php">
                <i class="bi bi-shield-lock"></i>
                <span><?= __('staff') ?></span>
            </a>
        </li>

        <li class="<?= strpos($current_uri, '/modules/settings') !== false ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/settings/index.php">
                <i class="bi bi-gear"></i>
                <span><?= __('settings') ?></span>
            </a>
        </li>
    </ul>
</aside>
