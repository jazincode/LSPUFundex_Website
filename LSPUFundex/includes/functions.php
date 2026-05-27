<?php
// ============================================
// LSPUFundex - Global Helper Functions
// File: includes/functions.php
// Location: C:\xampp\htdocs\LSPUFundex\includes\
//
// PURPOSE:
// Contains reusable functions used across
// every page in the system. Include this
// file once in the header and all functions
// become available everywhere.
// ============================================

// --- Prevent Direct Access ---
if (!defined('LSPUFUNDEX')) {
    header("Location: ../index.php");
    exit();
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Sanitize any string input to prevent XSS attacks.
 * Always use this before displaying user-submitted data.
 *
 * Usage: echo clean($userInput);
 */
function clean($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to another page.
 * Usage: redirect('login.php');
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Check if the current user is logged in.
 * Returns true/false.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the current user is an admin.
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if the current user is an officer.
 */
function isOfficer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'officer';
}

function isCouncil() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'council';
}

function requireCouncil() {
    requireLogin();
    if (!isCouncil() && !isAdmin()) {
        redirect('../login.php');
    }
}

/**
 * Require login — redirect to login page if not logged in.
 * Use this at the top of any protected page.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../login.php');
    }
}

/**
 * Require admin role — redirect if not admin.
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect('../login.php');
    }
}

// ============================================
// FORMATTING FUNCTIONS
// ============================================

/**
 * Format a number as Philippine Peso currency.
 * Usage: echo formatMoney(1500.50);
 * Output: ₱ 1,500.50
 */
function formatMoney($amount) {
    return '₱ ' . number_format((float)$amount, 2);
}

/**
 * Format a date into a readable format.
 * Usage: echo formatDate('2024-01-15');
 * Output: January 15, 2024
 */
function formatDate($date) {
    if (empty($date)) return '—';
    return date('F d, Y', strtotime($date));
}

/**
 * Format a datetime into readable format with time.
 * Usage: echo formatDateTime('2024-01-15 14:30:00');
 * Output: January 15, 2024 02:30 PM
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '—';
    return date('F d, Y h:i A', strtotime($datetime));
}

/**
 * Truncate long text to a set number of characters.
 * Usage: echo truncate($longText, 50);
 */
function truncate($text, $limit = 100) {
    if (strlen($text) <= $limit) return $text;
    return substr($text, 0, $limit) . '...';
}

// ============================================
// DATABASE HELPER FUNCTIONS
// ============================================

/**
 * Get a single row from any table by ID.
 * Usage: $dept = getById($conn, 'departments', 1);
 */
function getById($conn, $table, $id) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // sanitize table name
    $stmt  = $conn->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Count all rows in a table.
 * Usage: $total = countAll($conn, 'departments');
 */
function countAll($conn, $table) {
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $result = $conn->query("SELECT COUNT(*) as total FROM `{$table}`");
    return $result->fetch_assoc()['total'];
}

// ============================================
// FINANCIAL FUNCTIONS
// ============================================

/**
 * Get total funds for a section.
 */
function getTotalFunds($conn, $sectionId) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM funds WHERE section_id = ?");
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Get total expenses for a section.
 */
function getTotalExpenses($conn, $sectionId) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE section_id = ?");
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Get remaining balance for a section.
 */
function getBalance($conn, $sectionId) {
    return getTotalFunds($conn, $sectionId) - getTotalExpenses($conn, $sectionId);
}

/**
 * Get total council funds for a department.
 */
function getCouncilFunds($conn, $departmentId) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) as total
         FROM council_funds WHERE department_id = ?"
    );
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Get total council expenses for a department.
 */
function getCouncilExpenses($conn, $departmentId) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) as total
         FROM council_expenses WHERE department_id = ?"
    );
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

/**
 * Get council balance for a department.
 */
function getCouncilBalance($conn, $departmentId) {
    return getCouncilFunds($conn, $departmentId) - getCouncilExpenses($conn, $departmentId);
}

// ============================================
// AUDIT LOG FUNCTION
// ============================================

/**
 * Record an action in the audit log.
 * Usage: logAction($conn, 1, 'CREATE', 'Funds', 'Added ₱500 fund for BSIT 2A');
 */
function logAction($conn, $userId, $action, $module, $description) {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action, module, description, ip_address)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $userId, $action, $module, $description, $ip);
    $stmt->execute();
}

// ============================================
// FLASH MESSAGE FUNCTIONS
// ============================================

/**
 * Set a flash message (shown once, then disappears).
 * Usage: setFlash('success', 'Fund added successfully!');
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Display and clear the flash message.
 * Call this inside the page body.
 */
function showFlash() {
    if (!isset($_SESSION['flash'])) return;

    $type    = $_SESSION['flash']['type'];
    $message = clean($_SESSION['flash']['message']);

    $colors = [
        'success' => ['#d4edda', '#155724', '#28a745'],
        'error'   => ['#f8d7da', '#721c24', '#dc3545'],
        'warning' => ['#fff3cd', '#856404', '#ffc107'],
        'info'    => ['#d1ecf1', '#0c5460', '#17a2b8'],
    ];

    $c = $colors[$type] ?? $colors['info'];

    echo "
    <div style='
        background:{$c[0]};
        color:{$c[1]};
        border:1px solid {$c[2]};
        border-radius:8px;
        padding:14px 20px;
        margin-bottom:20px;
        font-family:Arial;
        display:flex;
        justify-content:space-between;
        align-items:center;
    '>
        <span>{$message}</span>
        <span onclick='this.parentElement.remove()'
              style='cursor:pointer; font-size:18px; opacity:0.6;'>✕</span>
    </div>";

    unset($_SESSION['flash']);
}
?>