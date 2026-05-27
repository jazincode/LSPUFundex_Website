<?php
// ============================================
// LSPUFundex - Officer Dashboard
// File: officer/dashboard.php
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

// Get the officer's assigned section
$sectionId = (int)($_SESSION['section_id'] ?? 0);

// If no section assigned, show warning
if ($sectionId <= 0) {
    // Try to get section_id from DB in case session is missing it
    $stmt = $conn->prepare(
        "SELECT section_id FROM users WHERE id = ?"
    );
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $sectionId = (int)($row['section_id'] ?? 0);
    $_SESSION['section_id'] = $sectionId;
}

// Get section details
$section = null;
if ($sectionId > 0) {
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
}

// Financial totals
$totalFunds    = $sectionId > 0 ? getTotalFunds($conn, $sectionId)    : 0;
$totalExpenses = $sectionId > 0 ? getTotalExpenses($conn, $sectionId) : 0;
$balance       = $totalFunds - $totalExpenses;

// Recent funds (last 5)
$recentFunds = [];
if ($sectionId > 0) {
    $stmt = $conn->prepare(
        "SELECT f.*, u.full_name AS added_by_name
         FROM funds f
         JOIN users u ON u.id = f.added_by
         WHERE f.section_id = ?
         ORDER BY f.fund_date DESC, f.created_at DESC
         LIMIT 5"
    );
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    $recentFunds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Recent expenses (last 5)
$recentExpenses = [];
if ($sectionId > 0) {
    $stmt = $conn->prepare(
        "SELECT e.*, u.full_name AS added_by_name
         FROM expenses e
         JOIN users u ON u.id = e.added_by
         WHERE e.section_id = ?
         ORDER BY e.expense_date DESC, e.created_at DESC
         LIMIT 5"
    );
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    $recentExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle  = 'Officer Dashboard';
$activePage = 'officer_dash';

require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-house me-2"></i>Officer Dashboard</h1>
        <p>Welcome back, <strong><?php echo clean($_SESSION['full_name']); ?></strong>!</p>
    </div>
    <?php if ($section): ?>
        <div class="section-badge-lg">
            <i class="bi bi-grid me-2"></i>
            <?php echo clean($section['dept_code']); ?> —
            <?php echo clean($section['year_level_name']); ?> —
            <?php echo clean($section['name']); ?>
        </div>
    <?php endif; ?>
</div>

<?php showFlash(); ?>

<?php if (!$section): ?>
<!-- No Section Warning -->
<div class="alert-box error mb-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>No section assigned.</strong>
    Please contact the administrator to assign you to a section.
</div>
<?php else: ?>

<!-- ============ STAT CARDS ============ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo formatMoney($totalFunds); ?>
                </div>
                <div class="stat-label">Total Funds Collected</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo formatMoney($totalExpenses); ?>
                </div>
                <div class="stat-label">Total Expenses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
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

<!-- ============ RECENT ACTIVITY ============ -->
<div class="row g-4">

    <!-- Recent Funds -->
    <div class="col-lg-6">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-cash-stack me-2"></i>Recent Funds</h5>
                <a href="<?php echo BASE_URL; ?>officer/funds.php"
                   class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="lspu-card-body p-0">
                <?php if (empty($recentFunds)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No funds recorded yet.
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
                                <td>
                                    <small class="fw-semibold">
                                        <?php echo clean(truncate($f['title'], 30)); ?>
                                    </small>
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

    <!-- Recent Expenses -->
    <div class="col-lg-6">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-receipt me-2"></i>Recent Expenses</h5>
                <a href="<?php echo BASE_URL; ?>officer/expenses.php"
                   class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="lspu-card-body p-0">
                <?php if (empty($recentExpenses)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No expenses recorded yet.
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
                                <td>
                                    <small class="fw-semibold">
                                        <?php echo clean(truncate($e['title'], 30)); ?>
                                    </small>
                                </td>
                                <td class="text-muted small">
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
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>