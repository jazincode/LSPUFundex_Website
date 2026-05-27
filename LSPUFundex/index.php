<?php
// ============================================
// LSPUFundex - Main Entry Point
// File: index.php
// Location: C:\xampp\htdocs\LSPUFundex\
// Purpose: Redirects to the public dashboard
// ============================================

// Turn on error reporting during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Redirect visitor to the public dashboard
header("Location: public/dashboard.php");
exit();
?>