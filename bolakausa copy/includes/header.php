<?php
$header_wallet_balance = 0;
$header_user_email = '';
$unread_notifs = 0;
$notifications_list = [];
$admin_order_attention_count = 0;
$admin_pending_topups_count = 0;
$admin_pending_refunds_count = 0;
$admin_unread_chats_count = 0;
$admin_critical_stock_count = 0;

$company_logo_setting = get_setting($pdo, 'company_logo_url', '');
$base_path_for_logo = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
if (empty($company_logo_setting)) {
    $company_logo = $base_path_for_logo . 'public/images/logo/logoofbolakausa.png';
} else {
    $company_logo = $base_path_for_logo . $company_logo_setting;
}

if (is_logged_in()) {
    $uid = $_SESSION['user_id'];
    $role = get_user_role();
    
    $stmt_email = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt_email->execute([$uid]);
    $header_user_email = $stmt_email->fetchColumn() ?: '';
    
    if (in_array($role, ['wholesale_user', 'executive'])) {
        $stmt_bal = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
        $stmt_bal->execute([$uid]);
        $header_wallet_balance = (float)($stmt_bal->fetch()['balance'] ?? 0);
    }

    if (in_array($role, ['admin', 'manager', 'editor', 'warehouse', 'viewer'])) {
        $stmt_att = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status IN ('Pending Payment', 'Pending Customer Approval', 'Payment Verified')");
        $stmt_att->execute();
        $admin_order_attention_count = (int)$stmt_att->fetchColumn();
        
        $stmt_top = $pdo->prepare("SELECT COUNT(*) FROM wallet_topups WHERE status = 'pending'");
        $stmt_top->execute();
        $admin_pending_topups_count = (int)$stmt_top->fetchColumn();

        $stmt_ref = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'Rejected' AND refund_approved = 0");
        $stmt_ref->execute();
        $admin_pending_refunds_count = (int)$stmt_ref->fetchColumn();
        
        $stmt_cht = $pdo->prepare("SELECT COUNT(*) FROM chats WHERE sender_role = 'wholesale_user' AND is_read = 0 AND is_deleted = 0");
        $stmt_cht->execute();
        $admin_unread_chats_count = (int)$stmt_cht->fetchColumn();
        
        $stmt_stock = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock_qty <= 5 AND is_deleted = 0");
        $stmt_stock->execute();
        $admin_critical_stock_count = (int)$stmt_stock->fetchColumn();
    }
    
    $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_notif->execute([$uid]);
    $unread_notifs = (int)$stmt_notif->fetchColumn();
    
    $stmt_notif_list = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_notif_list->execute([$uid]);
    $notifications_list = $stmt_notif_list->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bolakausa - Wholesale Food & Grocery</title>
    
    <!-- ABSOLUTE PATHS + CACHE BUSTER -->
    <?php $base_path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']); ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>public/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Favicon Links -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_path; ?>public/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_path; ?>public/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $base_path; ?>public/images/favicon/favicon-16x16.png">
    <link rel="shortcut icon" href="<?php echo $base_path; ?>public/images/favicon/favicon.ico">
    <link rel="manifest" href="<?php echo $base_path; ?>public/images/favicon/site.webmanifest">
    
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

        <?php $role = get_user_role(); ?>
<?php if (in_array($role, ['wholesale_user', 'executive'])): ?>
<?php include __DIR__ . '/../modules/products/wholesale_sidebar.php'; ?>
<?php else: ?>
<aside class="sidebar">
            <a href="/bolakausa/home" class="logo" style="margin-bottom: 1.5rem;">
                <img src="<?php echo $company_logo; ?>" alt="Bolakausa Logo" style="max-height: 42px; width: auto; object-fit: contain;">
                <span><span style="color: var(--primary);">Bolaka</span>USA.com</span>
            </a>

            <?php if (is_logged_in()): ?>
            <div style="background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(15, 23, 42, 0.06); padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 2rem;">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo e($header_user_email); ?>">
                    <i class="far fa-envelope" style="color: var(--text-muted); margin-right: 5px;"></i><?php echo e($header_user_email); ?>
                </div>
                <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--primary-dark); margin-top: 0.25rem; display: flex; align-items: center; gap: 4px;">
                    <i class="fas fa-user-shield" style="font-size: 0.65rem;"></i> Role: <?php echo e(str_replace('_', ' ', $role)); ?>
                </div>
            </div>
            <?php endif; ?>

            <nav style="flex: 1; display: flex; flex-direction: column;">
                <ul class="nav-menu">
                    <?php 
                    $role = get_user_role(); 
                    $curr_route = $parts[0] ?? 'home';
                    $curr_sub = $parts[1] ?? '';
                    
                    if (!is_logged_in()): 
                    ?>
                        <li class="<?php echo ($curr_route === 'home' || $curr_route === '') ? 'active' : ''; ?>"><a href="/bolakausa/home"><i class="fas fa-store"></i> Catalog</a></li>
                        <li class="<?php echo ($curr_route === 'login') ? 'active' : ''; ?>"><a href="/bolakausa/login"><i class="fas fa-sign-in-alt"></i> Portal Login</a></li>
                    
                    <?php elseif ($role === 'wholesale_user' || $role === 'executive'): ?>
                        <?php // Wholesale navigation moved to sidebar; removed from top header ?>

                    <?php elseif ($role === 'editor'): ?>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'products') ? 'active' : ''; ?>"><a href="/bolakausa/admin/products"><i class="fas fa-boxes"></i> Catalog Control</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'orders') ? 'active' : ''; ?>"><a href="/bolakausa/admin/orders" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-file-invoice-dollar"></i> Order Management</span><?php if ($admin_order_attention_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $admin_order_attention_count; ?></span><?php endif; ?></a></li>

                    <?php elseif ($role === 'admin'): ?>
                        <li class="<?php echo ($curr_route === 'admin' && ($curr_sub === '' || $curr_sub === 'dashboard')) ? 'active' : ''; ?>"><a href="/bolakausa/admin"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'products') ? 'active' : ''; ?>"><a href="/bolakausa/admin/products"><i class="fas fa-boxes"></i> Catalog Control</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'promotions') ? 'active' : ''; ?>"><a href="/bolakausa/admin/promotions"><i class="fas fa-tags"></i> Coupons & Offers</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'orders') ? 'active' : ''; ?>"><a href="/bolakausa/admin/orders" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-file-invoice-dollar"></i> Order Management</span><?php if ($admin_order_attention_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $admin_order_attention_count; ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'payments') ? 'active' : ''; ?>"><a href="/bolakausa/admin/payments" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-credit-card"></i> Payment Approvals</span><?php if (($admin_pending_topups_count + $admin_pending_refunds_count) > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo ($admin_pending_topups_count + $admin_pending_refunds_count); ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'wallet') ? 'active' : ''; ?>"><a href="/bolakausa/admin/wallet" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-wallet"></i> Finances</span></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'inventory-insights') ? 'active' : ''; ?>"><a href="/bolakausa/admin/inventory-insights" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-warehouse"></i> Inventory Insights</span><?php if ($admin_critical_stock_count > 0): ?><span style="background: #f59e0b; color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><i class="fas fa-exclamation-triangle" style="font-size:0.6rem;"></i> <?php echo $admin_critical_stock_count; ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'manager' && $curr_sub === 'stock') ? 'active' : ''; ?>"><a href="/bolakausa/manager/stock"><i class="fas fa-dolly"></i> Stock Inbound</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'chats') ? 'active' : ''; ?>"><a href="/bolakausa/admin/chats" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-headset"></i> Support Chats</span><?php if ($admin_unread_chats_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $admin_unread_chats_count; ?></span><?php endif; ?></a></li>
                        <li style="margin-top: 1rem; margin-bottom: 0.5rem; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; padding-left: 1rem;">System</li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'users') ? 'active' : ''; ?>"><a href="/bolakausa/admin/users"><i class="fas fa-users-cog"></i> Users</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'settings') ? 'active' : ''; ?>"><a href="/bolakausa/admin/settings"><i class="fas fa-cog"></i> Settings</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'logs') ? 'active' : ''; ?>"><a href="/bolakausa/admin/logs"><i class="fas fa-history"></i> Audit Logs</a></li>

                    <?php elseif ($role === 'manager'): ?>
                        <li class="<?php echo ($curr_route === 'manager' && ($curr_sub === '' || $curr_sub === 'dashboard')) ? 'active' : ''; ?>"><a href="/bolakausa/manager"><i class="fas fa-chart-line"></i> Operations Dashboard</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'products') ? 'active' : ''; ?>"><a href="/bolakausa/admin/products"><i class="fas fa-boxes"></i> Catalog Control</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'promotions') ? 'active' : ''; ?>"><a href="/bolakausa/admin/promotions"><i class="fas fa-tags"></i> Coupons & Offers</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'orders') ? 'active' : ''; ?>"><a href="/bolakausa/admin/orders" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-file-invoice-dollar"></i> Manage Orders</span><?php if ($admin_order_attention_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $admin_order_attention_count; ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'wallet') ? 'active' : ''; ?>"><a href="/bolakausa/admin/wallet" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-wallet"></i> Wallet Management</span></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'inventory-insights') ? 'active' : ''; ?>"><a href="/bolakausa/admin/inventory-insights" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-warehouse"></i> Inventory Insights</span><?php if ($admin_critical_stock_count > 0): ?><span style="background: #f59e0b; color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><i class="fas fa-exclamation-triangle" style="font-size:0.6rem;"></i> <?php echo $admin_critical_stock_count; ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'chats') ? 'active' : ''; ?>"><a href="/bolakausa/admin/chats" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-headset"></i> Support Chats</span><?php if ($admin_unread_chats_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $admin_unread_chats_count; ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'settings') ? 'active' : ''; ?>"><a href="/bolakausa/admin/settings"><i class="fas fa-cog"></i> Settings</a></li>

                    <?php elseif ($role === 'warehouse'): ?>
                        <li class="<?php echo ($curr_route === 'warehouse') ? 'active' : ''; ?>"><a href="/bolakausa/warehouse"><i class="fas fa-clipboard-list"></i> Fulfillment Queue</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'orders') ? 'active' : ''; ?>"><a href="/bolakausa/admin/orders" style="display: flex; align-items: center; justify-content: space-between;"><span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-shipping-fast"></i> Ship Orders</span><?php if ($admin_order_attention_count > 0): ?><span style="background: var(--rose); color: white; font-size: 0.7rem; font-weight: 800; padding: 1px 7px; border-radius: 10px;"><?php echo $admin_order_attention_count; ?></span><?php endif; ?></a></li>
                        <li class="<?php echo ($curr_route === 'manager' && $curr_sub === 'stock') ? 'active' : ''; ?>"><a href="/bolakausa/manager/stock"><i class="fas fa-dolly"></i> Stock Inbound</a></li>

                    <?php elseif ($role === 'viewer'): ?>
                        <li class="<?php echo ($curr_route === 'viewer') ? 'active' : ''; ?>"><a href="/bolakausa/viewer"><i class="fas fa-chart-bar"></i> Financial Overview</a></li>
                        <li class="<?php echo ($curr_route === 'admin' && $curr_sub === 'orders') ? 'active' : ''; ?>"><a href="/bolakausa/admin/orders"><i class="fas fa-book"></i> Order Ledger</a></li>
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
<?php endif; ?>
        
        <div class="main-content">
            <?php
            // Stats loaded early at top of page

            // Cart count & total for header
            $cart_count = 0;
            $cart_total = 0;
            if (!empty($_SESSION['cart'])) {
                $cart_count = array_sum($_SESSION['cart']);
                
                // Group products by ID to minimize SQL calls
                $pids = [];
                foreach ($_SESSION['cart'] as $item_key => $qty) {
                    $parts = explode('_', $item_key);
                    $pid = (int)$parts[0];
                    $pids[$pid] = true;
                }
                
                $ids = array_keys($pids);
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("SELECT id, base_price FROM products WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $products_price = [];
                    foreach ($stmt->fetchAll() as $p) {
                        $products_price[$p['id']] = (float)$p['base_price'];
                    }
                    
                    // Calculate total including variant modifiers & tiers
                    foreach ($_SESSION['cart'] as $item_key => $qty) {
                        $parts = explode('_', $item_key);
                        $pid = (int)$parts[0];
                        $variant_id = (int)($parts[1] ?? 0);
                        
                        if (isset($products_price[$pid])) {
                            $price = $products_price[$pid];
                            
                            // Apply tier pricing if applicable
                            $t_stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
                            $t_stmt->execute([$pid, $qty]);
                            $tier = $t_stmt->fetch();
                            
                            $discount = get_product_discount($pdo, $pid, $uid ?? 0);
                            $base_unit_price = $tier ? (float)$tier['unit_price'] : $price;
                            $price = calculate_discounted_price($base_unit_price, $discount);

                            if ($variant_id > 0) {
                                $vstmt = $pdo->prepare("SELECT price_modifier FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
                                $vstmt->execute([$variant_id, $pid]);
                                $mod = $vstmt->fetchColumn();
                                if ($mod !== false) {
                                    $price += (float)$mod;
                                }
                            }
                            $cart_total += $price * $qty;
                        }
                    }
                }
            }
            ?>
            <header class="top-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: var(--glass-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid var(--glass-border); width: 100%;">
                <div style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.25rem; color: var(--secondary); display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    <a href="/bolakausa/home" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 0.5rem;">
                        <img src="<?php echo $company_logo; ?>" alt="Bolakausa Logo" style="max-height: 36px; width: auto; object-fit: contain;">
                        <span class="header-logo-text"><span style="color: var(--primary);">Bolaka</span>USA.com</span>
                    </a>
                </div>

                <div style="display: flex; align-items: center; gap: 1rem;">
                    <?php if (is_logged_in()): ?>
                        <?php if (in_array($role, ['wholesale_user', 'executive'])): ?>
                            <a href="/bolakausa/wallet" style="text-decoration: none; display: flex; align-items: center; gap: 0.4rem; background: rgba(16,185,129,0.1); color: var(--primary); padding: 0.5rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.85rem; border: 1px solid rgba(16,185,129,0.15); transition: all 0.2s;">
                                <i class="fas fa-wallet"></i>
                                <span class="header-btn-text">$<?php echo number_format($header_wallet_balance, 2); ?></span>
                            </a>
                            <?php if ($cart_count > 0): ?>
                                <a href="/bolakausa/cart" style="text-decoration: none; display: flex; align-items: center; gap: 0.4rem; background: rgba(99,102,241,0.1); color: var(--accent); padding: 0.5rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.85rem; border: 1px solid rgba(99,102,241,0.15); position: relative; transition: all 0.2s;">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span style="position: absolute; top: -6px; right: -6px; background: var(--rose); color: white; border-radius: 50%; font-size: 0.65rem; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 0 6px var(--rose-glow);"><?php echo $cart_count; ?></span>
                                    <span class="header-btn-text">$<?php echo number_format($cart_total, 2); ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="notif-wrapper" style="position: relative;">
                            <button onclick="toggleNotifDropdown(event)" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer; position: relative; padding: 0.5rem; display: flex; align-items: center;" title="View notifications">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_notifs > 0): ?>
                                    <span style="position: absolute; top: 0; right: 0; background: var(--rose); color: white; border-radius: 50%; font-size: 0.65rem; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 0 6px var(--rose-glow);"><?php echo $unread_notifs; ?></span>
                                <?php endif; ?>
                            </button>
                            
                            <div id="notif-dropdown" style="display: none; position: absolute; right: 0; top: 100%; width: 300px; background: white; border: 1px solid var(--border-light); border-radius: 12px; box-shadow: var(--glass-shadow); padding: 1rem; z-index: 1001; margin-top: 0.5rem; text-align: left;">
                                <h4 style="margin: 0 0 0.75rem 0; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 0.9rem; color: var(--secondary); display:flex; justify-content:space-between; align-items:center;">
                                    <span>Notifications</span>
                                    <?php if ($unread_notifs > 0): ?>
                                        <a href="/bolakausa/notifications/mark-read" style="font-size: 0.7rem; color: var(--primary); text-decoration: none; font-weight: 700;">Mark all read</a>
                                    <?php endif; ?>
                                </h4>
                                <div style="max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem;">
                                    <?php if (empty($notifications_list)): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No new alerts.</div>
                                    <?php endif; ?>
                                    <?php foreach ($notifications_list as $n): ?>
                                        <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; opacity: <?php echo $n['is_read'] ? '0.6' : '1'; ?>;">
                                            <strong style="font-size: 0.8rem; color: var(--secondary); display:block;"><?php echo e($n['title']); ?></strong>
                                            <span style="font-size: 0.75rem; color: var(--text-muted); display:block; margin: 0.1rem 0; line-height: 1.3;"><?php echo e($n['message']); ?></span>
                                            <small style="font-size: 0.65rem; color: #94a3b8; display:block; margin-top: 0.15rem;"><i class="far fa-clock"></i> <?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="user-badge" style="margin: 0;">
                            <i class="fas fa-user-circle"></i>
                            <span class="header-user-role-text"><?php echo e(ucwords(str_replace('_', ' ', get_user_role()))); ?> Account</span>
                        </div>
                    <?php else: ?>
                        <div class="user-badge" style="color: var(--text-muted); margin: 0;">
                            <i class="fas fa-globe"></i>
                            <span class="header-user-role-text">Guest Visitor</span>
                        </div>
                    <?php endif; ?>
                </div>
            </header>
            
            <script>
            function toggleNotifDropdown(e) {
                e.stopPropagation();
                const dropdown = document.getElementById('notif-dropdown');
                if (dropdown) {
                    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
                }
            }
            document.addEventListener('click', function(e) {
                const dropdown = document.getElementById('notif-dropdown');
                if (dropdown && dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                }
            });
            </script>
            <main class="container">
