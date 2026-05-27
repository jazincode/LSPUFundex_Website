<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireCouncil();

$deptId = (int)($_SESSION['department_id'] ?? 0);
if ($deptId <= 0) {
    setFlash('error', 'No department assigned.');
    redirect(BASE_URL . 'council/dashboard.php');
}

$dept = getById($conn, 'departments', $deptId);

$pageTitle  = 'Council Expenses';
$activePage = 'council_expenses';
$error      = '';

define('UPLOAD_DIR',      '../uploads/receipts/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024);
define('UPLOAD_TYPES',    ['image/jpeg','image/png','image/gif','image/webp']);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title        = trim($_POST['title']        ?? '');
        $category     = trim($_POST['category']     ?? 'Others');
        $amount       = (float)($_POST['amount']    ?? 0);
        $notes        = trim($_POST['notes']        ?? '');
        $expense_date = trim($_POST['expense_date'] ?? '');
        $receipt_path = null;

        if (empty($title) || $amount <= 0 || empty($expense_date)) {
            $error = 'Title, amount, and date are required.';
        } else {
            $currentBalance = getCouncilBalance($conn, $deptId);
            if ($amount > $currentBalance) {
                $error = 'Insufficient council balance! Current balance is ' .
                         formatMoney($currentBalance) . '.';
            }
        }

        if (empty($error) && !empty($_FILES['receipt']['name'])) {
            $file     = $_FILES['receipt'];
            $fileType = mime_content_type($file['tmp_name']);
            $fileSize = $file['size'];
            $fileExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileType, UPLOAD_TYPES)) {
                $error = 'Receipt must be JPG, PNG, GIF, or WEBP.';
            } elseif ($fileSize > UPLOAD_MAX_SIZE) {
                $error = 'Receipt must be smaller than 2MB.';
            } else {
                $newFileName = 'council_receipt_' . time() . '_' .
                               $_SESSION['user_id'] . '.' . $fileExt;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newFileName)) {
                    $receipt_path = 'uploads/receipts/' . $newFileName;
                } else {
                    $error = 'Failed to upload receipt.';
                }
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare(
                "INSERT INTO council_expenses
                    (department_id, added_by, title, category, amount,
                     receipt_path, notes, expense_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiisdsss",
                $deptId, $_SESSION['user_id'],
                $title, $category, $amount,
                $receipt_path, $notes, $expense_date
            );
            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'CREATE', 'Council Expenses',
                    "Added council expense: '{$title}' — " . formatMoney($amount));
                setFlash('success', "Expense '{$title}' recorded successfully!");
            } else {
                setFlash('error', 'Failed to save expense.');
            }
        }
    }

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
            $existing = $conn->prepare(
                "SELECT * FROM council_expenses WHERE id=? AND department_id=?"
            );
            $existing->bind_param("ii", $id, $deptId);
            $existing->execute();
            $existingData = $existing->get_result()->fetch_assoc();

            if (!$existingData) {
                $error = 'Expense not found or access denied.';
            } else {
                $receipt_path = $existingData['receipt_path'];

                if (!empty($_FILES['receipt']['name'])) {
                    $file     = $_FILES['receipt'];
                    $fileType = mime_content_type($file['tmp_name']);
                    $fileSize = $file['size'];
                    $fileExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($fileType, UPLOAD_TYPES)) {
                        $error = 'Receipt must be JPG, PNG, GIF, or WEBP.';
                    } elseif ($fileSize > UPLOAD_MAX_SIZE) {
                        $error = 'Receipt must be smaller than 2MB.';
                    } else {
                        $newFileName = 'council_receipt_' . time() . '_' .
                                       $_SESSION['user_id'] . '.' . $fileExt;
                        if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newFileName)) {
                            if ($receipt_path && file_exists('../' . $receipt_path)) {
                                unlink('../' . $receipt_path);
                            }
                            $receipt_path = 'uploads/receipts/' . $newFileName;
                        } else {
                            $error = 'Failed to upload receipt.';
                        }
                    }
                }

                if ($keep_receipt == 0 && $receipt_path) {
                    if (file_exists('../' . $receipt_path)) unlink('../' . $receipt_path);
                    $receipt_path = null;
                }

                if (empty($error)) {
                    $stmt = $conn->prepare(
                        "UPDATE council_expenses
                         SET title=?, category=?, amount=?,
                             receipt_path=?, notes=?, expense_date=?
                         WHERE id=? AND department_id=?"
                    );
                    $stmt->bind_param("ssdsssii",
                        $title, $category, $amount,
                        $receipt_path, $notes, $expense_date,
                        $id, $deptId
                    );
                    if ($stmt->execute()) {
                        logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Council Expenses',
                            "Updated council expense ID {$id}: '{$title}'");
                        setFlash('success', "Expense '{$title}' updated successfully!");
                    } else {
                        setFlash('error', 'Failed to update expense.');
                    }
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlash('error', 'Invalid expense selected.');
        } else {
            $exp = $conn->prepare(
                "SELECT * FROM council_expenses WHERE id=? AND department_id=?"
            );
            $exp->bind_param("ii", $id, $deptId);
            $exp->execute();
            $expData = $exp->get_result()->fetch_assoc();

            if (!$expData) {
                setFlash('error', 'Expense not found or access denied.');
            } else {
                $del = $conn->prepare(
                    "DELETE FROM council_expenses WHERE id=? AND department_id=?"
                );
                $del->bind_param("ii", $id, $deptId);
                if ($del->execute()) {
                    if ($expData['receipt_path'] && file_exists('../' . $expData['receipt_path'])) {
                        unlink('../' . $expData['receipt_path']);
                    }
                    logAction($conn, $_SESSION['user_id'], 'DELETE', 'Council Expenses',
                        "Deleted council expense: '{$expData['title']}'");
                    setFlash('success', 'Expense deleted successfully!');
                } else {
                    setFlash('error', 'Failed to delete expense.');
                }
            }
        }
    }

    if (empty($error)) {
        redirect(BASE_URL . 'council/expenses.php');
    }
}

