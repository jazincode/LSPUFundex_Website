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

$pageTitle  = 'Council Funds';
$activePage = 'council_funds';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
    $title     = trim($_POST['title']     ?? '');
    $amount    = (float)($_POST['amount'] ?? 0);
    $source    = trim($_POST['source']    ?? '');
    $notes     = trim($_POST['notes']     ?? '');
    $fund_date = trim($_POST['fund_date'] ?? '');

    if (empty($title) || $amount <= 0 || empty($fund_date)) {
        $error = 'Title, amount, and date are required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO council_funds
                (department_id, added_by, title, amount, source, notes, fund_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisdsss",
            $deptId, $_SESSION['user_id'],
            $title, $amount, $source, $notes, $fund_date
        );
        if ($stmt->execute()) {
            logAction($conn, $_SESSION['user_id'], 'CREATE', 'Council Funds',
                "Added council fund: '{$title}' — " . formatMoney($amount));
            setFlash('success', "Fund '{$title}' added successfully!");
        } else {
            setFlash('error', 'Failed to add fund.');
        }
    }
}

    if ($action === 'update') {
        $id        = (int)($_POST['id']       ?? 0);
        $title     = trim($_POST['title']     ?? '');
        $amount    = (float)($_POST['amount'] ?? 0);
        $source    = trim($_POST['source']    ?? '');
        $notes     = trim($_POST['notes']     ?? '');
        $fund_date = trim($_POST['fund_date'] ?? '');

        if ($id <= 0 || empty($title) || $amount <= 0 || empty($fund_date)) {
            $error = 'All required fields must be filled.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE council_funds
                 SET title=?, amount=?, source=?, notes=?, fund_date=?
                 WHERE id=? AND department_id=?"
            );
            // title(s), amount(d), source(s), notes(s), fund_date(s), id(i), dept_id(i)
            $stmt->bind_param("sdsssi i",
                $title, $amount, $source, $notes, $fund_date, $id, $deptId
            );
            $stmt = $conn->prepare(
                "UPDATE council_funds
                 SET title=?, amount=?, source=?, notes=?, fund_date=?
                 WHERE id=? AND department_id=?"
            );
            $stmt->bind_param("sdsssii",
                $title, $amount, $source, $notes, $fund_date, $id, $deptId
            );
            if ($stmt->execute()) {
                logAction($conn, $_SESSION['user_id'], 'UPDATE', 'Council Funds',
                    "Updated council fund ID {$id}: '{$title}'");
                setFlash('success', "Fund '{$title}' updated successfully!");
            } else {
                setFlash('error', 'Failed to update fund.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlash('error', 'Invalid fund selected.');
        } else {
            $stmt = $conn->prepare(
                "SELECT * FROM council_funds WHERE id=? AND department_id=?"
            );
            $stmt->bind_param("ii", $id, $deptId);
            $stmt->execute();
            $fund = $stmt->get_result()->fetch_assoc();

            if (!$fund) {
                setFlash('error', 'Fund not found or access denied.');
            } else {
                $del = $conn->prepare(
                    "DELETE FROM council_funds WHERE id=? AND department_id=?"
                );
                $del->bind_param("ii", $id, $deptId);
                if ($del->execute()) {
                    logAction($conn, $_SESSION['user_id'], 'DELETE', 'Council Funds',
                        "Deleted council fund: '{$fund['title']}'");
                    setFlash('success', 'Fund deleted successfully!');
                } else {
                    setFlash('error', 'Failed to delete fund.');
                }
            }
        }
    }

    if (empty($error)) {
        redirect(BASE_URL . 'council/funds.php');
    }
}

// Fetch all council funds
$stmt = $conn->prepare(
    "SELECT cf.*, u.full_name AS added_by_name
     FROM council_funds cf
     JOIN users u ON u.id = cf.added_by
     WHERE cf.department_id = ?
     ORDER BY cf.fund_date DESC, cf.created_at DESC"
);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$funds = $stmt->get_result();

$totalFunds    = getCouncilFunds($conn, $deptId);
$totalExpenses = getCouncilExpenses($conn, $deptId);
$balance       = $totalFunds - $totalExpenses;

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-stack me-2"></i>Council Funds</h1>
        <p>Managing funds for <strong><?php echo clean($dept['name']); ?></strong>
           Council (<?php echo clean($dept['code']); ?>)</p>
    </div>
    <button class="btn btn-lspu" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-2"></i>Add Fund
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
        <h5><i class="bi bi-list-ul me-2"></i>Council Fund Records</h5>
        <span class="badge bg-light text-dark"><?php echo $funds->num_rows; ?> records</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Source</th>
                        <th>Date</th>
                        <th>Added By</th>
                        <th>Notes</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($funds->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No council funds yet. Click "Add Fund" to start.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; while ($f = $funds->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td><strong><?php echo clean($f['title']); ?></strong></td>
                        <td class="text-muted small">
                            <?php echo $f['source'] ? clean($f['source']) : '—'; ?>
                        </td>
                        <td class="small"><?php echo formatDate($f['fund_date']); ?></td>
                        <td class="text-muted small"><?php echo clean($f['added_by_name']); ?></td>
                        <td class="text-muted small">
                            <?php echo $f['notes'] ? clean(truncate($f['notes'], 40)) : '—'; ?>
                        </td>
                        <td class="text-end">
                            <span class="badge-fund"><?php echo formatMoney($f['amount']); ?></span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?php echo $f['id']; ?>,
                                        '<?php echo addslashes(clean($f['title'])); ?>',
                                        <?php echo $f['amount']; ?>,
                                        '<?php echo addslashes(clean($f['source'] ?? '')); ?>',
                                        '<?php echo $f['fund_date']; ?>',
                                        '<?php echo addslashes(clean($f['notes'] ?? '')); ?>'
                                    )" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this fund entry?')"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($funds->num_rows > 0): ?>
                <tfoot>
                    <tr style="background:#f0f9ff; font-weight:700;">
                        <td colspan="6" class="text-end" style="color:#1b4f72;">TOTAL FUNDS:</td>
                        <td class="text-end">
                            <span class="badge-fund" style="font-size:13px;">
                                <?php echo formatMoney($totalFunds); ?>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add Council Fund
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body lspu-form p-4">
                    <div class="mb-3">
                        <label class="form-label">Fund Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               placeholder="e.g. Council Annual Budget" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                   placeholder="0.00" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="fund_date" class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <input type="text" name="source" class="form-control"
                               placeholder="e.g. University Allocation, Fundraising">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lspu">
                        <i class="bi bi-save me-1"></i>Save Fund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header lspu-card-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Council Fund
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id"     id="editId">
                <div class="modal-body lspu-form p-4">
                    <div class="mb-3">
                        <label class="form-label">Fund Title</label>
                        <input type="text" name="title" id="editTitle"
                               class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" name="amount" id="editAmount"
                                   class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Date</label>
                            <input type="date" name="fund_date" id="editDate"
                                   class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <input type="text" name="source" id="editSource" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
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
function openEditModal(id, title, amount, source, date, notes) {
    document.getElementById('editId').value     = id;
    document.getElementById('editTitle').value  = title;
    document.getElementById('editAmount').value = amount;
    document.getElementById('editSource').value = source;
    document.getElementById('editDate').value   = date;
    document.getElementById('editNotes').value  = notes;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>