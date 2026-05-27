<?php
// ============================================
// LSPUFundex - User Management
// File: admin/users.php
// Location: C:\xampp\htdocs\LSPUFundex\admin\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'User Management';
$activePage = 'users';
$error      = '';

// ============================================
// HANDLE FORM ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ----------------------------------------
    // CREATE — Add new user
    // ----------------------------------------
    if ($action === 'create') {
        $full_name     = trim($_POST['full_name']     ?? '');
        $email         = trim($_POST['email']         ?? '');
        $username      = trim($_POST['username']      ?? '');
        $password      = $_POST['password']           ?? '';
        $role          = $_POST['role']               ?? 'officer';
        $section_id    = ($role === 'officer') ? (int)($_POST['section_id']    ?? 0) : null;
        $department_id = ($role === 'council') ? (int)($_POST['department_id'] ?? 0) : null;

        if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
            $error = 'Full name, email, username, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($role === 'officer' && $section_id <= 0) {
            $error = 'Please assign a section to this officer.';
        } elseif ($role === 'council' && $department_id <= 0) {
            $error = 'Please assign a department to this council officer.';
        } else {
            $chkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $chkUser->bind_param("s", $username);
            $chkUser->execute();
            $chkUser->store_result();

            $chkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $chkEmail->bind_param("s", $email);
            $chkEmail->execute();
            $chkEmail->store_result();

            if ($chkUser->num_rows > 0) {
                $error = "Username '{$username}' is already taken.";
            } elseif ($chkEmail->num_rows > 0) {
                $error = "Email '{$email}' is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conn->prepare(
                    "INSERT INTO users
                        (full_name, email, username, password, role, section_id, department_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "sssssii",
                    $full_name, $email, $username,
                    $hashedPassword, $role, $section_id, $department_id
                );

                if ($stmt->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'CREATE', 'Users',
                        "Created user: {$full_name} ({$role})");
                    setFlash('success', "User '{$full_name}' created successfully!");
                } else {
                    setFlash('error', 'Failed to create user. Please try again.');
                }
            }
        }
    }

    // ----------------------------------------
    // UPDATE — Edit user details
    // ----------------------------------------
    if ($action === 'update') {
        $id            = (int)($_POST['id']            ?? 0);
        $full_name     = trim($_POST['full_name']      ?? '');
        $email         = trim($_POST['email']          ?? '');
        $username      = trim($_POST['username']       ?? '');
        $role          = $_POST['role']                ?? 'officer';
        $is_active     = isset($_POST['is_active'])    ? 1 : 0;
        $section_id    = ($role === 'officer') ? (int)($_POST['section_id']    ?? 0) : null;
        $department_id = ($role === 'council') ? (int)($_POST['department_id'] ?? 0) : null;

        if ($id <= 0 || empty($full_name) || empty($email) || empty($username)) {
            $error = 'All required fields must be filled.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($role === 'officer' && $section_id <= 0) {
            $error = 'Please assign a section to this officer.';
        } elseif ($role === 'council' && $department_id <= 0) {
            $error = 'Please assign a department to this council officer.';
        } else {
            $chkUser = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chkUser->bind_param("si", $username, $id);
            $chkUser->execute();
            $chkUser->store_result();

            $chkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chkEmail->bind_param("si", $email, $id);
            $chkEmail->execute();
            $chkEmail->store_result();

            if ($chkUser->num_rows > 0) {
                $error = "Username '{$username}' is already taken.";
            } elseif ($chkEmail->num_rows > 0) {
                $error = "Email '{$email}' is already registered.";
            } else {
                // FIX: bind nullable ints via variables so MySQLi handles NULL correctly
                $stmt = $conn->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, username = ?,
                         role = ?, section_id = ?, department_id = ?, is_active = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    "ssssiiii",
                    $full_name, $email, $username,
                    $role, $section_id, $department_id, $is_active, $id
                );

                if ($stmt->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Users',
                        "Updated user: {$full_name} (ID: {$id})");
                    setFlash('success', "User '{$full_name}' updated successfully!");
                } else {
                    setFlash('error', 'Failed to update user. Error: ' . $stmt->error);
                }
            }
        }
    }

    // ----------------------------------------
    // RESET PASSWORD
    // ----------------------------------------
    if ($action === 'reset_password') {
        $id          = (int)($_POST['id']      ?? 0);
        $newPassword = $_POST['new_password']  ?? '';

        if ($id <= 0 || strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt   = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $id);

            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'RESET_PASSWORD', 'Users',
                    "Reset password for user ID: {$id}");
                setFlash('success', 'Password reset successfully!');
            } else {
                setFlash('error', 'Failed to reset password.');
            }
        }
    }

    // ----------------------------------------
    // DELETE
    // ----------------------------------------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id === (int)$_SESSION['user_id']) {
            setFlash('error', 'You cannot delete your own account.');
        } elseif ($id <= 0) {
            setFlash('error', 'Invalid user selected.');
        } else {
            $user = getById($conn, 'users', $id);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'DELETE', 'Users',
                    "Deleted user: {$user['full_name']}");
                setFlash('success', "User '{$user['full_name']}' deleted.");
            } else {
                setFlash('error', 'Failed to delete user.');
            }
        }
    }

    if (empty($error)) {
        redirect(BASE_URL . 'admin/users.php');
    }
}

