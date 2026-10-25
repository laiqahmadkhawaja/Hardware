<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

check_auth();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean_input($_POST['full_name']);
    $full_name_ur = clean_input($_POST['full_name_ur']);
    $phone = clean_input($_POST['phone']);
    $password = $_POST['password'] ?? '';

    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $up = $conn->prepare("UPDATE users SET full_name = ?, full_name_ur = ?, phone = ?, password = ? WHERE id = ?");
        $up->bind_param("ssssi", $full_name, $full_name_ur, $phone, $hash, $user_id);
    } else {
        $up = $conn->prepare("UPDATE users SET full_name = ?, full_name_ur = ?, phone = ? WHERE id = ?");
        $up->bind_param("sssi", $full_name, $full_name_ur, $phone, $user_id);
    }

    if ($up->execute()) {
        $_SESSION['full_name'] = $full_name;
        $msg = 'Profile updated successfully!';
    } else {
        $err = 'Error updating profile.';
    }
}

$page_title = __('profile');
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">User Profile</h4>
        <small class="text-muted">Manage your personal account credentials and details</small>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small"><?= clean_input($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($err) ?></div>
<?php endif; ?>

<div class="card card-custom" style="max-width: 600px;">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" class="form-control" value="<?= clean_input($user['username']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Role</label>
                    <input type="text" class="form-control" value="<?= clean_input($_SESSION['role_name'] ?? 'User') ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Full Name (English) *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= clean_input($user['full_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Full Name (Urdu)</label>
                    <input type="text" name="full_name_ur" class="form-control text-end" value="<?= clean_input($user['full_name_ur']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= clean_input($user['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Change Password (leave blank to keep)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
