<?php
// ============================================
// LSPUFundex - AJAX: Get Year Levels
// File: admin/ajax_get_year_levels.php
// Location: C:\xampp\htdocs\LSPUFundex\admin\
//
// PURPOSE:
// Called silently by JavaScript when user
// selects a department. Returns year levels
// as JSON so the dropdown fills automatically.
// ============================================

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only admins can call this
requireAdmin();

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$department_id = (int)($_GET['department_id'] ?? 0);

if ($department_id <= 0) {
    echo json_encode([]);
    exit();
}

// Fetch active year levels for the selected department
$stmt = $conn->prepare(
    "SELECT id, name, order_num
     FROM year_levels
     WHERE department_id = ? AND is_active = 1
     ORDER BY order_num ASC, name ASC"
);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();

$yearLevels = [];
while ($row = $result->fetch_assoc()) {
    $yearLevels[] = [
        'id'   => $row['id'],
        'name' => $row['name']
    ];
}

// Send back as JSON
header('Content-Type: application/json');
echo json_encode($yearLevels);
exit();
?>