// Fetch all council expenses
$stmt = $conn->prepare(
    "SELECT ce.*, u.full_name AS added_by_name
     FROM council_expenses ce
     JOIN users u ON u.id = ce.added_by
     WHERE ce.department_id = ?
     ORDER BY ce.expense_date DESC, ce.created_at DESC"
);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$expenses = $stmt->get_result();

$totalFunds    = getCouncilFunds($conn, $deptId);
$totalExpenses = getCouncilExpenses($conn, $deptId);
$balance       = $totalFunds - $totalExpenses;

$categories = ['Supplies','Events','Transportation','Food','Printing','Donations','Others'];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt me-2"></i>Council Expenses</h1>
        <p>Managing expenses for <strong><?php echo clean($dept['name']); ?></strong>
           Council (<?php echo clean($dept['code']); ?>)</p>
    </div>
    <button class="btn btn-lspu" data-bs-toggle="modal" data-bs-target="#addModal">
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
        <i class="bi bi-exclamation-circle me-2"></i><?php echo clean($error); ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value"><?php echo formatMoney($totalFunds); ?></div>
                <div class="stat-label">Total Council Funds</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="stat-value"><?php echo formatMoney($totalExpenses); ?></div>
                <div class="stat-label">Total Council Expenses</div>
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
                <div class="stat-label">Council Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>Council Expense Records</h5>
        <span class="badge bg-light text-dark"><?php echo $expenses->num_rows; ?> records</span>
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
                            No council expenses yet.
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
                                echo strtolower(str_replace(' ','-',$e['category'])); ?>">
                                <?php echo clean($e['category']); ?>
                            </span>
                        </td>
                        <td class="small"><?php echo formatDate($e['expense_date']); ?></td>
                        <td class="text-muted small"><?php echo clean($e['added_by_name']); ?></td>
                        <td class="text-center">
                            <?php if ($e['receipt_path']): ?>
                                <a href="<?php echo BASE_URL . clean($e['receipt_path']); ?>"
                                   target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-image"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="badge-expense">
                                <?php echo formatMoney($e['amount']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $e['id']; ?>,
                                        '<?php echo addslashes(clean($e['title'])); ?>',
                                        '<?php echo clean($e['category']); ?>',
                                        <?php echo $e['amount']; ?>,
                                        '<?php echo $e['expense_date']; ?>',
                                        '<?php echo addslashes(clean($e['notes'] ?? '')); ?>',
                                        <?php echo $e['receipt_path'] ? 1 : 0; ?>
                                    )" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" style="display:inline;" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?php echo $e['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this expense?')"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($expenses->num_rows > 0): ?>
                <tfoot>
                    <tr style="background:#fff5f5; font-weight:700;">
                        <td colspan="6" class="text-end" style="color:#c0392b;">TOTAL EXPENSES:</td>
                        <td class="text-end">
                            <span class="badge-expense" style="font-size:13px;">
                                <?php echo formatMoney($totalExpenses); ?>
                            </span>
                        </td>
                        <td></td>
                    </tr>
                    <tr style="background:#f0fff4; font-weight:700;">
                        <td colspan="6" class="text-end" style="color:#1e8449;">BALANCE:</td>
                        <td class="text-end">
                            <span class="fw-bold" style="font-size:13px;
                                  color:<?php echo $balance >= 0 ? '#27ae60' : '#e74c3c'; ?>">
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

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add Council Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">
                    <div class="balance-info mb-3 p-3 rounded"
                         style="background:#eafaf1; border:1px solid #a9dfbf;">
                        <small>
                            <i class="bi bi-wallet2 me-1 text-success"></i>
                            <strong>Available Council Balance:</strong>
                            <span class="fw-bold text-success">
                                <?php echo formatMoney($balance); ?>
                            </span>
                        </small>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Expense Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="e.g. Council Event Supplies" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                   placeholder="0.00" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="Optional details..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                Receipt Image <span class="text-muted">(optional, max 2MB)</span>
                            </label>
                            <input type="file" name="receipt" class="form-control"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Council Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token"   value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action"       value="update">
                <input type="hidden" name="id"           id="editId">
                <input type="hidden" name="keep_receipt" id="keepReceipt" value="1">
                <div class="modal-body lspu-form p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Expense Title</label>
                            <input type="text" name="title" id="editTitle"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category" id="editCategory" class="form-select">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
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
                            <label class="form-label">Replace Receipt</label>
                            <input type="file" name="receipt" class="form-control"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <div id="existingReceiptInfo" class="mt-2 d-none">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-image text-info"></i>
                                    <span class="small text-muted">Receipt already uploaded.</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="removeReceipt()">
                                        <i class="bi bi-x me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
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

<script>
function openEditModal(id, title, category, amount, date, notes, hasReceipt) {
    document.getElementById('editId').value       = id;
    document.getElementById('editTitle').value    = title;
    document.getElementById('editAmount').value   = amount;
    document.getElementById('editDate').value     = date;
    document.getElementById('editNotes').value    = notes;
    document.getElementById('keepReceipt').value  = 1;
    const catSelect = document.getElementById('editCategory');
    for (let opt of catSelect.options) { opt.selected = opt.value === category; }
    const receiptInfo = document.getElementById('existingReceiptInfo');
    if (hasReceipt) receiptInfo.classList.remove('d-none');
    else            receiptInfo.classList.add('d-none');
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function removeReceipt() {
    document.getElementById('keepReceipt').value = 0;
    document.getElementById('existingReceiptInfo').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>