<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$pageTitle  = 'Fund Transparency';
$activePage = 'transparency';

$filterDept = (int)($_GET['dept']   ?? 0);
$search     = trim($_GET['search']  ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($filterDept > 0) {
    $where   .= " AND s.department_id = ?";
    $params[] = $filterDept;
    $types   .= "i";
}
if (!empty($search)) {
    $where   .= " AND s.name LIKE ?";
    $params[] = "%{$search}%";
    $types   .= "s";
}

$sql = "SELECT s.id, s.name AS section_name, s.school_year,
               d.name AS dept_name, d.code AS dept_code,
               yl.name AS year_level_name,
               (SELECT COALESCE(SUM(amount),0) FROM funds    WHERE section_id = s.id) AS total_funds,
               (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE section_id = s.id) AS total_expenses,
               (SELECT COUNT(*)                FROM funds    WHERE section_id = s.id) AS fund_count,
               (SELECT COUNT(*)                FROM expenses WHERE section_id = s.id) AS expense_count
        FROM sections s
        JOIN departments d  ON d.id  = s.department_id
        JOIN year_levels yl ON yl.id = s.year_level_id
        {$where}
        ORDER BY d.name ASC, yl.order_num ASC, s.name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $sections = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$depts = $conn->query(
    "SELECT id, name, code FROM departments
     WHERE is_active = 1 ORDER BY name ASC"
)->fetch_all(MYSQLI_ASSOC);

// ── Council records per department ──────────────────────────────────────────
$councilWhere  = "WHERE 1=1";
$councilParams = [];
$councilTypes  = "";

if ($filterDept > 0) {
    $councilWhere   .= " AND d.id = ?";
    $councilParams[] = $filterDept;
    $councilTypes   .= "i";
}
if (!empty($search)) {
    $councilWhere   .= " AND d.name LIKE ?";
    $councilParams[] = "%{$search}%";
    $councilTypes   .= "s";
}

$councilSql = "SELECT d.id AS dept_id, d.name AS dept_name, d.code AS dept_code,
                      (SELECT COALESCE(SUM(amount),0) FROM council_funds    WHERE department_id = d.id) AS total_funds,
                      (SELECT COALESCE(SUM(amount),0) FROM council_expenses WHERE department_id = d.id) AS total_expenses,
                      (SELECT COUNT(*)                FROM council_funds    WHERE department_id = d.id) AS fund_count,
                      (SELECT COUNT(*)                FROM council_expenses WHERE department_id = d.id) AS expense_count
               FROM departments d
               {$councilWhere}
               ORDER BY d.name ASC";

if (!empty($councilParams)) {
    $stmt = $conn->prepare($councilSql);
    $stmt->bind_param($councilTypes, ...$councilParams);
    $stmt->execute();
    $councilDepts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $councilDepts = $conn->query($councilSql)->fetch_all(MYSQLI_ASSOC);
}

// ── Council fund detail records ──────────────────────────────────────────────
$detailWhere  = "WHERE 1=1";
$detailParams = [];
$detailTypes  = "";

if ($filterDept > 0) {
    $detailWhere   .= " AND cf.department_id = ?";
    $detailParams[] = $filterDept;
    $detailTypes   .= "i";
}

$councilFundsSql = "SELECT cf.title, cf.amount, cf.source, cf.fund_date,
                           d.name AS dept_name, d.code AS dept_code,
                           u.full_name AS added_by_name,
                           'fund' AS record_type
                    FROM council_funds cf
                    JOIN departments d ON d.id = cf.department_id
                    JOIN users u       ON u.id = cf.added_by
                    {$detailWhere}
                    ORDER BY cf.fund_date DESC";

$councilExpensesSql = "SELECT ce.title, ce.amount, ce.category AS source, ce.expense_date AS fund_date,
                              d.name AS dept_name, d.code AS dept_code,
                              u.full_name AS added_by_name,
                              'expense' AS record_type
                       FROM council_expenses ce
                       JOIN departments d ON d.id = ce.department_id
                       JOIN users u       ON u.id = ce.added_by
                       {$detailWhere}
                       ORDER BY ce.expense_date DESC";

if (!empty($detailParams)) {
    $stmt = $conn->prepare($councilFundsSql);
    $stmt->bind_param($detailTypes, ...$detailParams);
    $stmt->execute();
    $councilFundRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare($councilExpensesSql);
    $stmt->bind_param($detailTypes, ...$detailParams);
    $stmt->execute();
    $councilExpenseRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $councilFundRows    = $conn->query($councilFundsSql)->fetch_all(MYSQLI_ASSOC);
    $councilExpenseRows = $conn->query($councilExpensesSql)->fetch_all(MYSQLI_ASSOC);
}

// Merge and sort by date descending
$councilRecords = array_merge($councilFundRows, $councilExpenseRows);
usort($councilRecords, fn($a, $b) => strtotime($b['fund_date']) - strtotime($a['fund_date']));

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-eye me-2"></i>Fund Transparency</h1>
        <p>Complete financial overview of all sections — publicly accessible</p>
    </div>
</div>

<div class="lspu-card mb-4">
    <div class="lspu-card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search section or department..."
                           value="<?php echo clean($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="dept" class="form-select">
                    <option value="0">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?php echo $d['id']; ?>"
                            <?php echo $filterDept == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo clean($d['name']); ?> (<?php echo clean($d['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-lspu">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="transparency.php" class="btn btn-outline-secondary ms-1">
                    <i class="bi bi-x me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- SECTION FINANCIAL RECORDS -->
<div class="lspu-card mb-4">
    <div class="lspu-card-header">
        <h5><i class="bi bi-table me-2"></i>Section Financial Records</h5>
        <span class="badge bg-light text-dark"><?php echo count($sections); ?> sections</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Section</th>
                        <th>Department</th>
                        <th>Year Level</th>
                        <th>School Year</th>
                        <th class="text-end">Total Funds</th>
                        <th class="text-end">Total Expenses</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Records</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($sections)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No sections found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($sections as $sec):
                        $balance = $sec['total_funds'] - $sec['total_expenses'];
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td><strong><?php echo clean($sec['section_name']); ?></strong></td>
                        <td>
                            <span class="badge-balance"><?php echo clean($sec['dept_code']); ?></span>
                            <small class="text-muted ms-1"><?php echo clean($sec['dept_name']); ?></small>
                        </td>
                        <td><?php echo clean($sec['year_level_name']); ?></td>
                        <td class="text-muted small"><?php echo clean($sec['school_year']); ?></td>
                        <td class="text-end">
                            <span class="badge-fund"><?php echo formatMoney($sec['total_funds']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="badge-expense"><?php echo formatMoney($sec['total_expenses']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold"
                                  style="color:<?php echo $balance >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                                <?php echo formatMoney($balance); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <small class="text-muted">
                                <i class="bi bi-arrow-up text-success"></i>
                                <?php echo $sec['fund_count']; ?>
                                &nbsp;
                                <i class="bi bi-arrow-down text-danger"></i>
                                <?php echo $sec['expense_count']; ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- COUNCIL SUMMARY PER DEPARTMENT -->
<div class="lspu-card mb-4">
    <div class="lspu-card-header">
        <h5><i class="bi bi-building me-2"></i>Council Financial Summary</h5>
        <span class="badge bg-light text-dark"><?php echo count($councilDepts); ?> departments</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Department</th>
                        <th class="text-end">Council Funds</th>
                        <th class="text-end">Council Expenses</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Records</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($councilDepts)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No council records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($councilDepts as $cd):
                        $bal = $cd['total_funds'] - $cd['total_expenses'];
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <span class="badge-balance"><?php echo clean($cd['dept_code']); ?></span>
                            <strong class="ms-1"><?php echo clean($cd['dept_name']); ?></strong>
                        </td>
                        <td class="text-end">
                            <span class="badge-fund"><?php echo formatMoney($cd['total_funds']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="badge-expense"><?php echo formatMoney($cd['total_expenses']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold"
                                  style="color:<?php echo $bal >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                                <?php echo formatMoney($bal); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <small class="text-muted">
                                <i class="bi bi-arrow-up text-success"></i>
                                <?php echo $cd['fund_count']; ?>
                                &nbsp;
                                <i class="bi bi-arrow-down text-danger"></i>
                                <?php echo $cd['expense_count']; ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- COUNCIL TRANSACTION DETAILS -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-card-list me-2"></i>Council Transaction Records</h5>
        <span class="badge bg-light text-dark"><?php echo count($councilRecords); ?> records</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Department</th>
                        <th>Source / Category</th>
                        <th>Added By</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($councilRecords)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No council transactions found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($councilRecords as $r): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td>
                            <?php if ($r['record_type'] === 'fund'): ?>
                                <span class="badge bg-success-subtle text-success">
                                    <i class="bi bi-arrow-up me-1"></i>Fund
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger">
                                    <i class="bi bi-arrow-down me-1"></i>Expense
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo clean($r['title']); ?></strong></td>
                        <td>
                            <span class="badge-balance"><?php echo clean($r['dept_code']); ?></span>
                            <small class="text-muted ms-1"><?php echo clean($r['dept_name']); ?></small>
                        </td>
                        <td class="text-muted small">
                            <?php echo $r['source'] ? clean($r['source']) : '—'; ?>
                        </td>
                        <td class="text-muted small"><?php echo clean($r['added_by_name']); ?></td>
                        <td class="small"><?php echo formatDate($r['fund_date']); ?></td>
                        <td class="text-end">
                            <?php if ($r['record_type'] === 'fund'): ?>
                                <span class="badge-fund"><?php echo formatMoney($r['amount']); ?></span>
                            <?php else: ?>
                                <span class="badge-expense"><?php echo formatMoney($r['amount']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>