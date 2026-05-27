<?php
// ============================================
// LSPUFundex - Expense Management (Officer)
// File: officer/expenses.php
// Location: C:\xampp\htdocs\LSPUFundex\officer\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

// Get officer's section
$sectionId = (int)($_SESSION['section_id'] ?? 0);

if ($sectionId <= 0) {
    setFlash('error', 'No section assigned. Contact your administrator.');
    redirect(BASE_URL . 'officer/dashboard.php');
}

// Get section info
$stmt = $conn->prepare(
    "SELECT s.*, d.name AS dept_name, d.code AS dept_code,
            yl.name AS year_level_name
     FROM sections s
     JOIN departments d  ON d.id  = s.department_id
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.id = ?"
);
$stmt->bind_param("i", $sectionId);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

$pageTitle  = 'Manage Expenses';
$activePage = 'expenses';
$error      = '';

// Upload settings
define('UPLOAD_DIR',      '../uploads/receipts/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('UPLOAD_TYPES',    ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Make sure upload folder exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

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
        $title        = trim($_POST['title']        ?? '');
        $category     = trim($_POST['category']     ?? 'Others');
        $amount       = (float)($_POST['amount']    ?? 0);
        $notes        = trim($_POST['notes']        ?? '');
        $expense_date = trim($_POST['expense_date'] ?? '');
        $receipt_path = null;

        // Validation
        if (empty($title) || $amount <= 0 || empty($expense_date)) {
            $error = 'Title, amount, and date are required. Amount must be greater than zero.';

        } else {
            // Check balance — prevent overspending
            $currentBalance = getBalance($conn, $sectionId);
            if ($amount > $currentBalance) {
                $error = 'Insufficient balance! Current balance is ' .
                         formatMoney($currentBalance) .
                         '. You cannot add an expense greater than the balance.';
            }
        }

        // Handle receipt upload
        if (empty($error) && !empty($_FILES['receipt']['name'])) {
            $file     = $_FILES['receipt'];
            $fileType = mime_content_type($file['tmp_name']);
            $fileSize = $file['size'];
            $fileExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileType, UPLOAD_TYPES)) {
                $error = 'Receipt must be a JPG, PNG, GIF, or WEBP image.';
            } elseif ($fileSize > UPLOAD_MAX_SIZE) {
                $error = 'Receipt image must be smaller than 2MB.';
            } else {
                // Generate unique filename
                $newFileName = 'receipt_' . time() . '_' .
                               $_SESSION['user_id'] . '.' . $fileExt;
                $uploadPath  = UPLOAD_DIR . $newFileName;

                if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $error = 'Failed to upload receipt. Please try again.';
                } else {
                    $receipt_path = 'uploads/receipts/' . $newFileName;
                }
            }
        }

        // Save to DB if no errors
        // Params: section_id(i), added_by(i), title(s), category(s),
        //         amount(d), receipt_path(s), notes(s), expense_date(s) → "iissdss s" = 8
        if (empty($error)) {
            $stmt = $conn->prepare(
                "INSERT INTO expenses
                    (section_id, added_by, title, category,
                     amount, receipt_path, notes, expense_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iissdsss",
                $sectionId,
                $_SESSION['user_id'],
                $title,
                $category,
                $amount,
                $receipt_path,
                $notes,
                $expense_date
            );

            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'CREATE', 'Expenses',
                    "Added expense: '{$title}' — " . formatMoney($amount));
                setFlash('success', "Expense '{$title}' recorded successfully!");
            } else {
                setFlash('error', 'Failed to save expense. Please try again.');
            }
        }
    }

    // ----------------------------------------
    // UPDATE
    // ----------------------------------------
    if ($action === 'update') {
        $id           = (int)($_POST['id']           ?? 0);
        $title        = trim($_POST['title']         ?? '');
        $category     = trim($_POST['category']      ?? 'Others');
        $amount       = (float)($_POST['amount']     ?? 0);
        $notes        = trim($_POST['notes']         ?? '');
        $expense_date = trim($_POST['expense_date']  ?? '');
        $keep_receipt = (int)($_POST['keep_receipt'] ?? 1);

        if ($id <= 0 || empty($title) || $amount <= 0 || empty($expense_date)) {
            $error = 'All required fields must be filled.';
        } else {
            // Get the existing expense
            $existing = $conn->prepare(
                "SELECT * FROM expenses WHERE id = ? AND section_id = ?"
            );
            $existing->bind_param("ii", $id, $sectionId);
            $existing->execute();
            $existingData = $existing->get_result()->fetch_assoc();

            if (!$existingData) {
                $error = 'Expense not found or access denied.';
            } else {
                $receipt_path = $existingData['receipt_path'];

                // Handle new receipt upload
                if (!empty($_FILES['receipt']['name'])) {
                    $file     = $_FILES['receipt'];
                    $fileType = mime_content_type($file['tmp_name']);
                    $fileSize = $file['size'];
                    $fileExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($fileType, UPLOAD_TYPES)) {
                        $error = 'Receipt must be a JPG, PNG, GIF, or WEBP image.';
                    } elseif ($fileSize > UPLOAD_MAX_SIZE) {
                        $error = 'Receipt must be smaller than 2MB.';
                    } else {
                        $newFileName = 'receipt_' . time() . '_' .
                                       $_SESSION['user_id'] . '.' . $fileExt;
                        $uploadPath  = UPLOAD_DIR . $newFileName;

                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            // Delete old receipt file if exists
                            if ($receipt_path && file_exists('../' . $receipt_path)) {
                                unlink('../' . $receipt_path);
                            }
                            $receipt_path = 'uploads/receipts/' . $newFileName;
                        } else {
                            $error = 'Failed to upload new receipt.';
                        }
                    }
                }

                // Remove receipt if unchecked
                if ($keep_receipt == 0 && $receipt_path) {
                    if (file_exists('../' . $receipt_path)) {
                        unlink('../' . $receipt_path);
                    }
                    $receipt_path = null;
                }

                // Params: title(s), category(s), amount(d), receipt_path(s),
                //         notes(s), expense_date(s), id(i), section_id(i) → "ssdsssii" = 8
                if (empty($error)) {
                    $stmt = $conn->prepare(
                        "UPDATE expenses
                         SET title = ?, category = ?, amount = ?,
                             receipt_path = ?, notes = ?, expense_date = ?
                         WHERE id = ? AND section_id = ?"
                    );
                    $stmt->bind_param(
                        "ssdsssii",
                        $title, $category, $amount,
                        $receipt_path, $notes, $expense_date,
                        $id, $sectionId
                    );

                    if ($stmt->execute()) {
                        logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Expenses',
                            "Updated expense ID {$id}: '{$title}'");
                        setFlash('success', "Expense '{$title}' updated successfully!");
                    } else {
                        setFlash('error', 'Failed to update expense.');
                    }
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
            setFlash('error', 'Invalid expense selected.');
        } else {
            $exp = $conn->prepare(
                "SELECT * FROM expenses WHERE id = ? AND section_id = ?"
            );
            $exp->bind_param("ii", $id, $sectionId);
            $exp->execute();
            $expData = $exp->get_result()->fetch_assoc();

            if (!$expData) {
                setFlash('error', 'Expense not found or access denied.');
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM expenses WHERE id = ? AND section_id = ?"
                );
                $stmt->bind_param("ii", $id, $sectionId);

                if ($stmt->execute()) {
                    // Delete receipt file if exists
                    if ($expData['receipt_path'] &&
                        file_exists('../' . $expData['receipt_path'])) {
                        unlink('../' . $expData['receipt_path']);
                    }
                    logAction($conn, $_SESSION['user_id'], 'DELETE', 'Expenses',
                        "Deleted expense: '{$expData['title']}'");
                    setFlash('success', 'Expense deleted successfully!');
                } else {
                    setFlash('error', 'Failed to delete expense.');
                }
            }
        }
    }

    if (empty($error)) {
        redirect(BASE_URL . 'officer/expenses.php');
    }
}

