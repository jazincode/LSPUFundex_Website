<?php
// ============================================
// LSPUFundex - Year Levels Management
// File: admin/year_levels.php
// Location: C:\xampp\htdocs\LSPUFundex\admin\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'Manage Year Levels';
$activePage = 'year_levels';
$error      = '';

// ============================================
// HANDLE FORM ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ----------------------------------------
    // CREATE
    // ----------------------------------------
    if ($action === 'create') {
        $department_id = (int)($_POST['department_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $order_num     = (int)($_POST['order_num'] ?? 1);

        if ($department_id <= 0 || empty($name)) {
            $error = 'Department and year level name are required.';
        } else {
            // Check for duplicate year level in same department
            $check = $conn->prepare(
                "SELECT id FROM year_levels
                 WHERE department_id = ? AND name = ?"
            );
            $check->bind_param("is", $department_id, $name);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "'{$name}' already exists in this department.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO year_levels (department_id, name, order_num)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param("isi", $department_id, $name, $order_num);

                if ($stmt->execute()) {
                    // Get department name for log
                    $dept = getById($conn, 'departments', $department_id);
                    logAction($conn, $_SESSION['user_id'], 'CREATE', 'Year Levels',
                        "Added '{$name}' under {$dept['name']}");
                    setFlash('success', "Year level '{$name}' added successfully!");
                } else {
                    setFlash('error', 'Failed to add year level. Please try again.');
                }
            }
        }
    }

    // ----------------------------------------
    // UPDATE
    // ----------------------------------------
    if ($action === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $order_num     = (int)($_POST['order_num'] ?? 1);
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || $department_id <= 0 || empty($name)) {
            $error = 'All required fields must be filled.';
        } else {
            // Check duplicate — exclude current record
            $check = $conn->prepare(
                "SELECT id FROM year_levels
                 WHERE department_id = ? AND name = ? AND id != ?"
            );
            $check->bind_param("isi", $department_id, $name, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "'{$name}' already exists in this department.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE year_levels
                     SET department_id = ?, name = ?, order_num = ?, is_active = ?
                     WHERE id = ?"
                );
                $stmt->bind_param("isiii", $department_id, $name, $order_num, $is_active, $id);

                if ($stmt->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Year Levels',
                        "Updated year level ID {$id} to '{$name}'");
                    setFlash('success', "Year level '{$name}' updated successfully!");
                } else {
                    setFlash('error', 'Failed to update year level.');
                }
            }
        }
    }

    // ----------------------------------------
    // DELETE
    // ----------------------------------------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            setFlash('error', 'Invalid year level selected.');
        } else {
            $yl = getById($conn, 'year_levels', $id);

            $stmt = $conn->prepare("DELETE FROM year_levels WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'DELETE', 'Year Levels',
                    "Deleted year level: {$yl['name']}");
                setFlash('success', "Year level '{$yl['name']}' deleted successfully!");
            } else {
                setFlash('error', 'Cannot delete — this year level has linked sections.');
            }
        }
    }

    if (empty($error)) {
        redirect(BASE_URL . 'admin/year_levels.php');
    }
}

// ============================================
// READ — Fetch all year levels with department names
// ============================================

// For filter — get selected department
$filterDept = (int)($_GET['dept'] ?? 0);

// Build query with optional department filter
if ($filterDept > 0) {
    $stmt = $conn->prepare(
        "SELECT yl.*,
                d.name  AS dept_name,
                d.code  AS dept_code,
                COUNT(s.id) AS section_count
         FROM year_levels yl
         JOIN departments d ON d.id = yl.department_id
         LEFT JOIN sections s ON s.year_level_id = yl.id
         WHERE yl.department_id = ?
         GROUP BY yl.id
         ORDER BY d.name ASC, yl.order_num ASC"
    );
    $stmt->bind_param("i", $filterDept);
    $stmt->execute();
    $yearLevels = $stmt->get_result();
} else {
    $yearLevels = $conn->query(
        "SELECT yl.*,
                d.name  AS dept_name,
                d.code  AS dept_code,
                COUNT(s.id) AS section_count
         FROM year_levels yl
         JOIN departments d ON d.id = yl.department_id
         LEFT JOIN sections s ON s.year_level_id = yl.id
         GROUP BY yl.id
         ORDER BY d.name ASC, yl.order_num ASC"
    );
}

// Fetch all active departments for the Add/Edit dropdowns
$allDepts = $conn->query(
    "SELECT id, name, code FROM departments
     WHERE is_active = 1
     ORDER BY name ASC"
);
$deptList = $allDepts->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<!-- ============================================
     PAGE HEADER
     ============================================ -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-layers me-2"></i>Year Levels</h1>
        <p>Manage year levels per department</p>
    </div>
    <button class="btn btn-lspu" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-2"></i>Add Year Level
    </button>
</div>

<?php showFlash(); ?>

