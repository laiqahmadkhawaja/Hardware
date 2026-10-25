<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/language.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT u.*, r.name as role_slug, r.display_name as role_name, r.permissions 
                                FROM users u 
                                JOIN roles r ON u.role_id = r.id 
                                WHERE u.username = ? AND u.status = 'active' LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Successful Login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_slug'] = $user['role_slug'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['permissions'] = $user['permissions'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['lang'] = $user['language'] ?? 'en';

                log_audit($conn, 'LOGIN', 'USER-' . $user['id'], 'User logged in successfully');

                header("Location: " . BASE_URL . "/dashboard.php");
                exit;
            } else {
                $error = 'Invalid password credentials.';
            }
        } else {
            $error = 'User not found or inactive account.';
        }
    }
}

$page_title = __('login');
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center min-vh-100 bg-light py-5">
    <div class="card shadow-sm border-0" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 54px; height: 54px;">
                    <i class="bi bi-tools fs-3"></i>
                </div>
                <h4 class="fw-bold mb-1"><?= __('app_name') ?></h4>
                <p class="text-muted small"><?= __('app_tagline') ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= clean_input($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label small fw-semibold"><?= __('username') ?></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="admin or cashier" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold"><?= __('password') ?></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i><?= __('sign_in') ?>
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <span class="text-muted small">Switch Portal Language: </span>
                <a href="?lang=en" class="text-decoration-none fw-semibold <?= get_current_lang() === 'en' ? 'text-primary' : 'text-muted' ?>">English</a>
                <span class="text-muted mx-1">|</span>
                <a href="?lang=ur" class="text-decoration-none fw-semibold <?= get_current_lang() === 'ur' ? 'text-primary' : 'text-muted' ?>">اردو</a>
            </div>

            <div class="mt-3 text-center text-muted small bg-light p-2 rounded">
                Default Credentials: <strong>admin</strong> / <strong>admin123</strong>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
