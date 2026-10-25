<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$is_rtl = is_rtl();
$lang = get_current_lang();
$page_title = $page_title ?? __('app_name');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $is_rtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean_input($page_title) ?> - <?= __('app_name') ?></title>
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <?php if ($is_rtl): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/rtl.css">
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($enable_charts) && $enable_charts): ?>
        <script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>
    <?php endif; ?>
</head>
<body class="<?= isset($_SESSION['user_id']) ? 'app-layout' : '' ?>">
<?php if (isset($_SESSION['user_id'])): ?>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="app-wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="main-content">
            <?php 
            $flash = get_flash();
            if ($flash): 
            ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= clean_input($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
<?php endif; ?>
