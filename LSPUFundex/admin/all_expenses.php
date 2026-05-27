<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'All Expenses';
$activePage = 'all_expenses';

$filterDept = (int)($_GET['dept']     ?? 0);
$filterCat  = trim($_GET['category']  ?? '');
$dateFrom   = trim($_GET['from']      ?? '');
$dateTo     = trim($_GET['to']        ?? '');
$search     = trim($_GET['search']    ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($filterDept > 0) {
    $where .= " AND s.department_id = ?";
    $params[] = $filterDept; $types .= "i";
}
if (!empty($filterCat)) {
    $where .= " AND e.category = ?";
    $params[] = $filterCat; $types .= "s";
}
if (!empty($dateFrom)) {
    $where .= " AND e.expense_date >= ?";
    $params[] = $dateFrom; $types .= "s";
}
if (!empty($dateTo)) {
    $where .= " AND e.expense_date <= ?";
    $params[] = $dateTo; $types .= "s";
}
if (!empty($search)) {
    $where .= " AND e.title LIKE ?";
    $params[] = "%{$search}%"; $types .= "s";
}

$sql = "SELECT e.*, s.name AS section_name,
               d.code AS dept_code, d.name AS dept_name,
               u.full_name AS added_by_name
        FROM expenses e
        JOIN sections s    ON s.id = e.section_id
        JOIN departments d ON d.id = s.department_id
        JOIN users u       ON u.id = e.added_by
        {$where}
        ORDER BY e.expense_date DESC, e.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $expenses = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$grandTotal = array_sum(array_column($expenses, 'amount'));

$depts = $conn->query(
    "SELECT id, name, code FROM departments ORDER BY name ASC"
)->fetch_all(MYSQLI_ASSOC);

$categories = ['Supplies','Events','Transportation','Food','Printing','Donations','Others'];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt me-2"></i>All Expenses</h1>
        <p>System-wide expense records across all sections</p>
    </div>
</div>

<!-- Filters -->
<div class="lspu-card mb-4">
    <div class="lspu-card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Search Title</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Expense title..." value="<?php echo clean($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Department</label>
                <select name="dept" class="form-select form-select-sm">
                    <option value="0">All Depts</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?php echo $d['id']; ?>"
                            <?php echo $filterDept == $d['id'] ? 'selected':''; ?>>
                            <?php echo clean($d['code']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>"
                            <?php echo $filterCat === $cat ? 'selected':''; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Date From</label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="<?php echo clean($dateFrom); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Date To</label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="<?php echo clean($dateTo); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-lspu btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="all_expenses.php" class="btn btn-outline-secondary btn-sm ms-1">
                    <i class="bi bi-x me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-list-ol"></i></div>
            <div>
                <div class="stat-value"><?php echo count($expenses); ?></div>
                <div class="stat-label">Expense Records Found</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="stat-value"><?php echo formatMoney($grandTotal); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>Expense Records</h5>
        <span class="badge bg-light text-dark"><?php echo count($expenses); ?> records</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Section</th>
                        <th>Dept</th>
                        <th>Category</th>
                        <th>Added By</th>
                        <th>Date</th>
                        <th>Receipt</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>No records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($expenses as $e): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td><strong><?php echo clean($e['title']); ?></strong></td>
                        <td class="small"><?php echo clean($e['section_name']); ?></td>
                        <td>
                            <span class="badge-balance"><?php echo clean($e['dept_code']); ?></span>
                        </td>
                        <td>
                            <span class="category-badge cat-<?php
                                echo strtolower(str_replace(' ','-',$e['category'])); ?>">
                                <?php echo clean($e['category']); ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo clean($e['added_by_name']); ?></td>
                        <td class="small"><?php echo formatDate($e['expense_date']); ?></td>
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
                            <span class="badge-expense"><?php echo formatMoney($e['amount']); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($expenses)): ?>
                <tfoot>
                    <tr style="background:#fff5f5; font-weight:700;">
                        <td colspan="8" class="text-end" style="color:#c0392b;">GRAND TOTAL:</td>
                        <td class="text-end">
                            <span class="badge-expense" style="font-size:13px;">
                                <?php echo formatMoney($grandTotal); ?>
                            </span>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>