<?php
/**
 * Order List / Tracking for User - Premium Redesign
 */
restrict_to(['wholesale_user', 'admin', 'manager', 'executive']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$is_admin = in_array($user_role, ['admin', 'manager']);
$is_executive = ($user_role === 'executive');

// Handle Reorder Request
if (isset($_GET['action']) && $_GET['action'] === 'reorder') {
    $old_order_id = (int)$_GET['order_id'];
    
    // Fetch the target order to clone
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$old_order_id]);
    $old_order = $stmt->fetch();
    
    if ($old_order && ($is_admin || $old_order['user_id'] == $user_id)) {
        header("Location: " . BASE_URL . "orders/edit?id=$old_order_id&action=reorder");
        exit;
    }
}

// Fetch User's Own Orders
// Date Range Filter
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$show_all = isset($_GET['show_all']);

// Fetch User's Own Orders
if ($is_admin) {
    $q_own = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id";
    $own_params = [];
    if (!$show_all) {
        $q_own .= " WHERE DATE(o.created_at) BETWEEN ? AND ?";
        $own_params[] = $date_from;
        $own_params[] = $date_to;
    }
    $q_own .= " ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($q_own);
    $stmt->execute($own_params);
} else {
    $q_own = "SELECT * FROM orders WHERE user_id = ?";
    $own_params = [$user_id];
    if (!$show_all) {
        $q_own .= " AND DATE(created_at) BETWEEN ? AND ?";
        $own_params[] = $date_from;
        $own_params[] = $date_to;
    }
    $q_own .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($q_own);
    $stmt->execute($own_params);
}
$orders = $stmt->fetchAll();

// Fetch Confirmed Partner Orders for Executive (paid or verified other orders)
$confirmed_orders = [];
if ($is_executive) {
    $q_conf = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status IN ('Payment Verified', 'Processing', 'Shipped', 'Delivered') AND o.user_id != ?";
    $conf_params = [$user_id];
    if (!$show_all) {
        $q_conf .= " AND DATE(o.created_at) BETWEEN ? AND ?";
        $conf_params[] = $date_from;
        $conf_params[] = $date_to;
    }
    $q_conf .= " ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($q_conf);
    $stmt->execute($conf_params);
    $confirmed_orders = $stmt->fetchAll();
}
?>

<!-- Date filter -->
<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="url" value="orders">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">From Date</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1; width: 100%;">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">To Date</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1; width: 100%;">
        </div>
        <div style="display: flex; gap: 0.5rem; margin-top: auto;">
            <button type="submit" class="btn btn-blue" style="padding: 0.6rem 1.25rem;"><i class="fas fa-filter"></i> Filter</button>
            <a href="/bolakausa/orders?show_all=1" class="btn btn-outline" style="padding: 0.6rem 1.25rem; font-weight: 700; border-radius: 8px; text-decoration: none;">Show All</a>
        </div>
    </form>
</div>

<div class="section-title">
    <i class="fas fa-box-open" style="color: var(--primary);"></i>
    My Order History
</div>

<?php if (isset($_SESSION['reorder_error_msg'])): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['reorder_error_msg']); unset($_SESSION['reorder_error_msg']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 1rem;">
        <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--primary);"></i>
        <div>
            <strong style="display: block; font-size: 1.1rem; color: var(--secondary);">Order Placed Successfully!</strong>
            Order Reference ID: #<?php echo (int)$_GET['id']; ?>
        </div>
    </div>
<?php endif; ?>