// ============================================
// READ — Fetch all users with section info
// ============================================
$users = $conn->query(
    "SELECT u.*,
            s.name  AS section_name,
            d.code  AS dept_code,
            yl.name AS year_level_name,
            dep.name AS department_name,
            dep.code AS department_code
     FROM users u
     LEFT JOIN sections    s   ON s.id   = u.section_id
     LEFT JOIN departments d   ON d.id   = s.department_id
     LEFT JOIN year_levels yl  ON yl.id  = s.year_level_id
     LEFT JOIN departments dep ON dep.id = u.department_id
     ORDER BY u.role ASC, u.full_name ASC"
);

$allSections = $conn->query(
    "SELECT s.id, s.name AS section_name, s.school_year,
            d.name AS dept_name, d.code AS dept_code,
            yl.name AS year_level_name
     FROM sections s
     JOIN departments d  ON d.id  = s.department_id
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.is_active = 1
     ORDER BY d.name ASC, yl.order_num ASC, s.name ASC"
);
$sectionList = $allSections->fetch_all(MYSQLI_ASSOC);

$allDepts = $conn->query(
    "SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC"
);
$deptList = $allDepts->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<!-- ============================================
     PAGE HEADER
     ============================================ -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-people me-2"></i>User Management</h1>
        <p>Manage admin, officer, and council accounts</p>
    </div>
    <button class="btn btn-lspu" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-person-plus me-2"></i>Add User
    </button>
</div>

<?php showFlash(); ?>

<?php if (!empty($error)): ?>
    <div class="alert-box error mb-3">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo clean($error); ?>
    </div>
<?php endif; ?>

