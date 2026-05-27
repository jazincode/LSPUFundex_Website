<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'Admin Dashboard';
$activePage = 'admin_dash';

$totalUsers    = countAll($conn, 'users');
$totalSections = countAll($conn, 'sections');
$totalDepts    = countAll($conn, 'departments');

$totalFunds = $conn->query(
    "SELECT COALESCE(SUM(amount),0) AS t FROM funds"
)->fetch_assoc()['t'];

$totalExpenses = $conn->query(
    "SELECT COALESCE(SUM(amount),0) AS t FROM expenses"
)->fetch_assoc()['t'];

$totalBalance = $totalFunds - $totalExpenses;

// Funds per department (bar chart)
$deptFunds = $conn->query(
    "SELECT d.name AS dept_name, d.code,
            COALESCE(SUM(f.amount),0) AS total_funds
     FROM departments d
     LEFT JOIN sections s ON s.department_id = d.id
     LEFT JOIN funds    f ON f.section_id    = s.id
     GROUP BY d.id ORDER BY total_funds DESC"
)->fetch_all(MYSQLI_ASSOC);

// Monthly fund trend (last 6 months)
$monthlyFunds = $conn->query(
    "SELECT DATE_FORMAT(fund_date,'%b %Y') AS month_label,
            DATE_FORMAT(fund_date,'%Y-%m')  AS month_key,
            COALESCE(SUM(amount),0)         AS total
     FROM funds
     WHERE fund_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY month_key ORDER BY month_key ASC"
)->fetch_all(MYSQLI_ASSOC);

// Recent audit logs
$recentLogs = $conn->query(
    "SELECT al.*, u.full_name
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</h1>
        <p>Welcome back, <strong><?php echo clean($_SESSION['full_name']); ?></strong>!</p>
    </div>
    <span class="text-muted small">
        <i class="bi bi-clock me-1"></i><?php echo date('F d, Y h:i A'); ?>
    </span>
</div>



<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-bar-chart me-2"></i>Funds per Department</h5>
            </div>
            <div class="lspu-card-body">
                <canvas id="deptChart" height="230"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="lspu-card h-100">
            <div class="lspu-card-header">
                <h5><i class="bi bi-graph-up me-2"></i>Monthly Fund Trend</h5>
            </div>
            <div class="lspu-card-body">
                <?php if (empty($monthlyFunds)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-graph-up fs-1 d-block mb-2 opacity-25"></i>
                        No monthly data yet.
                    </div>
                <?php else: ?>
                    <canvas id="trendChart" height="230"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="row g-3 mb-4">
    <?php
    $links = [
        ['url'=>'departments.php', 'icon'=>'bi-building',    'label'=>'Departments', 'color'=>'blue'],
        ['url'=>'year_levels.php', 'icon'=>'bi-layers',      'label'=>'Year Levels', 'color'=>'green'],
        ['url'=>'sections.php',    'icon'=>'bi-grid',        'label'=>'Sections',    'color'=>'orange'],
        ['url'=>'users.php',       'icon'=>'bi-people',      'label'=>'Users',       'color'=>'blue'],
        ['url'=>'all_funds.php',   'icon'=>'bi-cash-stack',  'label'=>'All Funds',   'color'=>'green'],
        ['url'=>'all_expenses.php','icon'=>'bi-receipt',     'label'=>'All Expenses','color'=>'red'],
        ['url'=>'audit_logs.php',  'icon'=>'bi-journal-text','label'=>'Audit Logs',  'color'=>'orange'],
    ];
    foreach ($links as $l):
    ?>
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?php echo $l['url']; ?>"
           class="stat-card text-decoration-none" style="cursor:pointer;">
            <div class="stat-icon <?php echo $l['color']; ?>">
                <i class="bi <?php echo $l['icon']; ?>"></i>
            </div>
            <div>
                <div class="stat-label fw-semibold" style="font-size:14px; color:#2c3e50;">
                    <?php echo $l['label']; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Audit Logs -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-journal-text me-2"></i>Recent Activity</h5>
        <a href="audit_logs.php" class="btn btn-sm btn-light">View All</a>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No activity yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td class="small fw-semibold">
                            <?php echo clean($log['full_name'] ?? 'System'); ?>
                        </td>
                        <td>
                            <span class="action-badge action-<?php
                                echo strtolower($log['action']); ?>">
                                <?php echo clean($log['action']); ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo clean($log['module']); ?></td>
                        <td class="small"><?php echo clean(truncate($log['description'],60)); ?></td>
                        <td class="text-muted small">
                            <?php echo formatDateTime($log['created_at']); ?>
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

    new Chart(document.getElementById('deptChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($deptFunds, 'code')); ?>,
            datasets: [{
                label: 'Total Funds (₱)',
                data: <?php echo json_encode(array_map(
                    fn($d) => (float)$d['total_funds'], $deptFunds
                )); ?>,
                backgroundColor: 'rgba(27,79,114,0.8)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + v.toLocaleString() }
                }
            }
        }
    });

    <?php if (!empty($monthlyFunds)): ?>
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyFunds, 'month_label')); ?>,
            datasets: [{
                label: 'Monthly Funds (₱)',
                data: <?php echo json_encode(array_map(
                    fn($m) => (float)$m['total'], $monthlyFunds
                )); ?>,
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39,174,96,0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#27ae60'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + v.toLocaleString() }
                }
            }
        }
    });
    <?php endif; ?>

});
</script>

<?php require_once '../includes/footer.php'; ?>