<div class="table-wrap" style="margin-bottom: 4rem;">
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <?php if ($is_admin): ?><th>User</th><?php endif; ?>
                <th>Date Placed</th>
                <th>Grand Total</th>
                <th>Payment Status</th>
                <th>Fulfillment Status</th>
                <th style="text-align: right; width: 150px;">Invoice Receipt</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$orders): ?>
                <tr><td colspan="<?php echo $is_admin ? 7 : 6; ?>" style="text-align: center; color: var(--text-muted); padding: 3rem;">No orders found in this date range. <a href="/bolakausa/orders?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong style="color: var(--secondary);">#<?php echo $o['id']; ?></strong></td>
                <?php if ($is_admin): ?><td><strong style="color: var(--secondary);"><?php echo e($o['username']); ?></strong></td><?php endif; ?>
                <td><small style="color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></small></td>
                <td><strong style="color: var(--primary-dark);">$<?php echo number_format($o['total_amount'], 2); ?></strong></td>
                <td>
                    <?php
                        $p_bg = 'rgba(15,23,42,0.05)'; $p_color = 'var(--secondary)';
                        if ($o['payment_status'] === 'Unpaid') { $p_bg = 'rgba(244, 63, 94, 0.08)'; $p_color = 'var(--rose)'; }
                        elseif ($o['payment_status'] === 'Paid') { $p_bg = 'rgba(16, 185, 129, 0.08)'; $p_color = 'var(--primary-dark)'; }
                        elseif ($o['payment_status'] === 'Refunded') { $p_bg = 'rgba(99, 102, 241, 0.08)'; $p_color = '#4f46e5'; }
                    ?>
                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $p_bg; ?>; color: <?php echo $p_color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);">
                        <?php echo htmlspecialchars($o['payment_status']); ?>
                    </span>
                </td>
                <td>
                    <?php
                        $f_bg = 'rgba(15,23,42,0.05)'; $f_color = 'var(--secondary)';
                        if ($o['fulfillment_status'] === 'Pending') { $f_bg = 'rgba(100, 116, 139, 0.1)'; $f_color = '#475569'; }
                        elseif ($o['fulfillment_status'] === 'Processing') { $f_bg = 'rgba(59, 130, 246, 0.08)'; $f_color = '#3b82f6'; }
                        elseif ($o['fulfillment_status'] === 'Hold') { $f_bg = 'rgba(245, 158, 11, 0.15)'; $f_color = '#d97706'; }
                        elseif ($o['fulfillment_status'] === 'Stock Out') { $f_bg = 'rgba(239, 68, 68, 0.08)'; $f_color = '#ef4444'; }
                        elseif ($o['fulfillment_status'] === 'Ready to Ship') { $f_bg = 'rgba(99, 102, 241, 0.08)'; $f_color = '#4f46e5'; }
                        elseif ($o['fulfillment_status'] === 'Shipped') { $f_bg = 'rgba(139, 92, 246, 0.08)'; $f_color = '#8b5cf6'; }
                        elseif ($o['fulfillment_status'] === 'Out for Delivery') { $f_bg = 'rgba(249, 115, 22, 0.08)'; $f_color = '#f97316'; }
                        elseif ($o['fulfillment_status'] === 'Delivered') { $f_bg = 'rgba(16, 185, 129, 0.08)'; $f_color = 'var(--primary-dark)'; }
                        elseif ($o['fulfillment_status'] === 'Cancelled') { $f_bg = 'rgba(100, 116, 139, 0.08)'; $f_color = '#64748b'; }
                        elseif ($o['fulfillment_status'] === 'Rejected') { $f_bg = 'rgba(239, 68, 68, 0.15)'; $f_color = '#b91c1c'; }
                        elseif ($o['fulfillment_status'] === 'Pending Customer Approval') { $f_bg = 'rgba(245, 158, 11, 0.15)'; $f_color = '#d97706'; }
                    ?>
                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $f_bg; ?>; color: <?php echo $f_color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);">
                        <?php echo htmlspecialchars($o['fulfillment_status']); ?>
                    </span>
                </td>
                <td style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                    <a href="/bolakausa/invoice?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.775rem; border-radius: 6px; white-space: nowrap;"><i class="fas fa-file-invoice"></i> View Invoice</a>
                    <?php if (!$is_admin): ?>
                        <a href="/bolakausa/orders?action=reorder&order_id=<?php echo $o['id']; ?>" class="btn btn-green" style="padding: 0.45rem 1rem; font-size: 0.775rem; border-radius: 6px; white-space: nowrap;"><i class="fas fa-redo"></i> Reorder</a>
                    <?php endif; ?>
                    <?php if (!$is_admin && in_array($o['fulfillment_status'], ['Pending', 'Processing', 'Pending Customer Approval'])): ?>
                        <a href="/bolakausa/orders/edit?id=<?php echo $o['id']; ?>" class="btn btn-blue" style="padding: 0.45rem 1rem; font-size: 0.775rem; border-radius: 6px; white-space: nowrap;"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Executive Queue View: Confirmed Partner Orders -->
