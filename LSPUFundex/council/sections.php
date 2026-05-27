<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireCouncil();

$deptId = (int)($_SESSION['department_id'] ?? 0);
if ($deptId <= 0) {
    redirect(BASE_URL . 'council/dashboard.php');
}

$dept = getById($conn, 'departments', $deptId);

$stmt = $conn->prepare(
    "SELECT s.id, s.name AS section_name, s.school_year,
            yl.name AS year_level_name,
            (SELECT COALESCE(SUM(amount),0) FROM funds    WHERE section_id = s.id) AS total_funds,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE section_id = s.id) AS total_expenses,
            (SELECT COUNT(*)                FROM funds    WHERE section_id = s.id) AS fund_count,
            (SELECT COUNT(*)                FROM expenses WHERE section_id = s.id) AS expense_count
     FROM sections s
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.department_id = ? AND s.is_active = 1
     ORDER BY yl.order_num ASC, s.name ASC"
);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Section Overview';
$activePage = 'council_sections';

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-grid me-2"></i>Section Overview</h1>
        <p>All sections under <strong><?php echo clean($dept['name']); ?></strong></p>
    </div>
</div>

<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>
            <?php echo clean($dept['name']); ?> Sections
        </h5>
        <span class="badge bg-light text-dark"><?php echo count($sections); ?> sections</span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Section</th>
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
                        <td colspan="8" class="text-center text-muted py-4">
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
                        <td><?php echo clean($sec['year_level_name']); ?></td>
                        <td class="text-muted small"><?php echo clean($sec['school_year']); ?></td>
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

<?php require_once '../includes/footer.php'; ?>