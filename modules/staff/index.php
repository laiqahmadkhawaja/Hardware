<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('staff');

$error = '';

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $full_name = clean_input($_POST['full_name']);
    $full_name_ur = clean_input($_POST['full_name_ur'] ?? '');
    $username = clean_input($_POST['username']);
    $password = $_POST['password'] ?? '';
    $phone = clean_input($_POST['phone'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $role_id = (int)$_POST['role_id'];
    $language = clean_input($_POST['language'] ?? 'en');

    if (empty($full_name) || empty($username) || empty($password) || empty($role_id)) {
        $error = 'Full name, username, password and role are required.';
    } else {
        // Check duplicate username
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username '$username' already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, full_name_ur, username, password, phone, email, role_id, language, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssssssis", $full_name, $full_name_ur, $username, $hash, $phone, $email, $role_id, $language);
            if ($stmt->execute()) {
                set_flash('success', "User '$username' registered successfully.");
                header("Location: " . BASE_URL . "/modules/staff/index.php");
                exit;
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        }
    }
}

// Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $user_id = (int)$_POST['user_id'];
    $full_name = clean_input($_POST['full_name']);
    $full_name_ur = clean_input($_POST['full_name_ur'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $role_id = (int)$_POST['role_id'];
    $language = clean_input($_POST['language'] ?? 'en');
    $status = clean_input($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';

    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, full_name_ur = ?, password = ?, phone = ?, email = ?, role_id = ?, language = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssissi", $full_name, $full_name_ur, $hash, $phone, $email, $role_id, $language, $status, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, full_name_ur = ?, phone = ?, email = ?, role_id = ?, language = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssisi", $full_name, $full_name_ur, $phone, $email, $role_id, $language, $status, $user_id);
    }

    if ($stmt->execute()) {
        set_flash('success', 'User profile updated successfully.');
        header("Location: " . BASE_URL . "/modules/staff/index.php");
        exit;
    } else {
        $error = 'Database error: ' . $conn->error;
    }
}

$page_title = __('staff') . ' & Roles';

$users = $conn->query("SELECT u.*, r.display_name as role_name, b.name as branch_name 
                       FROM users u 
                       JOIN roles r ON u.role_id = r.id 
                       LEFT JOIN branches b ON u.branch_id = b.id 
                       ORDER BY u.id ASC");

$roles = $conn->query("SELECT * FROM roles ORDER BY id ASC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('staff') ?> & User Management</h4>
        <small class="text-muted">Register staff accounts, cashiers, storekeepers, and manage operational permissions</small>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddUser">
        <i class="bi bi-person-plus-fill me-1"></i> Add New Staff Member
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Contact Phone</th>
                    <th>Language</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= clean_input($u['full_name']) ?>
                            <?php if (!empty($u['full_name_ur'])): ?>
                                <small class="text-muted d-block"><?= clean_input($u['full_name_ur']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace text-primary fw-semibold"><?= clean_input($u['username']) ?></td>
                        <td><span class="badge bg-primary"><?= clean_input($u['role_name']) ?></span></td>
                        <td><?= clean_input($u['phone'] ?: '-') ?></td>
                        <td><span class="badge bg-light text-dark border"><?= strtoupper($u['language']) ?></span></td>
                        <td>
                            <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-light border" onclick='editUser(<?= json_encode($u) ?>)' title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Staff Member -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-plus me-1 text-primary"></i> Add New Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Full Name (English) *</label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Ali Raza" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Full Name (Urdu)</label>
                            <input type="text" name="full_name_ur" class="form-control text-end" placeholder="مثال: علی رضا">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Username *</label>
                            <input type="text" name="username" class="form-control" placeholder="aliraza" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Password *</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Role *</label>
                            <select name="role_id" class="form-select" required>
                                <?php 
                                $roles->data_seek(0);
                                while ($r = $roles->fetch_assoc()): 
                                ?>
                                    <option value="<?= $r['id'] ?>"><?= clean_input($r['display_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="staff@hardware.com">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Default Language</label>
                            <select name="language" class="form-select">
                                <option value="en">English</option>
                                <option value="ur">اردو (Urdu)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Register User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Staff Member -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Username</label>
                            <input type="text" id="editUsername" class="form-control font-monospace" disabled>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Role *</label>
                            <select name="role_id" id="editRoleId" class="form-select" required>
                                <?php 
                                $roles->data_seek(0);
                                while ($r = $roles->fetch_assoc()): 
                                ?>
                                    <option value="<?= $r['id'] ?>"><?= clean_input($r['display_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Full Name (English) *</label>
                            <input type="text" name="full_name" id="editFullName" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Full Name (Urdu)</label>
                            <input type="text" name="full_name_ur" id="editFullNameUr" class="form-control text-end">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" id="editPhone" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">New Password (leave blank to keep unchanged)</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(u) {
    $('#editUserId').val(u.id);
    $('#editUsername').val(u.username);
    $('#editFullName').val(u.full_name);
    $('#editFullNameUr').val(u.full_name_ur || '');
    $('#editRoleId').val(u.role_id);
    $('#editPhone').val(u.phone || '');
    $('#editStatus').val(u.status);
    
    new bootstrap.Modal(document.getElementById('modalEditUser')).show();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
