<?php
// ============================================
// LSPUFundex - Logout
// File: logout.php
// Location: C:\xampp\htdocs\LSPUFundex\
// ============================================

require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Only log the action if someone was actually logged in
if (isLoggedIn()) {
    logAction(
        $conn,
        $_SESSION['user_id'],
        'LOGOUT',
        'Auth',
        ($_SESSION['full_name'] ?? 'User') . ' logged out.'
    );
}

// Destroy the session completely
$_SESSION = [];
session_destroy();

// Redirect to login page
redirect(BASE_URL . 'login.php');
?>