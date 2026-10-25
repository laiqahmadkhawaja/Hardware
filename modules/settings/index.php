<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('settings');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = clean_input($_POST['business_name']);
    $business_name_ur = clean_input($_POST['business_name_ur']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $receipt_footer = clean_input($_POST['receipt_footer']);

    $stmt = $conn->prepare("UPDATE businesses SET name = ?, name_ur = ?, phone = ?, address = ? WHERE id = 1");
    $stmt->bind_param("ssss", $business_name, $business_name_ur, $phone, $address);
    $stmt->execute();

    $stmt_set = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'receipt_footer'");
    $stmt_set->bind_param("s", $receipt_footer);
    $stmt_set->execute();

    log_audit($conn, 'UPDATE_SETTINGS', 'BIZ-1', 'Updated business profile settings');
    $msg = 'Settings saved successfully!';
}

$biz = $conn->query("SELECT * FROM businesses WHERE id = 1")->fetch_assoc();
$footer_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'receipt_footer'")->fetch_assoc();
$receipt_footer = $footer_res['setting_value'] ?? '';

$page_title = __('settings');
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('settings') ?> & Business Profile</h4>
        <small class="text-muted">Configure store identity, thermal print footers & localized business parameters</small>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small"><?= clean_input($msg) ?></div>
<?php endif; ?>

<div class="card card-custom" style="max-width: 700px;">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Business Name (English) *</label>
                    <input type="text" name="business_name" class="form-control" value="<?= clean_input($biz['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Business Name (Urdu)</label>
                    <input type="text" name="business_name_ur" class="form-control text-end" value="<?= clean_input($biz['name_ur']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Contact Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= clean_input($biz['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Currency</label>
                    <input type="text" class="form-control" value="PKR (Pakistani Rupee)" disabled>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Store & Yard Address</label>
                    <input type="text" name="address" class="form-control" value="<?= clean_input($biz['address']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold">Thermal Receipt & Invoice Footer Terms</label>
                    <textarea name="receipt_footer" class="form-control" rows="3"><?= clean_input($receipt_footer) ?></textarea>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
