<?php
// ============================================
// LSPUFundex - Sections Management
// File: admin/sections.php
// Location: C:\xampp\htdocs\LSPUFundex\admin\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'Manage Sections';
$activePage = 'sections';
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
        $year_level_id = (int)($_POST['year_level_id'] ?? 0);
        $name          = trim($_POST['name']          ?? '');
        $school_year   = trim($_POST['school_year']   ?? '');

        if ($department_id <= 0 || $year_level_id <= 0 || empty($name) || empty($school_year)) {
            $error = 'All fields are required.';
        } else {
            // Check for duplicate section name in same year level & school year
            $check = $conn->prepare(
                "SELECT id FROM sections
                 WHERE year_level_id = ? AND name = ? AND school_year = ?"
            );
            $check->bind_param("iss", $year_level_id, $name, $school_year);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Section '{$name}' already exists in this year level for {$school_year}.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO sections
                        (department_id, year_level_id, name, school_year)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("iiss", $department_id, $year_level_id, $name, $school_year);

                if ($stmt->execute()) {
                    $dept = getById($conn, 'departments', $department_id);
                    $yl   = getById($conn, 'year_levels', $year_level_id);
                    logAction($conn, $_SESSION['user_id'], 'CREATE', 'Sections',
                        "Added section '{$name}' under {$dept['code']} {$yl['name']}");
                    setFlash('success', "Section '{$name}' added successfully!");
                } else {
                    setFlash('error', 'Failed to add section. Please try again.');
                }
            }
        }
    }

    // ----------------------------------------
    // UPDATE
    // ----------------------------------------
    if ($action === 'update') {
        $id            = (int)($_POST['id']            ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        $year_level_id = (int)($_POST['year_level_id'] ?? 0);
        $name          = trim($_POST['name']           ?? '');
        $school_year   = trim($_POST['school_year']    ?? '');
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || $department_id <= 0 || $year_level_id <= 0
            || empty($name) || empty($school_year)) {
            $error = 'All fields are required.';
        } else {
            // Check duplicate — exclude current record
            $check = $conn->prepare(
                "SELECT id FROM sections
                 WHERE year_level_id = ? AND name = ?
                 AND school_year = ? AND id != ?"
            );
            $check->bind_param("issi", $year_level_id, $name, $school_year, $id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Section '{$name}' already exists in this year level for {$school_year}.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE sections
                     SET department_id = ?, year_level_id = ?,
                         name = ?, school_year = ?, is_active = ?
                     WHERE id = ?"
                );
                $stmt->bind_param("iissii",
                    $department_id, $year_level_id,
                    $name, $school_year, $is_active, $id);

                if ($stmt->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Sections',
                        "Updated section ID {$id} to '{$name}'");
                    setFlash('success', "Section '{$name}' updated successfully!");
                } else {
                    setFlash('error', 'Failed to update section.');
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
            setFlash('error', 'Invalid section selected.');
        } else {
            $sec = getById($conn, 'sections', $id);

            $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'DELETE', 'Sections',
                    "Deleted section: {$sec['name']}");
                setFlash('success', "Section '{$sec['name']}' deleted successfully!");
            } else {
                setFlash('error', 'Cannot delete — this section has funds or expenses linked to it.');
            }
        }
    }

    if (empty($error)) {
        redirect(BASE_URL . 'admin/sections.php');
    }
}

// ============================================
// READ — Fetch all sections with related data
// ============================================
$filterDept = (int)($_GET['dept'] ?? 0);

if ($filterDept > 0) {
    $stmt = $conn->prepare(
        "SELECT s.*,
                d.name  AS dept_name,
                d.code  AS dept_code,
                yl.name AS year_level_name
         FROM sections s
         JOIN departments d  ON d.id  = s.department_id
         JOIN year_levels yl ON yl.id = s.year_level_id
         WHERE s.department_id = ?
         ORDER BY d.name ASC, yl.order_num ASC, s.name ASC"
    );
    $stmt->bind_param("i", $filterDept);
    $stmt->execute();
    $sections = $stmt->get_result();
} else {
    $sections = $conn->query(
        "SELECT s.*,
                d.name  AS dept_name,
                d.code  AS dept_code,
                yl.name AS year_level_name
         FROM sections s
         JOIN departments d  ON d.id  = s.department_id
         JOIN year_levels yl ON yl.id = s.year_level_id
         ORDER BY d.name ASC, yl.order_num ASC, s.name ASC"
    );
}

