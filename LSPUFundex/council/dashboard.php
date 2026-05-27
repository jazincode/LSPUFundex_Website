<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireCouncil();

$deptId = (int)($_SESSION['department_id'] ?? 0);
if ($deptId <= 0) {
    setFlash('error', 'No department assigned. Contact administrator.');
    redirect(BASE_URL . 'login.php');
}

$dept = getById($conn, 'departments', $deptId);

// Council own budget
$councilFunds    = getCouncilFunds($conn, $deptId);
$councilExpenses = getCouncilExpenses($conn, $deptId);
$councilBalance  = $councilFunds - $councilExpenses;

// All sections under this department totals
$sectionStmt = $conn->prepare(
    "SELECT s.id, s.name AS section_name, yl.name AS year_level_name,
            (SELECT COALESCE(SUM(amount),0) FROM funds    WHERE section_id = s.id) AS total_funds,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE section_id = s.id) AS total_expenses
     FROM sections s
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.department_id = ? AND s.is_active = 1
     ORDER BY yl.order_num ASC, s.name ASC"
);
$sectionStmt->bind_param("i", $deptId);
$sectionStmt->execute();
$sections = $sectionStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sectionTotalFunds    = array_sum(array_column($sections, 'total_funds'));
$sectionTotalExpenses = array_sum(array_column($sections, 'total_expenses'));

// Recent council funds
$recentFundsStmt = $conn->prepare(
    "SELECT cf.*, u.full_name AS added_by_name
     FROM council_funds cf
     JOIN users u ON u.id = cf.added_by
     WHERE cf.department_id = ?
     ORDER BY cf.fund_date DESC LIMIT 5"
);
$recentFundsStmt->bind_param("i", $deptId);
$recentFundsStmt->execute();
$recentFunds = $recentFundsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent council expenses
$recentExpStmt = $conn->prepare(
    "SELECT ce.*, u.full_name AS added_by_name
     FROM council_expenses ce
     JOIN users u ON u.id = ce.added_by
     WHERE ce.department_id = ?
     ORDER BY ce.expense_date DESC LIMIT 5"
);
$recentExpStmt->bind_param("i", $deptId);
$recentExpStmt->execute();
$recentExpenses = $recentExpStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Council Dashboard';
$activePage = 'council_dash';

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2"></i>Council Dashboard</h1>
        <p>Welcome, <strong><?php echo clean($_SESSION['full_name']); ?></strong>
           — <?php echo clean($dept['name']); ?> (<?php echo clean($dept['code']); ?>)</p>
    </div>
</div>

<?php showFlash(); ?>

<!-- Council Budget Cards -->
<div class="section-divider mb-2">
    <span>Council Budget</span>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value"><?php echo formatMoney($councilFunds); ?></div>
                <div class="stat-label">Council Funds</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="stat-value"><?php echo formatMoney($councilExpenses); ?></div>
                <div class="stat-label">Council Expenses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon <?php echo $councilBalance >= 0 ? 'green' : 'red'; ?>">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <div class="stat-value"
                     style="color:<?php echo $councilBalance >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                    <?php echo formatMoney($councilBalance); ?>
                </div>
                <div class="stat-label">Council Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Department Section Summary Cards -->
<div class="section-divider mb-2">
    <span>Department Section Summary</span>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
            <div>
                <div class="stat-value"><?php echo count($sections); ?></div>
                <div class="stat-label">Active Sections</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value"><?php echo formatMoney($sectionTotalFunds); ?></div>
                <div class="stat-label">All Sections Total Funds</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="stat-value">
                    <?php echo formatMoney($sectionTotalFunds - $sectionTotalExpenses); ?>
                </div>
                <div class="stat-label">All Sections Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-cash-stack me-2"></i>Recent Council Funds</h5>
                <a href="<?php echo BASE_URL; ?>council/funds.php"
                   class="btn btn-sm btn-light">Manage</a>
            </div>
            <div class="lspu-card-body p-0">
                <?php if (empty($recentFunds)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No council funds yet.
                    </div>
                <?php else: ?>
                    <table class="lspu-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentFunds as $f): ?>
                            <tr>
                                <td class="small fw-semibold">
                                    <?php echo clean(truncate($f['title'], 30)); ?>
                                </td>
                                <td class="text-muted small">
                                    <?php echo formatDate($f['fund_date']); ?>
                                </td>
                                <td class="text-end">
                                    <span class="badge-fund">
                                        <?php echo formatMoney($f['amount']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-receipt me-2"></i>Recent Council Expenses</h5>
                <a href="<?php echo BASE_URL; ?>council/expenses.php"
                   class="btn btn-sm btn-light">Manage</a>
            </div>
            <div class="lspu-card-body p-0">
                <?php if (empty($recentExpenses)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No council expenses yet.
                    </div>
                <?php else: ?>
                    <table class="lspu-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentExpenses as $e): ?>
                            <tr>
                                <td class="small fw-semibold">
                                    <?php echo clean(truncate($e['title'], 30)); ?>
                                </td>
                                <td class="small text-muted">
                                    <?php echo clean($e['category']); ?>
                                </td>
                                <td class="text-end">
                                    <span class="badge-expense">
                                        <?php echo formatMoney($e['amount']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sections Overview -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-grid me-2"></i>
            Sections under <?php echo clean($dept['name']); ?>
        </h5>
        <a href="<?php echo BASE_URL; ?>council/sections.php"
           class="btn btn-sm btn-light">View All</a>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Section</th>
                        <th>Year Level</th>
                        <th class="text-end">Funds</th>
                        <th class="text-end">Expenses</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($sections)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No sections found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($sections as $sec):
                        $bal = $sec['total_funds'] - $sec['total_expenses'];
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td><strong><?php echo clean($sec['section_name']); ?></strong></td>
                        <td><?php echo clean($sec['year_level_name']); ?></td>
                        <td class="text-end">
                            <span class="badge-fund">
                                <?php echo formatMoney($sec['total_funds']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="badge-expense">
                                <?php echo formatMoney($sec['total_expenses']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold"
                                  style="color:<?php echo $bal >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                                <?php echo formatMoney($bal); ?>
                            </span>
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