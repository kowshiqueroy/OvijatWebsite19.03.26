<?php
/**
 * Shared Admin Header / Sidebar
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';
require_once __DIR__ . '/../includes/ProductManager.php';
require_once __DIR__ . '/../includes/WalletManager.php';
require_once __DIR__ . '/../includes/WarehouseManager.php';
require_once __DIR__ . '/../includes/CheckoutManager.php';
require_once __DIR__ . '/../includes/StripeClient.php';
require_once __DIR__ . '/../includes/ImageOptimizer.php';
require_once __DIR__ . '/../includes/Logger.php';

AuthManager::requireRole(['admin', 'editor', 'warehouse', 'reports', 'support'], 'gatekeeper.php');

$current_page = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['user_role'] ?? '';

// Role-Based Navigation Logic
$canManageInventory = in_array($userRole, ['admin', 'editor', 'warehouse']);
$canManageOrders = in_array($userRole, ['admin', 'warehouse']);
$canManageMessages = in_array($userRole, ['admin', 'editor', 'support']);
$canManageUsers = in_array($userRole, ['admin']);
$canSeeReports = in_array($userRole, ['admin', 'reports']);
$canManageCMS = in_array($userRole, ['admin', 'editor']);
$canManageSettings = in_array($userRole, ['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Admin'; ?> | Sobjiwali</title>
    <style>
        :root { 
            --primary: #2D5A27; 
            --primary-light: #4A8B42; 
            --text: #2c3e50; 
            --bg: #f4f7f6; 
            --white: #fff; 
            --sidebar: #1a202c; 
            --sidebar-hover: #2d3748;
            --border: #e2e8f0;
            --accent: #F28C28;
            --error: #e53e3e;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); display: flex; font-size: 13px; line-height: 1.5; }
        
        /* Sidebar */
        .sidebar { width: 220px; background: var(--sidebar); color: #fff; height: 100vh; position: fixed; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-brand { padding: 1.5rem; background: rgba(0,0,0,0.2); font-weight: 800; font-size: 1rem; color: #4db6ac; letter-spacing: 1px; }
        .sidebar-nav { flex: 1; padding: 1rem 0.5rem; overflow-y: auto; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 0.8rem 1rem; color: #a0aec0; text-decoration: none; border-radius: 8px; margin-bottom: 2px; font-weight: 600; transition: all 0.2s; }
        .nav-link:hover { background: var(--sidebar-hover); color: #fff; }
        .nav-link.active { background: var(--primary); color: #fff; }
        .nav-sep { height: 1px; background: rgba(255,255,255,0.05); margin: 1rem 0.5rem; }
        
        /* Content Area */
        .admin-main { margin-left: 220px; flex: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .admin-header { height: 60px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 90; }
        .content-body { padding: 2rem; flex: 1; }
        
        /* Typography & Layout */
        h1 { font-size: 1.5rem; font-weight: 800; color: #1a202c; letter-spacing: -0.5px; }
        .card { background: var(--white); border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.02); padding: 1.5rem; margin-bottom: 1.5rem; }
        
        /* Tables - High Density */
        .table-responsive { overflow-x: auto; background: var(--white); border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th { background: #f8fafc; color: #718096; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; padding: 12px 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        td { padding: 10px 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:hover td { background: #f8fafc; }
        
        /* UI Components */
        .badge { font-size: 0.65rem; font-weight: 800; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; cursor: pointer; text-decoration: none; border: 1px solid transparent; transition: all 0.2s; gap: 5px; font-family: inherit; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-outline { background: transparent; border-color: var(--border); color: var(--text); }
        .btn-danger { background: #fff5f5; color: var(--error); border-color: #feb2b2; }
        
        input, select, textarea { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.85rem; font-family: inherit; outline: none; width: 100%; }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1); }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; }
        .alert-success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
        .alert-error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 700; font-size: 0.75rem; color: #4a5568; margin-bottom: 0.4rem; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">SOBJIWALI ADMIN</div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' && !isset($_GET['status']) ? 'active' : ''; ?>">📊 Dashboard</a>
            
            <?php if ($canManageOrders): ?>
            <a href="dashboard.php?status=all" class="nav-link <?php echo $current_page == 'dashboard.php' && isset($_GET['status']) ? 'active' : ''; ?>">📦 Order Management</a>
            <?php endif; ?>

            <?php if ($canManageMessages): ?>
            <a href="support.php" class="nav-link <?php echo $current_page == 'support.php' ? 'active' : ''; ?>">
                💬 Support Hub
                <span id="admin-msg-badge" class="badge" style="background: var(--accent); color: white; margin-left: auto; display: none;">0</span>
            </a>
            <a href="canned_responses.php" class="nav-link <?php echo $current_page == 'canned_responses.php' ? 'active' : ''; ?>">📝 Saved Replies</a>
            <?php endif; ?>

            <?php if ($canManageInventory): ?>            <a href="products.php" class="nav-link <?php echo ($current_page == 'products.php' || $current_page == 'product_edit.php') ? 'active' : ''; ?>">🥦 Inventory</a>
            <a href="categories.php" class="nav-link <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>">📂 Categories</a>
            <?php endif; ?>

            <?php if ($canManageUsers || $canManageInventory): ?><div class="nav-sep"></div><?php endif; ?>

            <?php if ($canManageUsers): ?>
            <a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">👥 User Management</a>
            <?php endif; ?>

            <?php if ($canManageInventory): ?>
            <a href="bulk_import.php" class="nav-link <?php echo $current_page == 'bulk_import.php' ? 'active' : ''; ?>">🚀 Bulk Import</a>
            <?php endif; ?>

            <?php if ($canSeeReports): ?>
            <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">📈 Reports</a>
            <?php endif; ?>

            <?php if ($canManageSettings): ?>
            <a href="tax_shipping.php" class="nav-link <?php echo $current_page == 'tax_shipping.php' ? 'active' : ''; ?>">⚖️ Tax & Shipping</a>
            <?php endif; ?>

            <?php if ($canManageCMS): ?>
            <a href="cms.php" class="nav-link <?php echo $current_page == 'cms.php' ? 'active' : ''; ?>">📝 Static Pages</a>
            <?php endif; ?>

            <?php if ($canManageSettings): ?>
            <div class="nav-sep"></div>
            <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">🎨 Site Settings</a>
            <a href="logs.php" class="nav-link <?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">📜 Audit Trail</a>
            <?php endif; ?>
        </div>
        <div style="padding: 1rem; background: rgba(0,0,0,0.1);">
            <a href="../" target="_blank" style="color:#fff; text-decoration:none; font-size:0.75rem; font-weight:700;">🌐 View Storefront</a>
        </div>
    </div>
    
    <div class="admin-main">
        <header class="admin-header">
            <div style="font-weight:700; color:#718096;"><?php echo date('l, d M Y'); ?></div>
            <div style="display:flex; align-items:center; gap:1.5rem;">
                
                <!-- Notification Bell -->
                <div style="position: relative; cursor: pointer; padding: 10px;" onclick="toggleAdminNotifs()">
                    <span style="font-size: 1.2rem;">🔔</span>
                    <span id="admin-notif-badge" style="position: absolute; top: 0; right: 0; background: var(--error); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.6rem; font-weight: 800; display: none; align-items: center; justify-content: center; border: 2px solid white;">0</span>
                </div>

                <div style="text-align:right;">
                    <div style="font-weight:800; font-size:0.85rem; color:var(--primary);"><?php echo $_SESSION['user_email']; ?></div>
                    <div style="font-size:0.65rem; font-weight:800; opacity:0.5; text-transform:uppercase;"><?php echo str_replace('_', ' ', $userRole); ?></div>
                </div>
                <a href="logout.php" class="btn btn-outline" style="padding:5px 10px;">Logout</a>
            </div>
        </header>

        <!-- Notification Dropdown -->
        <div id="admin-notif-dropdown" style="display: none; position: fixed; top: 70px; right: 2rem; width: 300px; background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); z-index: 1000; overflow: hidden;">
            <div style="padding: 1rem; background: var(--bg); font-weight: 800; font-size: 0.8rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between;">
                <span>Recent Alerts</span>
                <a href="javascript:void(0)" onclick="markAllRead()" style="color: var(--primary); text-decoration: none; font-size: 0.7rem;">Mark all read</a>
            </div>
            <div id="admin-notif-list" style="max-height: 400px; overflow-y: auto;">
                <!-- Notifs load here -->
            </div>
        </div>

        <script>
            async function checkAdminNotifs() {
                try {
                    const res = await fetch('../api_notifications.php?action=get_recent');
                    const data = await res.json();
                    if (data.success) {
                        const badge = document.getElementById('admin-notif-badge');
                        badge.innerText = data.unread_count;
                        badge.style.display = data.unread_count > 0 ? 'flex' : 'none';

                        const list = document.getElementById('admin-notif-list');
                        if (data.notifications.length === 0) {
                            list.innerHTML = '<div style="padding: 2rem; text-align: center; opacity: 0.5; font-size: 0.8rem;">No new alerts.</div>';
                        } else {
                            list.innerHTML = data.notifications.map(n => `
                                <div style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.8rem; ${n.is_read ? 'opacity: 0.6' : 'background: #fdfdfd'}">
                                    <div style="font-weight: 800; color: var(--primary);">${n.title}</div>
                                    <div style="margin-top: 0.2rem;">${n.message}</div>
                                    <small style="opacity: 0.5; font-size: 0.65rem;">${n.created_at}</small>
                                </div>
                            `).join('');
                        }

                        // Also update support badge if visible
                        const msgBadge = document.getElementById('admin-msg-badge');
                        if (msgBadge) {
                            const msgNotifs = data.notifications.filter(n => n.type === 'new_message' && !n.is_read).length;
                            msgBadge.innerText = msgNotifs;
                            msgBadge.style.display = msgNotifs > 0 ? 'inline-block' : 'none';
                        }
                    }
                } catch(e) {}
            }

            function toggleAdminNotifs() {
                const dd = document.getElementById('admin-notif-dropdown');
                dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
            }

            async function markAllRead() {
                await fetch('../api_notifications.php?action=mark_read');
                checkAdminNotifs();
            }

            setInterval(checkAdminNotifs, 30000);
            checkAdminNotifs();
        </script>

        <div class="content-body">