// All active departments for dropdowns
$allDepts = $conn->query(
    "SELECT id, name, code FROM departments
     WHERE is_active = 1 ORDER BY name ASC"
);
$deptList = $allDepts->fetch_all(MYSQLI_ASSOC);

// Generate school year options
$currentYear = (int)date('Y');
$schoolYears = [];
for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
    $schoolYears[] = $y . '-' . ($y + 1);
}

require_once '../includes/header.php';
?>

<!-- ============================================
     PAGE HEADER
     ============================================ -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-grid me-2"></i>Sections</h1>
        <p>Manage class sections per department and year level</p>
    </div>
    <button class="btn btn-lspu" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-2"></i>Add Section
    </button>
</div>

<?php showFlash(); ?>

<?php if (!empty($error)): ?>
    <div class="alert-box error mb-3">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo clean($error); ?>
    </div>
<?php endif; ?>

<!-- ============================================
     FILTER BAR
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
                    <a href="sections.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x me-1"></i>Clear
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ============================================
     SECTIONS TABLE
     ============================================ -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>
            <?php echo $filterDept > 0 ? 'Filtered Sections' : 'All Sections'; ?>
        </h5>
        <span class="badge bg-light text-dark">
            <?php echo $sections->num_rows; ?> total
        </span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Section Name</th>
                        <th>Department</th>
                        <th>Year Level</th>
                        <th>School Year</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($sections->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No sections found. Click "Add Section" to start.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; while ($sec = $sections->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <strong><?php echo clean($sec['name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge-balance">
                                <?php echo clean($sec['dept_code']); ?>
                            </span>
                            <span class="text-muted small ms-1">
                                <?php echo clean($sec['dept_name']); ?>
                            </span>
                        </td>
                        <td><?php echo clean($sec['year_level_name']); ?></td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10
                                         text-secondary fw-semibold px-3 py-1 rounded-pill">
                                <?php echo clean($sec['school_year']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sec['is_active']): ?>
                                <span class="badge-fund">Active</span>
                            <?php else: ?>
                                <span class="badge-expense">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo formatDate($sec['created_at']); ?>
                        </td>
                        <td class="text-center">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $sec['id']; ?>,
                                        <?php echo $sec['department_id']; ?>,
                                        <?php echo $sec['year_level_id']; ?>,
                                        '<?php echo addslashes(clean($sec['name'])); ?>',
                                        '<?php echo clean($sec['school_year']); ?>',
                                        <?php echo $sec['is_active']; ?>
                                    )"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Delete -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?php echo $sec['id']; ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete section '<?php echo clean($sec['name']); ?>'? All its funds and expenses will also be removed."
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
     ADD SECTION MODAL
     ============================================ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add New Section
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
           <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">

                    <!-- Step indicator -->
                    <div class="ajax-steps mb-3">
                        <span class="step-badge active">Step 1</span>
                        Select Department
                        <i class="bi bi-arrow-right mx-2 text-muted"></i>
                        <span class="step-badge">Step 2</span>
                        Select Year Level
                        <i class="bi bi-arrow-right mx-2 text-muted"></i>
                        <span class="step-badge">Step 3</span>
                        Fill Section Details
                    </div>

                    <!-- 1. Department -->
                    <div class="mb-3">
                        <label class="form-label">
                            Department <span class="text-danger">*</span>
                        </label>
                        <select name="department_id" id="addDeptId"
                                class="form-select" required>
                            <option value="">— Select Department First —</option>
                            <?php foreach ($deptList as $d): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    <?php echo clean($d['name']); ?>
                                    (<?php echo clean($d['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Year Level (loaded by AJAX) -->
                    <div class="mb-3">
                        <label class="form-label">
                            Year Level <span class="text-danger">*</span>
                        </label>
                        <select name="year_level_id" id="addYearLevelId"
                                class="form-select" required disabled>
                            <option value="">— Select Department First —</option>
                        </select>
                        <div id="addYearLevelSpinner" class="form-text text-primary d-none">
                            <i class="bi bi-arrow-repeat spin me-1"></i>Loading year levels...
                        </div>
                    </div>

                    <!-- 3. Section Name -->
                    <div class="mb-3">
                        <label class="form-label">
                            Section Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="addSectionName"
                               class="form-control"
                               placeholder="e.g. BSIT 2A" required>
                        <div class="form-text">
                            Use standard naming: Program + Year + Section letter.
                        </div>
                    </div>

                    <!-- 4. School Year -->
                    <div class="mb-3">
                        <label class="form-label">
                            School Year <span class="text-danger">*</span>
                        </label>
                        <select name="school_year" class="form-select" required>
                            <?php foreach ($schoolYears as $sy): ?>
                                <option value="<?php echo $sy; ?>"
                                    <?php echo $sy === date('Y') . '-' . (date('Y') + 1)
                                        ? 'selected' : ''; ?>>
                                    <?php echo $sy; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     EDIT SECTION MODAL
     ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Section
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"     id="editId">
                <div class="modal-body lspu-form p-4">

                    <!-- Department -->
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

                    <!-- Year Level (AJAX) -->
                    <div class="mb-3">
                        <label class="form-label">
                            Year Level <span class="text-danger">*</span>
                        </label>
                        <select name="year_level_id" id="editYearLevelId"
                                class="form-select" required>
                            <option value="">— Loading... —</option>
                        </select>
                        <div id="editYearLevelSpinner" class="form-text text-primary d-none">
                            <i class="bi bi-arrow-repeat spin me-1"></i>Loading year levels...
                        </div>
                    </div>

                    <!-- Section Name -->
                    <div class="mb-3">
                        <label class="form-label">
                            Section Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="editName"
                               class="form-control" required>
                    </div>

                    <!-- School Year -->
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <select name="school_year" id="editSchoolYear"
                                class="form-select" required>
                            <?php foreach ($schoolYears as $sy): ?>
                                <option value="<?php echo $sy; ?>">
                                    <?php echo $sy; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Active Toggle -->
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


<!-- ============================================
     JAVASCRIPT — AJAX Dropdown Logic
     ============================================ -->
<script>
// ------------------------------------------------
// AJAX function — fetch year levels for a dept
// ------------------------------------------------
function loadYearLevels(deptId, targetSelectId, spinnerId, preselectId = null) {
    const select  = document.getElementById(targetSelectId);
    const spinner = document.getElementById(spinnerId);

    // Reset dropdown
    select.innerHTML = '<option value="">— Select Year Level —</option>';
    select.disabled  = true;

    if (!deptId || deptId == 0) return;

    // Show spinner
    spinner.classList.remove('d-none');

    // AJAX call to our PHP handler
    fetch(`ajax_get_year_levels.php?department_id=${deptId}`)
        .then(response => response.json())
        .then(data => {
            spinner.classList.add('d-none');

            if (data.length === 0) {
                select.innerHTML =
                    '<option value="">No year levels found for this department</option>';
                return;
            }

            // Populate the dropdown with results
            data.forEach(yl => {
                const opt      = document.createElement('option');
                opt.value      = yl.id;
                opt.textContent = yl.name;

                // Pre-select if editing
                if (preselectId && yl.id == preselectId) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });

            select.disabled = false;
        })
        .catch(() => {
            spinner.classList.add('d-none');
            select.innerHTML =
                '<option value="">Error loading year levels</option>';
        });
}

// ------------------------------------------------
// ADD MODAL — department change triggers AJAX
// ------------------------------------------------
document.getElementById('addDeptId').addEventListener('change', function () {
    loadYearLevels(this.value, 'addYearLevelId', 'addYearLevelSpinner');
});

// ------------------------------------------------
// EDIT MODAL — open with pre-filled values
// ------------------------------------------------
function openEditModal(id, deptId, yearLevelId, name, schoolYear, isActive) {
    document.getElementById('editId').value       = id;
    document.getElementById('editName').value     = name;
    document.getElementById('editIsActive').checked = isActive == 1;

    // Set department dropdown
    const deptSelect = document.getElementById('editDeptId');
    for (let opt of deptSelect.options) {
        opt.selected = opt.value == deptId;
    }

    // Set school year
    const sySelect = document.getElementById('editSchoolYear');
    for (let opt of sySelect.options) {
        opt.selected = opt.value === schoolYear;
    }

    // Load year levels via AJAX and pre-select the current one
    loadYearLevels(deptId, 'editYearLevelId', 'editYearLevelSpinner', yearLevelId);

    // Also listen for dept change inside edit modal
    document.getElementById('editDeptId').onchange = function () {
        loadYearLevels(this.value, 'editYearLevelId', 'editYearLevelSpinner');
    };

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>