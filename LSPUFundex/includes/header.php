<?php
// ============================================
// LSPUFundex - Reusable Header
// File: includes/header.php
// Location: C:\xampp\htdocs\LSPUFundex\includes\
//
// PURPOSE:
// Included at the TOP of every page.
// Loads Bootstrap, CSS, and builds the
// navbar and sidebar navigation.
//
// REQUIRES these variables to be set
// before including this file:
//   $pageTitle  — title shown in browser tab
//   $activePage — highlights current nav item
// ============================================

// Security constant — prevents direct access to includes
// Load app config if not already loaded
if (!defined('LSPUFUNDEX')) {
    require_once __DIR__ . '/../config/app.php';
}

// Load functions if not already loaded
if (!function_exists('clean')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set defaults if not provided
$pageTitle  = $pageTitle  ?? 'LSPUFundex';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($pageTitle); ?> — LSPUFundex</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">

    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/Tab Icon.png">
</head>
<body>

<!-- ============ TOP NAVBAR ============ -->
<nav class="navbar navbar-expand-lg lspu-navbar fixed-top">
    <div class="container-fluid px-4">

       <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>">

        <!-- Logo Image -->
           <img src="<?php echo BASE_URL; ?>assets/images/logo.png"
            alt="LSPUFundex Logo"
            class="brand-logo">

        <div>
            <span class="brand-name">LSPUFundex</span>
            <span class="brand-tagline d-none d-md-block">
            Financial Transparency System
            </span>
        </div>
        </a>

        <!-- Sidebar Toggle — custom JS handles this, NOT Bootstrap collapse -->
        <button type="button" id="sidebarToggle"
                class="btn btn-link text-white p-1 border-0 ms-2"
                aria-label="Toggle sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>

        <!-- Right Side Nav -->
        <div class="ms-auto d-flex align-items-center gap-3">

            <!-- Current Date/Time -->
            <span class="text-white opacity-75 small d-none d-lg-block" id="navClock"></span>

            <?php if (isLoggedIn()): ?>
                <!-- Logged In User Info -->
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-2"
                            type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar-sm">
                            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <span class="d-none d-md-inline"><?php echo clean($_SESSION['full_name'] ?? 'User'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                Logged in as <strong><?php echo clean($_SESSION['role'] ?? ''); ?></strong>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <!-- Not Logged In -->
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-light btn-sm">
                    <i class="bi bi-lock me-1"></i>Officer Login
                </a>
            <?php endif; ?>

        </div>
    </div>
</nav>

<!-- ============ PAGE WRAPPER ============ -->
<div class="page-wrapper">

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-inner">

            <!-- Public Navigation (visible to everyone) -->

            <div class="sidebar-section">
                <span class="sidebar-label">PUBLIC</span>
                <a href="<?php echo BASE_URL; ?>public/dashboard.php"
                   class="sidebar-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>public/transparency.php"
                   class="sidebar-link <?php echo $activePage === 'transparency' ? 'active' : ''; ?>">
                    <i class="bi bi-eye"></i>
                    <span>Fund Transparency</span>
                </a>
                <a href="<?php echo BASE_URL; ?>public/rankings.php"
                   class="sidebar-link <?php echo $activePage === 'rankings' ? 'active' : ''; ?>">
                    <i class="bi bi-trophy"></i>
                    <span>Rankings</span>
                </a>
            </div>

            <?php if (isLoggedIn() && isOfficer()): ?>
            <!-- Officer Navigation -->
            <div class="sidebar-section">
                <span class="sidebar-label">OFFICER</span>
                <a href="<?php echo BASE_URL; ?>officer/dashboard.php"
                   class="sidebar-link <?php echo $activePage === 'officer_dash' ? 'active' : ''; ?>">
                    <i class="bi bi-house"></i>
                    <span>My Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>officer/funds.php"
                   class="sidebar-link <?php echo $activePage === 'funds' ? 'active' : ''; ?>">
                    <i class="bi bi-cash-stack"></i>
                    <span>Manage Funds</span>
                </a>
                <a href="<?php echo BASE_URL; ?>officer/expenses.php"
                   class="sidebar-link <?php echo $activePage === 'expenses' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i>
                    <span>Manage Expenses</span>
                </a>
                <a href="<?php echo BASE_URL; ?>officer/profile.php"
                class="sidebar-link <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i>
                <span>My Profile</span>
                </a>
            </div>
            <?php endif; ?>

           <?php if (isLoggedIn() && isCouncil()): ?>
            <div class="sidebar-section">
                <span class="sidebar-label">COUNCIL</span>
                <a href="<?php echo BASE_URL; ?>council/dashboard.php"
                class="sidebar-link <?php echo $activePage === 'council_dash' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
             <a href="<?php echo BASE_URL; ?>council/funds.php"
             class="sidebar-link <?php echo $activePage === 'council_funds' ? 'active' : ''; ?>">
              <i class="bi bi-cash-stack"></i>
              <span>Council Funds</span>
             </a>
            <a href="<?php echo BASE_URL; ?>council/expenses.php"
              class="sidebar-link <?php echo $activePage === 'council_expenses' ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i>
                 <span>Council Expenses</span>
            </a>
             <a href="<?php echo BASE_URL; ?>council/sections.php"
               class="sidebar-link <?php echo $activePage === 'council_sections' ? 'active' : ''; ?>">
               <i class="bi bi-grid"></i>
               <span>Section Overview</span>
             </a>
            <a href="<?php echo BASE_URL; ?>council/rankings.php"
                 class="sidebar-link <?php echo $activePage === 'council_rankings' ? 'active' : ''; ?>">
                  <i class="bi bi-trophy"></i>
                   <span>Rankings</span>
                   </a>
                 </div>
            <a href="<?php echo BASE_URL; ?>council/profile.php"
                class="sidebar-link <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                  <i class="bi bi-person-circle"></i>
                  <span>My Profile</span>
            </a>
            <?php endif; ?>

            <?php if (isLoggedIn() && isAdmin()): ?>
            <!-- Admin Navigation -->
            <div class="sidebar-section">
                <span class="sidebar-label">ADMIN</span>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php"
                   class="sidebar-link <?php echo $activePage === 'admin_dash' ? 'active' : ''; ?>">
                    <i class="bi bi-house"></i>
                    <span>Admin Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/departments.php"
                   class="sidebar-link <?php echo $activePage === 'departments' ? 'active' : ''; ?>">
                    <i class="bi bi-building"></i>
                    <span>Departments</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/year_levels.php"
                   class="sidebar-link <?php echo $activePage === 'year_levels' ? 'active' : ''; ?>">
                    <i class="bi bi-layers"></i>
                    <span>Year Levels</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/sections.php"
                   class="sidebar-link <?php echo $activePage === 'sections' ? 'active' : ''; ?>">
                    <i class="bi bi-grid"></i>
                    <span>Sections</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/users.php"
                   class="sidebar-link <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/audit_logs.php"
                   class="sidebar-link <?php echo $activePage === 'audit_logs' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Logs</span>
                </a>
            </div>
            <?php endif; ?>

        </div>
    </aside>
    <!-- ============ END SIDEBAR ============ -->

    <!-- ============ MAIN CONTENT AREA ============ -->
    <main class="main-content">
        <div class="content-inner">