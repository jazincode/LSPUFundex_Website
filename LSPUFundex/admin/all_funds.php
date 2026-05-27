<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'All Funds';
$activePage = 'all_funds';

$filterDept = (int)($_GET['dept']   ?? 0);
$dateFrom   = trim($_GET['from']    ?? '');
$dateTo     = trim($_GET['to']      ?? '');
$search     = trim($_GET['search']  ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($filterDept > 0) {
    $where .= " AND s.department_id = ?";
    $params[] = $filterDept; $types .= "i";
}
if (!empty($dateFrom)) {
    $where .= " AND f.fund_date >= ?";
    $params[] = $dateFrom; $types .= "s";
}
if (!empty($dateTo)) {
    $where .= " AND f.fund_date <= ?";
    $params[] = $dateTo; $types .= "s";
}
if (!empty($search)) {
    $where .= " AND f.title LIKE ?";
    $params[] = "%{$search}%"; $types .= "s";
}

$sql = "SELECT f.*, s.name AS section_name,
               d.code AS dept_code, d.name AS dept_name,
               yl.name AS year_level_name,
               u.full_name AS added_by_name
        FROM funds f
        JOIN sections s    ON s.id = f.section_id
        JOIN departments d ON d.id = s.department_id
        JOIN year_levels yl ON yl.id = s.year_level_id
        JOIN users u        ON u.id = f.added_by
        {$where}
        ORDER BY f.fund_date DESC, f.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $funds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $funds = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$grandTotal = array_sum(array_column($funds, 'amount'));

$depts = $conn->query(
    "SELECT id, name, code FROM departments ORDER BY name ASC"
)->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-stack me-2"></i>All Funds</h1>
        <p>System-wide fund records across all sections</p>
    </div>
</div>

<!-- Filters -->
<div class="lspu-card mb-4">
    <div class="lspu-card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Search Title</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Fund title..." value="<?php echo clean($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Department</label>
                <select name="dept" class="form-select form-select-sm">
                    <option value="0">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?php echo $d['id']; ?>"
                            <?php echo $filterDept == $d['id'] ? 'selected':''; ?>>
                            <?php echo clean($d['name']); ?>
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
                <a href="all_funds.php" class="btn btn-outline-secondary btn-sm ms-1">
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
            <div class="stat-icon blue"><i class="bi bi-list-ol"></i></div>
            <div>
                <div class="stat-value"><?php echo count($funds); ?></div>
                <div class="stat-label">Fund Records Found</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
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
        <h5><i class="bi bi-list-ul me-2"></i>Fund Records</h5>
        <span class="badge bg-light text-dark"><?php echo count($funds); ?> records</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Section</th>
                        <th>Department</th>
                        <th>Source</th>
                        <th>Added By</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($funds)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>No records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($funds as $f): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td><strong><?php echo clean($f['title']); ?></strong></td>
                        <td><?php echo clean($f['section_name']); ?></td>
                        <td>
                            <span class="badge-balance"><?php echo clean($f['dept_code']); ?></span>
                        </td>
                        <td class="text-muted small">
                            <?php echo $f['source'] ? clean($f['source']) : '—'; ?>
                        </td>
                        <td class="text-muted small"><?php echo clean($f['added_by_name']); ?></td>
                        <td class="small"><?php echo formatDate($f['fund_date']); ?></td>
                        <td class="text-end">
                            <span class="badge-fund"><?php echo formatMoney($f['amount']); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($funds)): ?>
                <tfoot>
                    <tr style="background:#f0f9ff; font-weight:700;">
                        <td colspan="7" class="text-end" style="color:#1b4f72;">GRAND TOTAL:</td>
                        <td class="text-end">
                            <span class="badge-fund" style="font-size:13px;">
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