<?php if (!empty($error)): ?>
    <div class="alert-box error mb-3">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo clean($error); ?>
    </div>
<?php endif; ?>

<!-- ============================================
     FILTER BY DEPARTMENT
     ============================================ -->
<div class="lspu-card mb-4">
    <div class="lspu-card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0 fw-semibold small">
                    <i class="bi bi-funnel me-1"></i>Filter by Department:
                </label>
            </div>
            <div class="col-auto">
                <select name="dept" class="form-select form-select-sm"
                        onchange="this.form.submit()">
                    <option value="0">All Departments</option>
                    <?php foreach ($deptList as $d): ?>
                        <option value="<?php echo $d['id']; ?>"
                            <?php echo $filterDept == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo clean($d['name']); ?>
                            (<?php echo clean($d['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterDept > 0): ?>
                <div class="col-auto">
                    <a href="year_levels.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x me-1"></i>Clear Filter
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ============================================
     YEAR LEVELS TABLE
     ============================================ -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>
            <?php echo $filterDept > 0 ? 'Filtered Year Levels' : 'All Year Levels'; ?>
        </h5>
        <span class="badge bg-light text-dark">
            <?php echo $yearLevels->num_rows; ?> total
        </span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Year Level</th>
                        <th>Department</th>
                        <th>Order</th>
                        <th>Sections</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($yearLevels->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No year levels found.
                            <?php echo $filterDept > 0
                                ? 'Try clearing the filter.'
                                : 'Click "Add Year Level" to start.'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; while ($yl = $yearLevels->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <strong><?php echo clean($yl['name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge-balance">
                                <?php echo clean($yl['dept_code']); ?>
                            </span>
                            <span class="text-muted small ms-1">
                                <?php echo clean($yl['dept_name']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="text-muted small">
                                <?php echo $yl['order_num']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-3 py-1 rounded-pill">
                                <?php echo $yl['section_count']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($yl['is_active']): ?>
                                <span class="badge-fund">Active</span>
                            <?php else: ?>
                                <span class="badge-expense">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo formatDate($yl['created_at']); ?>
                        </td>
                        <td class="text-center">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $yl['id']; ?>,
                                        <?php echo $yl['department_id']; ?>,
                                        '<?php echo addslashes(clean($yl['name'])); ?>',
                                        <?php echo $yl['order_num']; ?>,
                                        <?php echo $yl['is_active']; ?>
                                    )"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Delete -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?php echo $yl['id']; ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete '<?php echo clean($yl['name']); ?>'? Sections under it will also be removed."
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
     ADD YEAR LEVEL MODAL
     ============================================ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add Year Level
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">

                    <div class="mb-3">
                        <label class="form-label">
                            Department <span class="text-danger">*</span>
                        </label>
                        <select name="department_id" class="form-select" required>
                            <option value="">— Select Department —</option>
                            <?php foreach ($deptList as $d): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    <?php echo clean($d['name']); ?>
                                    (<?php echo clean($d['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Year Level Name <span class="text-danger">*</span>
                        </label>
                        <select name="name" class="form-select" required>
                            <option value="">— Select Year Level —</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                        <div class="form-text">
                            Year levels are standardized to prevent duplicates.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="order_num" class="form-control"
                               value="1" min="1" max="10">
                        <div class="form-text">
                            Controls sort order. 1 = shown first.
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Year Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     EDIT YEAR LEVEL MODAL
     ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Year Level
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
           <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"     id="editId">
                <div class="modal-body lspu-form p-4">

                    <div class="mb-3">
                        <label class="form-label">
                            Department <span class="text-danger">*</span>
                        </label>
                        <select name="department_id" id="editDeptId"
                                class="form-select" required>
                            <option value="">— Select Department —</option>
                            <?php foreach ($deptList as $d): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    <?php echo clean($d['name']); ?>
                                    (<?php echo clean($d['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Year Level Name <span class="text-danger">*</span>
                        </label>
                        <select name="name" id="editName"
                                class="form-select" required>
                            <option value="">— Select Year Level —</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="order_num" id="editOrderNum"
                               class="form-control" min="1" max="10">
                    </div>

                    <div class="mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   name="is_active" id="editIsActive" value="1">
                            <label class="form-check-label fw-semibold"
                                   for="editIsActive">Active</label>
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


<script>
// Populate Edit modal
function openEditModal(id, deptId, name, orderNum, isActive) {
    document.getElementById('editId').value       = id;
    document.getElementById('editOrderNum').value = orderNum;
    document.getElementById('editIsActive').checked = isActive == 1;

    // Set department dropdown
    const deptSelect = document.getElementById('editDeptId');
    for (let opt of deptSelect.options) {
        opt.selected = opt.value == deptId;
    }

    // Set year level name dropdown
    const nameSelect = document.getElementById('editName');
    for (let opt of nameSelect.options) {
        opt.selected = opt.value === name;
    }

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>