<!-- ============================================
     USERS TABLE
     ============================================ -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>All Users</h5>
        <span class="badge bg-light text-dark">
            <?php echo $users->num_rows; ?> total
        </span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Assigned To</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users->num_rows === 0): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No users found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar"
                                     style="background:<?php
                                        if ($u['role'] === 'admin')        echo '#1b4f72';
                                        elseif ($u['role'] === 'council')  echo '#8e44ad';
                                        else                               echo '#27ae60';
                                     ?>">
                                    <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo clean($u['full_name']); ?></strong>
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-warning text-dark ms-1"
                                              style="font-size:10px;">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted small">
                            @<?php echo clean($u['username']); ?>
                        </td>
                        <td class="small"><?php echo clean($u['email']); ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="role-badge admin">
                                    <i class="bi bi-shield-fill me-1"></i>Admin
                                </span>
                            <?php elseif ($u['role'] === 'council'): ?>
                                <span class="role-badge council">
                                    <i class="bi bi-building me-1"></i>Council
                                </span>
                            <?php else: ?>
                                <span class="role-badge officer">
                                    <i class="bi bi-person-badge me-1"></i>Officer
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'council' && $u['department_name']): ?>
                                <span class="badge-balance">
                                    <?php echo clean($u['department_code']); ?>
                                </span>
                                <span class="small ms-1">
                                    <?php echo clean($u['department_name']); ?>
                                </span>
                            <?php elseif ($u['section_name']): ?>
                                <span class="badge-balance">
                                    <?php echo clean($u['dept_code']); ?>
                                </span>
                                <span class="small ms-1">
                                    <?php echo clean($u['section_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">— System Wide —</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo $u['last_login']
                                ? formatDateTime($u['last_login'])
                                : 'Never'; ?>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge-fund">Active</span>
                            <?php else: ?>
                                <span class="badge-expense">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $u['id']; ?>,
                                        '<?php echo addslashes(clean($u['full_name'])); ?>',
                                        '<?php echo addslashes(clean($u['email'])); ?>',
                                        '<?php echo addslashes(clean($u['username'])); ?>',
                                        '<?php echo $u['role']; ?>',
                                        <?php echo $u['section_id']    ?? 0; ?>,
                                        <?php echo $u['department_id'] ?? 0; ?>,
                                        <?php echo $u['is_active']; ?>
                                    )"
                                    title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Reset Password -->
                            <button class="btn btn-sm btn-outline-warning me-1"
                                    onclick="openResetModal(<?php echo $u['id']; ?>,
                                        '<?php echo addslashes(clean($u['full_name'])); ?>')"
                                    title="Reset Password">
                                <i class="bi bi-key"></i>
                            </button>

                            <!-- Delete -->
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?php echo $u['id']; ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete <?php echo addslashes(clean($u['full_name'])); ?>? This cannot be undone.')"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ============================================
     ADD USER MODAL
     ============================================ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control"
                                   placeholder="e.g. Juan Dela Cruz" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="e.g. juan@lspu.edu.ph" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Username <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="username" class="form-control"
                                   placeholder="e.g. juan.delacruz" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" name="password"
                                       id="addPassword" class="form-control"
                                       placeholder="Min. 8 characters" required>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePass('addPassword', 'addEye')">
                                    <i class="bi bi-eye" id="addEye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select name="role" id="addRole" class="form-select" required
                                    onchange="toggleSectionField('add')">
                                <option value="officer">Class Officer</option>
                                <option value="council">Council Officer</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <!-- Section wrapper — shown for Officer -->
                        <div class="col-md-6" id="addSectionWrapper">
                            <label class="form-label">
                                Assign Section <span class="text-danger">*</span>
                            </label>
                            <select name="section_id" class="form-select">
                                <option value="">— Select Section —</option>
                                <?php foreach ($sectionList as $sec): ?>
                                    <option value="<?php echo $sec['id']; ?>">
                                        <?php echo clean($sec['dept_code']); ?>
                                        — <?php echo clean($sec['year_level_name']); ?>
                                        — <?php echo clean($sec['section_name']); ?>
                                        (<?php echo clean($sec['school_year']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Department wrapper — shown for Council -->
                        <div class="col-md-6" id="addDeptWrapper" style="display:none;">
                            <label class="form-label">
                                Assign Department <span class="text-danger">*</span>
                            </label>
                            <select name="department_id" class="form-select">
                                <option value="">— Select Department —</option>
                                <?php foreach ($deptList as $d): ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        <?php echo clean($d['name']); ?>
                                        (<?php echo clean($d['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     EDIT USER MODAL
     ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"     id="editId">
                <div class="modal-body lspu-form p-4">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="editFullName"
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="editEmail"
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="editUsername"
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" id="editRole" class="form-select"
                                    onchange="toggleSectionField('edit')">
                                <option value="officer">Class Officer</option>
                                <option value="council">Council Officer</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <!-- Section wrapper — shown for Officer -->
                        <div class="col-md-6" id="editSectionWrapper">
                            <label class="form-label">Assigned Section</label>
                            <select name="section_id" id="editSectionId"
                                    class="form-select">
                                <option value="">— Select Section —</option>
                                <?php foreach ($sectionList as $sec): ?>
                                    <option value="<?php echo $sec['id']; ?>">
                                        <?php echo clean($sec['dept_code']); ?>
                                        — <?php echo clean($sec['year_level_name']); ?>
                                        — <?php echo clean($sec['section_name']); ?>
                                        (<?php echo clean($sec['school_year']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Department wrapper — shown for Council -->
                        <div class="col-md-6" id="editDeptWrapper" style="display:none;">
                            <label class="form-label">Assigned Department</label>
                            <select name="department_id" id="editDeptId"
                                    class="form-select">
                                <option value="">— Select Department —</option>
                                <?php foreach ($deptList as $d): ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        <?php echo clean($d['name']); ?>
                                        (<?php echo clean($d['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox"
                                       name="is_active" id="editIsActive" value="1">
                                <label class="form-check-label fw-semibold"
                                       for="editIsActive">Account Active</label>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     RESET PASSWORD MODAL
     ============================================ -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header"
                 style="background:linear-gradient(90deg,#7d6608,#d4ac0d);">
                <h5 class="modal-title">
                    <i class="bi bi-key me-2"></i>Reset Password
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token"  value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action"      value="reset_password">
                <input type="hidden" name="id"          id="resetUserId">
                <div class="modal-body lspu-form p-4">
                    <p class="text-muted mb-3">
                        Resetting password for:
                        <strong id="resetUserName" class="text-dark"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label">
                            New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="new_password"
                                   id="resetPassword" class="form-control"
                                   placeholder="Min. 8 characters" required>
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePass('resetPassword','resetEye')">
                                <i class="bi bi-eye" id="resetEye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-3 rounded"
                         style="background:#fff3cd; border:1px solid #ffc107; font-size:13px;">
                        <i class="bi bi-exclamation-triangle me-1 text-warning"></i>
                        <strong>Important:</strong> Notify the user of their new password
                        after resetting.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script>
function togglePass(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (f.type === 'password') {
        f.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        i.className = 'bi bi-eye';
    }
}

function toggleSectionField(prefix) {
    const role        = document.getElementById(prefix + 'Role').value;
    const secWrapper  = document.getElementById(prefix + 'SectionWrapper');
    const deptWrapper = document.getElementById(prefix + 'DeptWrapper');

    if (secWrapper)  secWrapper.style.display  = (role === 'officer') ? '' : 'none';
    if (deptWrapper) deptWrapper.style.display = (role === 'council') ? '' : 'none';
}

// Set initial state on page load
toggleSectionField('add');

function openEditModal(id, fullName, email, username, role, sectionId, departmentId, isActive) {
    document.getElementById('editId').value         = id;
    document.getElementById('editFullName').value   = fullName;
    document.getElementById('editEmail').value      = email;
    document.getElementById('editUsername').value   = username;
    document.getElementById('editIsActive').checked = isActive == 1;

    // Set role dropdown
    const roleSelect = document.getElementById('editRole');
    for (let opt of roleSelect.options) {
        opt.selected = opt.value === role;
    }

    // Set section dropdown
    const secSelect = document.getElementById('editSectionId');
    for (let opt of secSelect.options) {
        opt.selected = opt.value == sectionId;
    }

    // Set department dropdown
    const deptSelect = document.getElementById('editDeptId');
    for (let opt of deptSelect.options) {
        opt.selected = opt.value == departmentId;
    }

    // Show/hide correct fields based on role
    toggleSectionField('edit');

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openResetModal(id, fullName) {
    document.getElementById('resetUserId').value         = id;
    document.getElementById('resetUserName').textContent = fullName;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>