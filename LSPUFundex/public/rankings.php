<?php
// ============================================
// LSPUFundex - Rankings
// File: public/rankings.php
// Location: C:\xampp\htdocs\LSPUFundex\public\
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$pageTitle  = 'Rankings';
$activePage = 'rankings';

$topByFunds = $conn->query(
    "SELECT s.name AS section_name, d.code AS dept_code,
            yl.name AS year_level_name,
            COALESCE(SUM(f.amount),0) AS total
     FROM sections s
     JOIN departments d  ON d.id  = s.department_id
     JOIN year_levels yl ON yl.id = s.year_level_id
     LEFT JOIN funds f   ON f.section_id = s.id
     GROUP BY s.id ORDER BY total DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$topByBalance = $conn->query(
    "SELECT s.name AS section_name, d.code AS dept_code,
            yl.name AS year_level_name,
            COALESCE(SUM(f.amount),0) - COALESCE(SUM(e.amount),0) AS balance
     FROM sections s
     JOIN departments d  ON d.id  = s.department_id
     JOIN year_levels yl ON yl.id = s.year_level_id
     LEFT JOIN funds    f ON f.section_id = s.id
     LEFT JOIN expenses e ON e.section_id = s.id
     GROUP BY s.id ORDER BY balance DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$topByActivity = $conn->query(
    "SELECT s.name AS section_name, d.code AS dept_code,
            yl.name AS year_level_name,
            COUNT(DISTINCT f.id) + COUNT(DISTINCT e.id) AS total_transactions
     FROM sections s
     JOIN departments d  ON d.id  = s.department_id
     JOIN year_levels yl ON yl.id = s.year_level_id
     LEFT JOIN funds    f ON f.section_id = s.id
     LEFT JOIN expenses e ON e.section_id = s.id
     GROUP BY s.id ORDER BY total_transactions DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$topDepts = $conn->query(
    "SELECT d.name AS dept_name, d.code AS dept_code,
            COALESCE(SUM(f.amount),0) AS total_funds,
            COUNT(DISTINCT s.id) AS section_count
     FROM departments d
     LEFT JOIN sections s ON s.department_id = d.id
     LEFT JOIN funds    f ON f.section_id    = s.id
     GROUP BY d.id ORDER BY total_funds DESC"
)->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-trophy me-2"></i>Rankings</h1>
        <p>Section and department performance rankings</p>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-6">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-cash-stack me-2"></i>Highest Funds Collected</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr><th class="text-center" style="width:50px;">Rank</th><th>Section</th><th class="text-end">Total Funds</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topByFunds)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
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
                                    <?php echo clean($row['dept_code']); ?> —
                                    <?php echo clean($row['year_level_name']); ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <span class="badge-fund"><?php echo formatMoney($row['total']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-wallet2 me-2"></i>Highest Remaining Balance</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr><th class="text-center" style="width:50px;">Rank</th><th>Section</th><th class="text-end">Balance</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topByBalance)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topByBalance as $i => $row): ?>
                        <tr>
                            <td class="text-center">
                                <span class="rank-badge" data-rank="<?php echo $i + 1; ?>">
                                    <?php echo $i + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo clean($row['section_name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo clean($row['dept_code']); ?> —
                                    <?php echo clean($row['year_level_name']); ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold" style="color:<?php echo $row['balance'] >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                                    <?php echo formatMoney($row['balance']); ?>
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

    <div class="col-lg-6">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-lightning me-2"></i>Most Active Sections</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr><th class="text-center" style="width:50px;">Rank</th><th>Section</th><th class="text-center">Transactions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topByActivity)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
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
                                    <?php echo clean($row['dept_code']); ?> —
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

    <div class="col-lg-6">
        <div class="lspu-card">
            <div class="lspu-card-header">
                <h5><i class="bi bi-building me-2"></i>Department Rankings</h5>
            </div>
            <div class="lspu-card-body p-0">
                <table class="lspu-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:50px;">Rank</th>
                            <th>Department</th>
                            <th class="text-center">Sections</th>
                            <th class="text-end">Total Funds</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($topDepts)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No data yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topDepts as $i => $row): ?>
                        <tr>
                            <td class="text-center">
                                <span class="rank-badge" data-rank="<?php echo $i + 1; ?>">
                                    <?php echo $i + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo clean($row['dept_name']); ?></strong><br>
                                <small class="badge-balance"><?php echo clean($row['dept_code']); ?></small>
                            </td>
                            <td class="text-center text-muted"><?php echo $row['section_count']; ?></td>
                            <td class="text-end">
                                <span class="badge-fund"><?php echo formatMoney($row['total_funds']); ?></span>
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

/* Rank 1 - Full color (darkest) */
.rank-badge[data-rank="1"] {
    background: #1b4f72;
}

/* Rank 2 - Slightly faded */
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