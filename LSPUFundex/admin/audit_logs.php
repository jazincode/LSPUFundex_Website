<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle  = 'Audit Logs';
$activePage = 'audit_logs';

// Pagination
$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Filters
$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user']   ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if (!empty($filterAction)) {
    $where .= " AND al.action = ?";
    $params[] = $filterAction; $types .= "s";
}
if (!empty($filterUser)) {
    $where .= " AND u.full_name LIKE ?";
    $params[] = "%{$filterUser}%"; $types .= "s";
}

// Count total for pagination
$countSql = "SELECT COUNT(*) AS total
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             {$where}";

if (!empty($params)) {
    $cs = $conn->prepare($countSql);
    $cs->bind_param($types, ...$params);
    $cs->execute();
    $totalRecords = $cs->get_result()->fetch_assoc()['total'];
} else {
    $totalRecords = $conn->query($countSql)->fetch_assoc()['total'];
}

$totalPages = ceil($totalRecords / $perPage);

// Fetch logs
$sql = "SELECT al.*, u.full_name
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id
        {$where}
        ORDER BY al.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $logs = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$actions = ['LOGIN','LOGOUT','CREATE','UPDATE','DELETE','RESET_PASSWORD'];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-text me-2"></i>Audit Logs</h1>
        <p>Complete trail of all system actions</p>
    </div>
    <span class="badge bg-secondary"><?php echo number_format($totalRecords); ?> total records</span>
</div>

<!-- Filters -->
<div class="lspu-card mb-4">
    <div class="lspu-card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Search User</label>
                <input type="text" name="user" class="form-control form-control-sm"
                       placeholder="Full name..." value="<?php echo clean($filterUser); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Action Type</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?php echo $a; ?>"
                            <?php echo $filterAction === $a ? 'selected':''; ?>>
                            <?php echo $a; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-lspu btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="audit_logs.php" class="btn btn-outline-secondary btn-sm ms-1">
                    <i class="bi bi-x me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="lspu-card">
    <div class="lspu-card-header">
        <h5><i class="bi bi-list-ul me-2"></i>Activity Log</h5>
        <span class="text-white-50 small">
            Page <?php echo $currentPage; ?> of <?php echo max(1,$totalPages); ?>
        </span>
    </div>
    <div class="lspu-card-body p-0">
        <div class="table-responsive">
            <table class="lspu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>No logs found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $n = $offset + 1; foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted"><?php echo $n++; ?></td>
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
                        <td class="small"><?php echo clean($log['description']); ?></td>
                        <td class="text-muted small"><?php echo clean($log['ip_address'] ?? '—'); ?></td>
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

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?php echo $currentPage <= 1 ? 'disabled':''; ?>">
            <a class="page-link" href="?page=<?php echo $currentPage-1;
                echo $filterAction ? '&action='.$filterAction:'';
                echo $filterUser   ? '&user='.$filterUser:''; ?>">
                &laquo; Prev
            </a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?php echo $p == $currentPage ? 'active':''; ?>">
                <a class="page-link" href="?page=<?php echo $p;
                    echo $filterAction ? '&action='.$filterAction:'';
                    echo $filterUser   ? '&user='.$filterUser:''; ?>">
                    <?php echo $p; ?>
                </a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled':''; ?>">
            <a class="page-link" href="?page=<?php echo $currentPage+1;
                echo $filterAction ? '&action='.$filterAction:'';
                echo $filterUser   ? '&user='.$filterUser:''; ?>">
                Next &raquo;
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>