// ============================================
// READ — All expenses for this section
// ============================================
$stmt = $conn->prepare(
    "SELECT e.*, u.full_name AS added_by_name
     FROM expenses e
     JOIN users u ON u.id = e.added_by
     WHERE e.section_id = ?
     ORDER BY e.expense_date DESC, e.created_at DESC"
);
$stmt->bind_param("i", $sectionId);
$stmt->execute();
$expenses = $stmt->get_result();

// Financial summary
$totalFunds    = getTotalFunds($conn, $sectionId);
$totalExpenses = getTotalExpenses($conn, $sectionId);
$balance       = $totalFunds - $totalExpenses;

// Categories list (must match ENUM in database)
$categories = [
    'Supplies', 'Events', 'Transportation',
    'Food', 'Printing', 'Donations', 'Others'
];

require_once '../includes/header.php';
?>

<!-- ============================================
     PAGE HEADER
     ============================================ -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt me-2"></i>Expense Management</h1>
        <p>
            Managing expenses for:
            <strong>
                <?php echo clean($section['dept_code']); ?> —
                <?php echo clean($section['year_level_name']); ?> —
                <?php echo clean($section['name']); ?>
            </strong>
        </p>
    </div>
    <button class="btn btn-lspu"
            data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-2"></i>Add Expense
    </button>
    <a href="<?php echo BASE_URL; ?>officer/print_report.php"
   target="_blank" class="btn btn-outline-secondary">
    <i class="bi bi-printer me-1"></i>Print Report
    </a>
