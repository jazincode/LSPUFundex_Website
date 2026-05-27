<?php
// ============================================
// LSPUFundex - Departments Management
// File: admin/departments.php
// Location: C:\xampp\htdocs\LSPUFundex\admin\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only admins can access this page
requireAdmin();

$pageTitle  = 'Manage Departments';
$activePage = 'departments';
$error      = '';

// ============================================
// HANDLE FORM ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ----------------------------------------
    // CREATE — Add new department
    // ----------------------------------------
    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || empty($code)) {
            $error = 'Department name and code are required.';

        } else {
            // Check if code already exists
            $check = $conn->prepare("SELECT id FROM departments WHERE code = ?");
            $check->bind_param("s", $code);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Department code '{$code}' already exists.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO departments (name, code, description)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param("sss", $name, $code, $description);

                if ($stmt->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'CREATE', 'Departments',
                        "Added department: {$name} ({$code})");
                    setFlash('success', "Department '{$name}' added successfully!");
                } else {
                    setFlash('error', 'Failed to add department. Please try again.');
                }
            }
        }
    }

    // ----------------------------------------
    // UPDATE — Edit existing department
    // ----------------------------------------
    if ($action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($code) || $id <= 0) {
            $error = 'Department name and code are required.';
        } else {
            // Check code uniqueness — exclude current record
            $check = $conn->prepare(
                "SELECT id FROM departments WHERE code = ? AND id != ?"
            );
            $check->bind_param("si", $code, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Department code '{$code}' is already used by another department.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE departments
                     SET name = ?, code = ?, description = ?, is_active = ?
                     WHERE id = ?"
                );
                $stmt->bind_param("sssii", $name, $code, $description, $is_active, $id);

                if ($stmt->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Departments',
                        "Updated department: {$name} ({$code})");
                    setFlash('success', "Department '{$name}' updated successfully!");
                } else {
                    setFlash('error', 'Failed to update department. Please try again.');
                }
            }
        }
    }

    // ----------------------------------------
    // DELETE — Remove department
    // ----------------------------------------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            setFlash('error', 'Invalid department selected.');
        } else {
            // Get department name first for the log
            $dept = getById($conn, 'departments', $id);

            $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'DELETE', 'Departments',
                    "Deleted department: {$dept['name']} ({$dept['code']})");
                setFlash('success', "Department '{$dept['name']}' deleted successfully!");
            } else {
                setFlash('error', 'Cannot delete — this department has linked records.');
            }
        }
    }

    // Redirect to prevent form resubmission on refresh
    if (empty($error)) {
        redirect(BASE_URL . 'admin/departments.php');
    }
}

// ============================================
// READ — Fetch all departments for display
// ============================================
$departments = $conn->query(
    "SELECT d.*,
            COUNT(DISTINCT yl.id) as year_level_count,
            COUNT(DISTINCT s.id)  as section_count
     FROM departments d
     LEFT JOIN year_levels yl ON yl.department_id = d.id
     LEFT JOIN sections    s  ON s.department_id  = d.id
     GROUP BY d.id
     ORDER BY d.name ASC"
);

// If editing — fetch the selected department's data
$editDept = null;
if (isset($_GET['edit'])) {
    $editId   = (int)$_GET['edit'];
    $editDept = getById($conn, 'departments', $editId);
}

require_once '../includes/header.php';
?>

<!-- ============================================
     PAGE HEADER
     ============================================ -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-building me-2"></i>Departments</h1>
        <p>Manage college departments of the university</p>
    </div>
    <button class="btn btn-lspu" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-2"></i>Add Department
    </button>
</div>

<?php showFlash(); ?>

<?php if (!empty($error)): ?>
    <div class="alert-box error mb-3">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo clean($error); ?>
    </div>
<?php endif; ?>

<!-- ============================================
     DEPARTMENTS TABLE
     ============================================ -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>All Departments</h5>
        <span class="badge bg-light text-dark">
            <?php echo $departments->num_rows; ?> total
        </span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Year Levels</th>
                        <th>Sections</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($departments->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No departments yet. Click "Add Department" to start.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; while ($dept = $departments->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <strong><?php echo clean($dept['name']); ?></strong>
                            <?php if (!empty($dept['description'])): ?>
                                <br><small class="text-muted">
                                    <?php echo clean(truncate($dept['description'], 60)); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-balance">
                                <?php echo clean($dept['code']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-1 rounded-pill">
                                <?php echo $dept['year_level_count']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-3 py-1 rounded-pill">
                                <?php echo $dept['section_count']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($dept['is_active']): ?>
                                <span class="badge-fund">Active</span>
                            <?php else: ?>
                                <span class="badge-expense">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo formatDate($dept['created_at']); ?>
                        </td>
                        <td class="text-center">
                            <!-- Edit Button -->
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $dept['id']; ?>,
                                        '<?php echo addslashes(clean($dept['name'])); ?>',
                                        '<?php echo clean($dept['code']); ?>',
                                        '<?php echo addslashes(clean($dept['description'] ?? '')); ?>',
                                        <?php echo $dept['is_active']; ?>
                                    )"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Delete Button -->
                           <form method="POST" style="display:inline;">
                           <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                           <input type="hidden" name="action" value="delete">
                           <input type="hidden" name="id"     value="<?php echo $dept['id']; ?>">
                           <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Delete <?php echo addslashes(clean($dept['name'])); ?>? This cannot be undone.')"
                            title="Delete">
                           <i class="bi bi-trash"></i>
                           </button>
                        </form>
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
     ADD DEPARTMENT MODAL
     ============================================ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add New Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
           <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">

                    <div class="mb-3">
                        <label class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g. College of Computer Studies" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control"
                               placeholder="e.g. CCS" maxlength="20"
                               style="text-transform:uppercase;" required>
                        <div class="form-text">Short unique code. Will be auto-uppercased.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Brief description of this department"></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     EDIT DEPARTMENT MODAL
     ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"     id="editId">
                <div class="modal-body lspu-form p-4">

                    <div class="mb-3">
                        <label class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName"
                               class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="editCode"
                               class="form-control" maxlength="20"
                               style="text-transform:uppercase;" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription"
                                  class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   name="is_active" id="editIsActive" value="1">
                            <label class="form-check-label fw-semibold" for="editIsActive">
                                Active
                            </label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     PAGE JAVASCRIPT
     ============================================ -->
<script>
// Populate the Edit modal with the selected department's data
function openEditModal(id, name, code, description, isActive) {
    document.getElementById('editId').value          = id;
    document.getElementById('editName').value        = name;
    document.getElementById('editCode').value        = code;
    document.getElementById('editDescription').value = description;
    document.getElementById('editIsActive').checked  = isActive == 1;

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Auto-uppercase the code field while typing
document.querySelectorAll('input[name="code"]').forEach(function(el) {
    el.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>