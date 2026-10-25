<header class="topbar d-print-none">
    <div class="d-flex align-items-center">
        <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none text-dark fw-bold fs-5" href="<?= BASE_URL ?>/dashboard.php">
            <span class="badge bg-primary px-2 py-1"><i class="bi bi-tools"></i></span>
            <span><?= __('app_name') ?></span>
        </a>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Quick POS Button -->
        <a href="<?= BASE_URL ?>/pos.php" class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-cart4"></i>
            <span class="d-none d-sm-inline"><?= __('quick_pos') ?></span>
        </a>

        <!-- Language Switcher -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-translate me-1"></i>
                <?= get_current_lang() === 'ur' ? 'اردو' : 'English' ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item <?= get_current_lang() === 'en' ? 'active' : '' ?>" href="?lang=en">English</a></li>
                <li><a class="dropdown-item <?= get_current_lang() === 'ur' ? 'active' : '' ?>" href="?lang=ur">اردو (Urdu)</a></li>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light border d-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-6"></i>
                <span class="d-none d-md-inline fw-semibold"><?= clean_input($_SESSION['full_name'] ?? 'User') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header"><?= clean_input($_SESSION['role_name'] ?? 'Role') ?></h6></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person me-2"></i><?= __('profile') ?></a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/settings/index.php"><i class="bi bi-gear me-2"></i><?= __('settings') ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= __('logout') ?></a></li>
            </ul>
        </div>
    </div>
</header>
