<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle  = 'My Profile';
$activePage = 'profile';
$error      = '';

$userId    = (int)$_SESSION['user_id'];
$sectionId = (int)($_SESSION['section_id'] ?? 0);

// Get full user info
$stmt = $conn->prepare(
    "SELECT u.*, s.name AS section_name,
            d.name AS dept_name, d.code AS dept_code,
            yl.name AS year_level_name
     FROM users u
     LEFT JOIN sections    s  ON s.id  = u.section_id
     LEFT JOIN departments d  ON d.id  = s.department_id
     LEFT JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE u.id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Activity summary
$fundCount = $conn->prepare(
    "SELECT COUNT(*) AS c FROM funds WHERE added_by = ?"
);
$fundCount->bind_param("i", $userId);
$fundCount->execute();
$myFundCount = $fundCount->get_result()->fetch_assoc()['c'];

$expCount = $conn->prepare(
    "SELECT COUNT(*) AS c FROM expenses WHERE added_by = ?"
);
$expCount->bind_param("i", $userId);
$expCount->execute();
$myExpCount = $expCount->get_result()->fetch_assoc()['c'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($currentPass, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match.';
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $userId);

        if ($stmt->execute()) {
            logAction($conn, $userId, 'RESET_PASSWORD', 'Profile',
                'User changed their own password.');
            setFlash('success', 'Password changed successfully!');
            redirect(BASE_URL . 'officer/profile.php');
        } else {
            $error = 'Failed to update password.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-person-circle me-2"></i>My Profile</h1>
        <p>View your account details and change your password</p>
    </div>
</div>

<?php showFlash(); ?>

<div class="row g-4">

    <!-- Profile Info -->
    <div class="col-lg-5">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-person me-2"></i>Account Information</h5>
            </div>
            <div class="lspu-card-body">

                <!-- Avatar -->
                <div class="text-center mb-4">
                    <div class="profile-avatar mx-auto">
                        <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
                    </div>
                    <h5 class="mt-3 mb-1"><?php echo clean($user['full_name']); ?></h5>
                    <span class="role-badge <?php echo $user['role']; ?>">
                        <i class="bi bi-<?php echo $user['role']==='admin'
                            ? 'shield-fill':'person-badge'; ?> me-1"></i>
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>

                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted small fw-semibold" style="width:35%">Username</td>
                        <td class="small">@<?php echo clean($user['username']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small fw-semibold">Email</td>
                        <td class="small"><?php echo clean($user['email']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small fw-semibold">Section</td>
                        <td class="small">
                            <?php if ($user['section_name']): ?>
                                <span class="badge-balance"><?php echo clean($user['dept_code']); ?></span>
                                <?php echo clean($user['section_name']); ?>
                            <?php else: ?>
                                <span class="text-muted">All Sections</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted small fw-semibold">Last Login</td>
                        <td class="small">
                            <?php echo $user['last_login']
                                ? formatDateTime($user['last_login']) : 'N/A'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted small fw-semibold">Member Since</td>
                        <td class="small"><?php echo formatDate($user['created_at']); ?></td>
                    </tr>
                </table>

                <!-- Activity Summary -->
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="text-center p-3 rounded"
                             style="background:#eafaf1; border:1px solid #a9dfbf;">
                            <div class="fw-bold fs-4 text-success"><?php echo $myFundCount; ?></div>
                            <small class="text-muted">Funds Added</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 rounded"
                             style="background:#fdedec; border:1px solid #f5b7b1;">
                            <div class="fw-bold fs-4 text-danger"><?php echo $myExpCount; ?></div>
                            <small class="text-muted">Expenses Added</small>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="col-lg-7">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-key me-2"></i>Change Password</h5>
            </div>
            <div class="lspu-card-body">

                <?php if (!empty($error)): ?>
                    <div class="alert-box error mb-3">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php echo clean($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="lspu-form">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password"
                               class="form-control"
                               placeholder="Min. 8 characters" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password"
                               class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Update Password
                    </button>
                </form>

            </div>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>