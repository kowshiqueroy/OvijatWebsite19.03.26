<?php
/**
 * Order List / Tracking for User - Premium Redesign
 */
restrict_to(['wholesale_user', 'admin', 'manager', 'executive']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$is_admin = in_array($user_role, ['admin', 'manager']);
$is_executive = ($user_role === 'executive');

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
                <th>Fulfillment Status</th>
                <th style="text-align: right; width: 150px;">Invoice Receipt</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$orders): ?>
                <tr><td colspan="<?php echo $is_admin ? 6 : 5; ?>" style="text-align: center; color: var(--text-muted); padding: 3rem;">No orders found in this date range. <a href="/bolakausa/orders?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong style="color: var(--secondary);">#<?php echo $o['id']; ?></strong></td>
                <?php if ($is_admin): ?><td><strong style="color: var(--secondary);"><?php echo e($o['username']); ?></strong></td><?php endif; ?>
                <td><small style="color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></small></td>
                <td><strong style="color: var(--primary-dark);">$<?php echo number_format($o['total_amount'], 2); ?></strong></td>
                <td>
                    <?php
                        $bg = 'rgba(15,23,42,0.05)'; $color = 'var(--secondary)';
                        if ($o['status'] === 'Pending Payment') { $bg = 'rgba(244, 63, 94, 0.08)'; $color = 'var(--rose)'; }
                        if ($o['status'] === 'Pending Customer Approval') { $bg = 'rgba(245, 158, 11, 0.15)'; $color = '#d97706'; }
                        if ($o['status'] === 'Delivered') { $bg = 'rgba(16, 185, 129, 0.08)'; $color = 'var(--primary)'; }
                        if ($o['status'] === 'Processing') { $bg = 'rgba(59, 130, 246, 0.08)'; $color = '#3b82f6'; }
                        if ($o['status'] === 'Payment Verified') { $bg = 'rgba(16, 185, 129, 0.08)'; $color = 'var(--primary-dark)'; }
                    ?>
                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);">
                        <?php echo $o['status']; ?>
                    </span>
                </td>
                <td style="text-align: right;">
                    <a href="/bolakausa/invoice?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-outline" style="padding: 0.45rem 1rem; font-size: 0.775rem; border-radius: 6px;"><i class="fas fa-file-invoice"></i> View Invoice</a>
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
                    <th>Fulfillment Status</th>
                    <th style="text-align: right; width: 150px;">Invoice Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$confirmed_orders): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">No confirmed partner orders found in this date range. <a href="/bolakausa/orders?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                <?php endif; ?>
                <?php foreach ($confirmed_orders as $co): ?>
                <tr>
                    <td><strong style="color: var(--secondary);">#<?php echo $co['id']; ?></strong></td>
                    <td><strong style="color: var(--secondary);">@<?php echo e($co['username']); ?></strong></td>
                    <td><small style="color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($co['created_at'])); ?></small></td>
                    <td><strong style="color: var(--primary-dark);">$<?php echo number_format($co['total_amount'], 2); ?></strong></td>
                    <td>
                        <?php
                            $bg = 'rgba(15,23,42,0.05)'; $color = 'var(--secondary)';
                            if ($co['status'] === 'Delivered') { $bg = 'rgba(16, 185, 129, 0.08)'; $color = 'var(--primary)'; }
                            if ($co['status'] === 'Processing') { $bg = 'rgba(59, 130, 246, 0.08)'; $color = '#3b82f6'; }
                            if ($co['status'] === 'Payment Verified') { $bg = 'rgba(16, 185, 129, 0.08)'; $color = 'var(--primary-dark)'; }
                            if ($co['status'] === 'Shipped') { $bg = 'rgba(99, 102, 241, 0.08)'; $color = 'var(--accent)'; }
                        ?>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.02);">
                            <?php echo $co['status']; ?>
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