<?php if ($is_executive): ?>
    <div class="section-title">
        <i class="fas fa-shipping-fast" style="color: var(--accent);"></i>
        Confirmed Partner Orders
    </div>
    
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Partner Account</th>
                    <th>Date Placed</th>
                    <th>Grand Total</th>
                    <th>Payment Status</th>
                    <th>Fulfillment Status</th>
                    <th style="text-align: right; width: 150px;">Invoice Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$confirmed_orders): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">No confirmed partner orders found in this date range. <a href="/bolakausa/orders?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                <?php endif; ?>
                <?php foreach ($confirmed_orders as $co): ?>
                <tr>
                    <td><strong style="color: var(--secondary);">#<?php echo $co['id']; ?></strong></td>
                    <td><strong style="color: var(--secondary);">@<?php echo e($co['username']); ?></strong></td>
                    <td><small style="color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($co['created_at'])); ?></small></td>
                    <td><strong style="color: var(--primary-dark);">$<?php echo number_format($co['total_amount'], 2); ?></strong></td>
                    <td>
                        <?php
                            $p_bg = 'rgba(15,23,42,0.05)'; $p_color = 'var(--secondary)';
                            if ($co['payment_status'] === 'Unpaid') { $p_bg = 'rgba(244, 63, 94, 0.08)'; $p_color = 'var(--rose)'; }
                            elseif ($co['payment_status'] === 'Paid') { $p_bg = 'rgba(16, 185, 129, 0.08)'; $p_color = 'var(--primary-dark)'; }
                            elseif ($co['payment_status'] === 'Refunded') { $p_bg = 'rgba(99, 102, 241, 0.08)'; $p_color = '#4f46e5'; }
                        ?>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $p_bg; ?>; color: <?php echo $p_color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);">
                            <?php echo htmlspecialchars($co['payment_status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                            $f_bg = 'rgba(15,23,42,0.05)'; $f_color = 'var(--secondary)';
                            if ($co['fulfillment_status'] === 'Pending') { $f_bg = 'rgba(100, 116, 139, 0.1)'; $f_color = '#475569'; }
                            elseif ($co['fulfillment_status'] === 'Processing') { $f_bg = 'rgba(59, 130, 246, 0.08)'; $f_color = '#3b82f6'; }
                            elseif ($co['fulfillment_status'] === 'Hold') { $f_bg = 'rgba(245, 158, 11, 0.15)'; $f_color = '#d97706'; }
                            elseif ($co['fulfillment_status'] === 'Stock Out') { $f_bg = 'rgba(239, 68, 68, 0.08)'; $f_color = '#ef4444'; }
                            elseif ($co['fulfillment_status'] === 'Ready to Ship') { $f_bg = 'rgba(99, 102, 241, 0.08)'; $f_color = '#4f46e5'; }
                            elseif ($co['fulfillment_status'] === 'Shipped') { $f_bg = 'rgba(139, 92, 246, 0.08)'; $f_color = '#8b5cf6'; }
                            elseif ($co['fulfillment_status'] === 'Out for Delivery') { $f_bg = 'rgba(249, 115, 22, 0.08)'; $f_color = '#f97316'; }
                            elseif ($co['fulfillment_status'] === 'Delivered') { $f_bg = 'rgba(16, 185, 129, 0.08)'; $f_color = 'var(--primary-dark)'; }
                            elseif ($co['fulfillment_status'] === 'Cancelled') { $f_bg = 'rgba(100, 116, 139, 0.08)'; $f_color = '#64748b'; }
                            elseif ($co['fulfillment_status'] === 'Rejected') { $f_bg = 'rgba(239, 68, 68, 0.15)'; $f_color = '#b91c1c'; }
                            elseif ($co['fulfillment_status'] === 'Pending Customer Approval') { $f_bg = 'rgba(245, 158, 11, 0.15)'; $f_color = '#d97706'; }
                        ?>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $f_bg; ?>; color: <?php echo $f_color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);">
                            <?php echo htmlspecialchars($co['fulfillment_status']); ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a href="/bolakausa/invoice?id=<?php echo $co['id']; ?>" target="_blank" class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.775rem; border-radius: 6px;"><i class="fas fa-file-invoice"></i> View Invoice</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p style="margin-top: 2rem;"><a href="/bolakausa/home" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Shop</a></p>
