<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$pageTitle  = 'Public Dashboard';
$activePage = 'dashboard';

$totalFunds = $conn->query(
    "SELECT COALESCE(SUM(amount),0) AS total FROM funds"
)->fetch_assoc()['total'];

$totalExpenses = $conn->query(
    "SELECT COALESCE(SUM(amount),0) AS total FROM expenses"
)->fetch_assoc()['total'];


$topSections = $conn->query(
    "SELECT s.name AS section_name, d.code AS dept_code,
            COALESCE(SUM(f.amount),0) AS total_funds
     FROM sections s
     JOIN departments d ON d.id = s.department_id
     LEFT JOIN funds f  ON f.section_id = s.id
     GROUP BY s.id ORDER BY total_funds DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$categoryData = $conn->query(
    "SELECT category, COALESCE(SUM(amount),0) AS total
     FROM expenses GROUP BY category ORDER BY total DESC"
)->fetch_all(MYSQLI_ASSOC);

$recentTransactions = $conn->query(
    "SELECT 'fund' AS type, f.title, f.amount, f.fund_date AS date,
             s.name AS section_name, d.code AS dept_code
     FROM funds f
     JOIN sections s    ON s.id = f.section_id
     JOIN departments d ON d.id = s.department_id
     UNION ALL
     SELECT 'expense', e.title, e.amount, e.expense_date,
             s.name, d.code
     FROM expenses e
     JOIN sections s    ON s.id = e.section_id
     JOIN departments d ON d.id = s.department_id
     ORDER BY date DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2"></i>Public Dashboard</h1>
        <p>Live financial overview</p>
    </div>
    <span class="text-muted small">
        <i class="bi bi-clock me-1"></i>Updated: <?php echo date('F d, Y h:i A'); ?>
    </span>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-bar-chart me-2"></i>Top Sections by Funds</h5>
            </div>
            <div class="lspu-card-body">
                <?php if (empty($topSections)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-25"></i>
                        No fund data yet.
                    </div>
                <?php else: ?>
                    <canvas id="fundsBarChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-pie-chart me-2"></i>Expenses by Category</h5>
            </div>
            <div class="lspu-card-body">
                <?php if (empty($categoryData)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-25"></i>
                        No expense data yet.
                    </div>
                <?php else: ?>
                    <canvas id="expenseDoughnut" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
        <a href="<?php echo BASE_URL; ?>public/transparency.php"
           class="btn btn-sm btn-light">View All</a>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Section</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentTransactions)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No transactions recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $tx): ?>
                    <tr>
                        <td>
                            <?php if ($tx['type'] === 'fund'): ?>
                                <span class="badge-fund">
                                    <i class="bi bi-arrow-up-circle me-1"></i>Fund
                                </span>
                            <?php else: ?>
                                <span class="badge-expense">
                                    <i class="bi bi-arrow-down-circle me-1"></i>Expense
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo clean(truncate($tx['title'], 40)); ?></td>
                        <td>
                            <span class="badge-balance">
                                <?php echo clean($tx['dept_code']); ?>
                            </span>
                            <span class="text-muted small ms-1">
                                <?php echo clean($tx['section_name']); ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?php echo formatDate($tx['date']); ?>
                        </td>
                        <td class="text-end">
                            <span class="<?php echo $tx['type'] === 'fund'
                                ? 'badge-fund' : 'badge-expense'; ?>">
                                <?php echo formatMoney($tx['amount']); ?>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($topSections)): ?>
    new Chart(document.getElementById('fundsBarChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(
                fn($s) => $s['dept_code'] . ' - ' . $s['section_name'],
                $topSections
            )); ?>,
            datasets: [{
                label: 'Total Funds (₱)',
                data: <?php echo json_encode(array_map(
                    fn($s) => (float)$s['total_funds'], $topSections
                )); ?>,
                backgroundColor: [
                    'rgba(27,79,114,0.85)','rgba(41,128,185,0.85)',
                    'rgba(39,174,96,0.85)','rgba(243,156,18,0.85)',
                    'rgba(142,68,173,0.85)'
                ],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ₱ ' + ctx.raw.toLocaleString('en-PH',
                            {minimumFractionDigits:2})
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: val => '₱' + val.toLocaleString('en-PH') }
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($categoryData)): ?>
    new Chart(document.getElementById('expenseDoughnut').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($categoryData, 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map(
                    fn($c) => (float)$c['total'], $categoryData
                )); ?>,
                backgroundColor: [
                    '#1b4f72','#2980b9','#27ae60',
                    '#e67e22','#8e44ad','#e74c3c','#7f8c8d'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: {size:12}, padding:12 } },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ₱ ' + ctx.raw.toLocaleString('en-PH',
                            {minimumFractionDigits:2})
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>