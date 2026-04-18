<?php
/**
 * Header Template
 * Core PHP Employee Management System
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - HR System' : 'HR System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
    :root {
            --sidebar-width: 260px;
        }
        html, body {
            min-height: 100vh;
            background-color: #f5f7fa;
            overflow-x: hidden;
        }
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s;
            overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 12px;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
            overflow-x: hidden;
        }
        @media (max-width: 768px) {
            .main-content {
                overflow-x: visible;
            }
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #0d6efd; }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .table-responsive {
            border-radius: 8px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-responsive table {
            min-width: 600px;
        }
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.15);
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .page-header {
            background: #fff;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-active { background: #d1e7dd; color: #0f5132; }
        .badge-inactive { background: #f8d7da; color: #842029; }
        .badge-resigned { background: #fff3cd; color: #664d03; }
        .badge-terminated { background: #f8d7da; color: #842029; }
        .site-footer {
            display: none !important;
        }
        @media print {
            .site-footer {
                display: none !important;
            }
        }
        @media (max-width: 768px) {
            html, body {
                overflow-x: hidden;
            }
            .table-responsive {
                overflow-x: scroll !important;
                -webkit-overflow-scrolling: touch;
            }
            .sidebar {
                display: none;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 260px;
                height: 100vh;
                z-index: 1050;
            }
            .sidebar.show {
                display: block !important;
            }
            .sidebar .nav-link {
                pointer-events: auto;
                cursor: pointer;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <?php if (defined('IS_ADMIN_PAGE') && IS_ADMIN_PAGE): ?>
    <nav class="sidebar d-none d-md-block" id="sidebarNav">
        <div class="p-4 text-center border-bottom border-secondary">
            <h5 class="text-white mb-0">
                <i class="bi bi-building"></i> HR System
            </h5>
            <small class="text-white-50">Employee Management</small>
        </div>
        <ul class="nav flex-column py-3">
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'employees' ? 'active' : ''; ?>" href="employees.php">
                    <i class="bi bi-people"></i> Employees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'salary' ? 'active' : ''; ?>" href="salary-list.php">
                    <i class="bi bi-currency-dollar"></i> Salary Sheets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'salary-generate' ? 'active' : ''; ?>" href="salary-generate.php">
                    <i class="bi bi-calculator"></i> Generate Salary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'loans' ? 'active' : ''; ?>" href="loan.php">
                    <i class="bi bi-cash-stack"></i> Loans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'pf-ledger' ? 'active' : ''; ?>" href="pf-ledger.php">
                    <i class="bi bi-piggy-bank"></i> PF Ledger
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'bonuses' ? 'active' : ''; ?>" href="bonuses.php">
                    <i class="bi bi-gift"></i> Bonuses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'employee-report' ? 'active' : ''; ?>" href="employee-report.php">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Employee Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'users' ? 'active' : ''; ?>" href="user-management.php">
                    <i class="bi bi-shield-lock"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'activity-logs' ? 'active' : ''; ?>" href="activity-logs.php">
                    <i class="bi bi-clock-history"></i> Activity Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'id-cards' ? 'active' : ''; ?>" href="id-cards.php">
                    <i class="bi bi-person-badge"></i> ID Cards
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'settings' ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'photo' ? 'active' : ''; ?>" href="photo-upload.php">
                    <i class="bi bi-camera"></i> Photos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage ?? '') === 'demo' ? 'active' : ''; ?>" href="demo.php">
                    <i class="bi bi-magic"></i> Demo Data
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="../public/profile.php" target="_blank">
                    <i class="bi bi-person-badge"></i> Public Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white d-md-none mb-4 rounded">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <span class="navbar-brand mb-0 h5">HR System</span>
            </div>
        </nav>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var sidebar = document.getElementById('sidebarNav');
                var overlay = document.getElementById('sidebarOverlay');
                var toggler = document.querySelector('.navbar-toggler');
                
                if (toggler && sidebar) {
                    toggler.addEventListener('click', function() {
                        sidebar.classList.toggle('show');
                        overlay.classList.toggle('show');
                    });
                    
                    if (overlay) {
                        overlay.addEventListener('click', function() {
                            sidebar.classList.remove('show');
                            overlay.classList.remove('show');
                        });
                    }
                    
                    document.querySelectorAll('#sidebarNav .nav-link').forEach(function(link) {
                        link.addEventListener('click', function() {
                            if (window.innerWidth < 768) {
                                sidebar.classList.remove('show');
                                overlay.classList.remove('show');
                            }
                        });
                    });
                }
            });
        </script>
    <?php endif; ?>
