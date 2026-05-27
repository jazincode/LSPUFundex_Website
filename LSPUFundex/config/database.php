<?php
// ============================================
// LSPUFundex - Database Connection
// File: config/database.php
// Location: C:\xampp\htdocs\LSPUFundex\config\
//
// PURPOSE:
// This file connects PHP to MySQL.
// Every page that needs database access
// will include this file at the top.
// ============================================

// --- Database Settings ---
define('DB_HOST', 'localhost');   // Always localhost for XAMPP
define('DB_USER', 'root');        // Default XAMPP MySQL username
define('DB_PASS', '');            // Default XAMPP MySQL password (empty)
define('DB_NAME', 'lspufundex'); // Your database name

// --- Create Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Check for Connection Errors ---
if ($conn->connect_error) {
    // Show a friendly error instead of a blank page
    die("
        <div style='
            font-family: Arial, sans-serif;
            background: #fff3f3;
            border: 2px solid #e74c3c;
            border-radius: 8px;
            padding: 30px;
            margin: 50px auto;
            max-width: 500px;
            text-align: center;
        '>
            <h2 style='color:#e74c3c;'>❌ Database Connection Failed</h2>
            <p style='color:#555;'>Error: " . htmlspecialchars($conn->connect_error) . "</p>
            <hr style='margin:15px 0;'>
            <p style='color:#777; font-size:13px;'>
                Make sure MySQL is running in XAMPP Control Panel
                and the database <strong>lspufundex</strong> exists.
            </p>
        </div>
    ");
}

// --- Set Character Encoding ---
$conn->set_charset("utf8mb4");

// --- Optional: Uncomment below during debugging only ---
// echo "Connected successfully to: " . DB_NAME;
?>