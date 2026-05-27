<?php
// ============================================
// Folder Protection File
// Purpose: Prevents direct folder access
// ============================================

// Build the full URL to the homepage dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host     = $_SERVER['HTTP_HOST'];

// Redirect to the main project homepage
header("Location: " . $protocol . "://" . $host . "/LSPUFundex/");
exit();
?>