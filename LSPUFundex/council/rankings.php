<?php
// ============================================
// LSPUFundex - Council Rankings
// File: council/rankings.php
// Location: C:\xampp\htdocs\LSPUFundex\council\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireCouncil();

$deptId = (int)($_SESSION['department_id'] ?? 0);
if ($deptId <= 0) {
    redirect(BASE_URL . 'council/dashboard.php');
}

$dept = getById($conn, 'departments', $deptId);

// Sections ranked by highest funds
$topByFunds = $conn->prepare(
    "SELECT s.name AS section_name, yl.name AS year_level_name,
            (SELECT COALESCE(SUM(amount),0) FROM funds WHERE section_id = s.id) AS total_funds
     FROM sections s
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.department_id = ? AND s.is_active = 1
     ORDER BY total_funds DESC"
);
$topByFunds->bind_param("i", $deptId);
$topByFunds->execute();
$topByFunds = $topByFunds->get_result()->fetch_all(MYSQLI_ASSOC);

// Sections ranked by highest balance
$topByBalance = $conn->prepare(
    "SELECT s.name AS section_name, yl.name AS year_level_name,
            (SELECT COALESCE(SUM(amount),0) FROM funds    WHERE section_id = s.id) AS total_funds,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE section_id = s.id) AS total_expenses
     FROM sections s
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.department_id = ? AND s.is_active = 1
     ORDER BY (total_funds - total_expenses) DESC"
);
$topByBalance->bind_param("i", $deptId);
$topByBalance->execute();
$topByBalance = $topByBalance->get_result()->fetch_all(MYSQLI_ASSOC);

// Sections ranked by most transactions
$topByActivity = $conn->prepare(
    "SELECT s.name AS section_name, yl.name AS year_level_name,
            (SELECT COUNT(*) FROM funds    WHERE section_id = s.id) +
            (SELECT COUNT(*) FROM expenses WHERE section_id = s.id) AS total_transactions
     FROM sections s
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.department_id = ? AND s.is_active = 1
     ORDER BY total_transactions DESC"
);
$topByActivity->bind_param("i", $deptId);
$topByActivity->execute();
$topByActivity = $topByActivity->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Rankings';
$activePage = 'council_rankings';

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-trophy me-2"></i>Section Rankings</h1>
        <p>Rankings for all sections under <strong><?php echo clean($dept['name']); ?></strong></p>
    </div>
</div>

<div class="row g-4">

    <!-- Highest Funds -->
    <div class="col-lg-4">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-cash-stack me-2"></i>Highest Funds</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:50px;">Rank</th>
                            <th>Section</th>
                            <th class="text-end">Funds</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topByFunds)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">No data yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topByFunds as $i => $row): ?>
                        <tr>
                            <td class="text-center">
                                <span class="rank-badge" data-rank="<?php echo $i + 1; ?>">
                                    <?php echo $i + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo clean($row['section_name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo clean($row['year_level_name']); ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <span class="badge-fund">
                                    <?php echo formatMoney($row['total_funds']); ?>
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

    <!-- Highest Balance -->
    <div class="col-lg-4">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-wallet2 me-2"></i>Highest Balance</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:50px;">Rank</th>
                            <th>Section</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topByBalance)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">No data yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topByBalance as $i => $row):
                            $bal = $row['total_funds'] - $row['total_expenses'];
                        ?>
                        <tr>
                            <td class="text-center">
                                <span class="rank-badge" data-rank="<?php echo $i + 1; ?>">
                                    <?php echo $i + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo clean($row['section_name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo clean($row['year_level_name']); ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold" style="color:<?php echo $bal >= 0 ? '#27ae60' : '#e74c3c'; ?>">
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

    <!-- Most Active -->
    <div class="col-lg-4">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-lightning me-2"></i>Most Active</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:50px;">Rank</th>
                            <th>Section</th>
                            <th class="text-center">Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topByActivity)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">No data yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topByActivity as $i => $row): ?>
                        <tr>
                            <td class="text-center">
                                <span class="rank-badge" data-rank="<?php echo $i + 1; ?>">
                                    <?php echo $i + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo clean($row['section_name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo clean($row['year_level_name']); ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-1 rounded-pill">
                                    <?php echo $row['total_transactions']; ?>
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

</div>

<!-- ============================================
     CSS FOR RANK NUMBERS (FADED COLORS)
     ============================================ -->
<style>
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 13px;
    color: #fff;
    background: #e9ecef; /* Default gray for ranks 4+ */
}

/* Rank 1 - Full color */
.rank-badge[data-rank="1"] {
    background: #1b4f72;
}

/* Rank 2 - Slightly faded (reduced intensity) */
.rank-badge[data-rank="2"] {
    background: #2874a6;
}

/* Rank 3 - More faded */
.rank-badge[data-rank="3"] {
    background: #5499c7;
}

/* Ranks 4+ - Gray */
.rank-badge[data-rank="4"],
.rank-badge[data-rank="5"],
.rank-badge[data-rank="6"],
.rank-badge[data-rank="7"],
.rank-badge[data-rank="8"],
.rank-badge[data-rank="9"],
.rank-badge[data-rank="10"] {
    background: #e9ecef;
    color: #495057;
}
</style>

<?php require_once '../includes/footer.php'; ?>