</div>

<?php showFlash(); ?>

<?php if (!empty($error)): ?>
    <div class="alert-box error mb-3">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?php echo clean($error); ?>
    </div>
<?php endif; ?>

<!-- ============================================
     SUMMARY CARDS
     ============================================ -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo formatMoney($totalFunds); ?></div>
                <div class="stat-label">Total Funds</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo formatMoney($totalExpenses); ?></div>
                <div class="stat-label">Total Expenses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon <?php echo $balance >= 0 ? 'green' : 'red'; ?>">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <div class="stat-value"
                     style="color:<?php echo $balance >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                    <?php echo formatMoney($balance); ?>
                </div>
                <div class="stat-label">Remaining Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     EXPENSES TABLE
     ============================================ -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>Expense Records</h5>
        <span class="badge bg-light text-dark">
            <?php echo $expenses->num_rows; ?> records
        </span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Added By</th>
                        <th>Receipt</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($expenses->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No expenses recorded yet.
                            Click "Add Expense" to record the first entry.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; while ($e = $expenses->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <strong><?php echo clean($e['title']); ?></strong>
                            <?php if ($e['notes']): ?>
                                <br><small class="text-muted">
                                    <?php echo clean(truncate($e['notes'], 40)); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="category-badge cat-<?php
                                echo strtolower(str_replace(' ', '-', $e['category']));
                            ?>">
                                <?php echo clean($e['category']); ?>
                            </span>
                        </td>
                        <td class="small">
                            <?php echo formatDate($e['expense_date']); ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo clean($e['added_by_name']); ?>
                        </td>
                        <td class="text-center">
                            <?php if ($e['receipt_path']): ?>
                                <a href="<?php echo BASE_URL . clean($e['receipt_path']); ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-info"
                                   title="View Receipt">
                                    <i class="bi bi-image"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="badge-expense">
                                <?php echo formatMoney($e['amount']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $e['id']; ?>,
                                        '<?php echo addslashes(clean($e['title'])); ?>',
                                        '<?php echo clean($e['category']); ?>',
                                        <?php echo $e['amount']; ?>,
                                        '<?php echo $e['expense_date']; ?>',
                                        '<?php echo addslashes(clean($e['notes'] ?? '')); ?>',
                                        <?php echo $e['receipt_path'] ? 1 : 0; ?>
                                    )"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Delete -->
                        <form method="POST" style="display:inline;"
                                  enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"
                                       value="<?php echo $e['id']; ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete this expense? This will affect your section balance."
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>

                <!-- TOTALS ROW -->
                <?php if ($expenses->num_rows > 0): ?>
                <tfoot>
                    <tr style="background:#fff5f5; font-weight:700;">
                        <td colspan="6" class="text-end" style="color:#c0392b;">
                            TOTAL EXPENSES:
                        </td>
                        <td class="text-end">
                            <span class="badge-expense" style="font-size:13px;">
                                <?php echo formatMoney($totalExpenses); ?>
                            </span>
                        </td>
                        <td></td>
                    </tr>
                    <tr style="background:#f0fff4; font-weight:700;">
                        <td colspan="6" class="text-end" style="color:#1e8449;">
                            REMAINING BALANCE:
                        </td>
                        <td class="text-end">
                            <span class="badge-<?php echo $balance >= 0 ? 'fund' : 'expense'; ?>"
                                  style="font-size:13px;">
                                <?php echo formatMoney($balance); ?>
                            </span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>


<!-- ============================================
     ADD EXPENSE MODAL
     ============================================ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add Expense Entry
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">

                    <!-- Balance Warning -->
                    <div class="balance-warning mb-3"
                         style="background:#eafaf1; border:1px solid #a9dfbf;
                                border-radius:8px; padding:10px 16px;">
                        <small>
                            <i class="bi bi-wallet2 me-1 text-success"></i>
                            <strong>Available Balance:</strong>
                            <span class="fw-bold text-success">
                                <?php echo formatMoney($balance); ?>
                            </span>
                        </small>
                    </div>

                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label">
                                Expense Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="e.g. Printing of Activity Worksheets"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Category <span class="text-danger">*</span>
                            </label>
                            <select name="category" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>">
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                Amount (₱) <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="amount" class="form-control"
                                   placeholder="0.00" step="0.01" min="0.01"
                                   required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="expense_date"
                                   class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="Optional details about this expense...">
                            </textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Receipt Image
                                <span class="text-muted">(optional, max 2MB)</span>
                            </label>
                            <input type="file" name="receipt"
                                   class="form-control"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">
                                Accepted formats: JPG, PNG, GIF, WEBP
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================
     EDIT EXPENSE MODAL
     ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Expense Entry
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token"    value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action"        value="update">
                <input type="hidden" name="id"            id="editId">
                <input type="hidden" name="keep_receipt"  id="keepReceipt" value="1">
                <div class="modal-body lspu-form p-4">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label">Expense Title</label>
                            <input type="text" name="title" id="editTitle"
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category" id="editCategory"
                                    class="form-select">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>">
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" name="amount" id="editAmount"
                                   class="form-control" step="0.01" min="0.01" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="expense_date" id="editDate"
                                   class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="editNotes"
                                      class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Replace Receipt Image
                                <span class="text-muted">(optional)</span>
                            </label>
                            <input type="file" name="receipt"
                                   class="form-control"
                                   accept="image/jpeg,image/png,image/gif,image/webp">

                            <!-- Existing receipt indicator -->
                            <div id="existingReceiptInfo" class="mt-2 d-none">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-image text-info"></i>
                                    <span class="small text-muted">
                                        Receipt already uploaded.
                                    </span>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="removeReceipt()">
                                        <i class="bi bi-x me-1"></i>Remove
                                    </button>
                                </div>
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
     JAVASCRIPT
     ============================================ -->
<script>
function openEditModal(id, title, category, amount, date, notes, hasReceipt) {
    document.getElementById('editId').value       = id;
    document.getElementById('editTitle').value    = title;
    document.getElementById('editAmount').value   = amount;
    document.getElementById('editDate').value     = date;
    document.getElementById('editNotes').value    = notes;
    document.getElementById('keepReceipt').value  = 1;

    // Set category
    const catSelect = document.getElementById('editCategory');
    for (let opt of catSelect.options) {
        opt.selected = opt.value === category;
    }

    // Show/hide existing receipt info
    const receiptInfo = document.getElementById('existingReceiptInfo');
    if (hasReceipt) {
        receiptInfo.classList.remove('d-none');
    } else {
        receiptInfo.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function removeReceipt() {
    document.getElementById('keepReceipt').value = 0;
    document.getElementById('existingReceiptInfo').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>