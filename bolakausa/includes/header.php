<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bolakausa - Wholesale Food & Grocery</title>
    
    <!-- ABSOLUTE PATHS + CACHE BUSTER -->
    <?php $base_path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']); ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>public/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Scripts -->
    <script src="<?php echo $base_path; ?>public/js/main.js?v=<?php echo time(); ?>" defer></script>
</head>
<body>
    <div class="app-layout">
        
        <button class="mobile-toggle" aria-label="Toggle Navigation">
            <i class="fas fa-bars"></i>
        </button>

        <aside class="sidebar">
            <?php 
                $comp_name = get_setting($pdo, 'company_name', 'Bolakausa');
                $comp_logo = get_setting($pdo, 'company_logo_url');
                // Split name for styling Bolaka<span>usa</span>
                $name_parts = explode(' ', $comp_name, 2);
                $first_part = $name_parts[0];
                $second_part = $name_parts[1] ?? '';
            ?>
            <a href="/bolakausa/home" class="logo">
                <?php if ($comp_logo): ?>
                    <img src="<?php echo e($comp_logo); ?>" alt="<?php echo e($comp_name); ?>" style="height: 40px; width: auto;">
                <?php else: ?>
                    <i class="fas fa-leaf"></i> 
                    <?php echo e($first_part); ?><?php if($second_part): ?><span><?php echo e($second_part); ?></span><?php endif; ?>
                <?php endif; ?>
            </a>

            <nav style="flex: 1; display: flex; flex-direction: column;">
                <ul class="nav-menu">
                    <?php 
                    $role = get_user_role(); 
                    if (!is_logged_in()): 
                    ?>
                        <li><a href="/bolakausa/home"><i class="fas fa-store"></i> Catalog</a></li>
                        <li><a href="/bolakausa/login"><i class="fas fa-sign-in-alt"></i> Portal Login</a></li>
                    
                    <?php elseif ($role === 'wholesale_user'): ?>
                        <li><a href="/bolakausa/home"><i class="fas fa-store"></i> Catalog</a></li>
                        <li><a href="/bolakausa/cart"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                        <li><a href="/bolakausa/orders"><i class="fas fa-box-open"></i> My Orders</a></li>
                        <li><a href="/bolakausa/wallet"><i class="fas fa-wallet"></i> My Wallet</a></li>
                        <li><a href="/bolakausa/chats"><i class="fas fa-comments"></i> Partner Support</a></li>

                    <?php elseif ($role === 'admin'): ?>
                        <li><a href="/bolakausa/admin"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
                        <li><a href="/bolakausa/admin/products"><i class="fas fa-boxes"></i> Catalog Control</a></li>
                        <li><a href="/bolakausa/admin/orders"><i class="fas fa-file-invoice-dollar"></i> Order Management</a></li>
                        <li><a href="/bolakausa/admin/wallet"><i class="fas fa-wallet"></i> Finances</a></li>
                        <li><a href="/bolakausa/manager/stock"><i class="fas fa-dolly"></i> Stock Inbound</a></li>
                        <li><a href="/bolakausa/admin/chats"><i class="fas fa-headset"></i> Support Chats</a></li>
                        <li style="margin-top: 1rem; margin-bottom: 0.5rem; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; padding-left: 1rem;">System</li>
                        <li><a href="/bolakausa/admin/users"><i class="fas fa-users-cog"></i> Users</a></li>
                        <li><a href="/bolakausa/admin/settings"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="/bolakausa/admin/logs"><i class="fas fa-history"></i> Audit Logs</a></li>

                    <?php elseif ($role === 'manager'): ?>
                        <li><a href="/bolakausa/manager"><i class="fas fa-chart-line"></i> Operations Dashboard</a></li>
                        <li><a href="/bolakausa/admin/orders"><i class="fas fa-file-invoice-dollar"></i> Manage Orders</a></li>
                        <li><a href="/bolakausa/admin/wallet"><i class="fas fa-wallet"></i> Wallet Management</a></li>
                        <li><a href="/bolakausa/admin/chats"><i class="fas fa-headset"></i> Support Chats</a></li>

                    <?php elseif ($role === 'warehouse'): ?>
                        <li><a href="/bolakausa/warehouse"><i class="fas fa-clipboard-list"></i> Fulfillment Queue</a></li>
                        <li><a href="/bolakausa/admin/orders"><i class="fas fa-shipping-fast"></i> Ship Orders</a></li>
                        <li><a href="/bolakausa/manager/stock"><i class="fas fa-dolly"></i> Stock Inbound</a></li>

                    <?php elseif ($role === 'viewer'): ?>
                        <li><a href="/bolakausa/viewer"><i class="fas fa-chart-bar"></i> Financial Overview</a></li>
                        <li><a href="/bolakausa/admin/orders"><i class="fas fa-book"></i> Order Ledger</a></li>
                    <?php endif; ?>
                </ul>

                <?php if (is_logged_in()): ?>
                    <div style="margin-top: auto;">
                        <a href="/bolakausa/logout" class="btn btn-red" style="width: 100%; justify-content: center;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php else: ?>
                    <div style="margin-top: auto;">
                        <a href="/bolakausa/register" class="btn btn-green" style="width: 100%; justify-content: center;">
                            <i class="fas fa-handshake"></i> Join Wholesale
                        </a>
                    </div>
                <?php endif; ?>
            </nav>
        </aside>
        
        <div class="main-content">
            <header class="top-header">
                <?php if (is_logged_in()): ?>
                    <div class="user-badge">
                        <i class="fas fa-user-circle"></i>
                        <?php echo e(ucwords(str_replace('_', ' ', get_user_role()))); ?> Account
                    </div>
                <?php else: ?>
                    <div class="user-badge" style="color: var(--text-muted);">
                        <i class="fas fa-globe"></i> Guest Visitor
                    </div>
                <?php endif; ?>
            </header>
            <main class="container">
