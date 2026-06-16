<?php
/**
 * Order List / Tracking for User
 */
restrict_to(['wholesale_user', 'admin', 'manager']);

$user_id = $_SESSION['user_id'];
$is_admin = in_array($_SESSION['user_role'], ['admin', 'manager']);

// Fetch Orders
if ($is_admin) {
    $stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
}
$orders = $stmt->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-box-open" style="color: var(--primary);"></i>
    My Order History
</div>

<?php if (isset($_GET['success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 1rem;">
        <i class="fas fa-check-circle" style="font-size: 2rem;"></i>
        <div>
            <strong style="display: block; font-size: 1.1rem;">Order placed successfully!</strong>
            Order ID: #<?php echo (int)$_GET['id']; ?>
        </div>
    </div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <?php if ($is_admin): ?><th>User</th><?php endif; ?>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$orders): ?>
                <tr><td colspan="<?php echo $is_admin ? 6 : 5; ?>" style="text-align: center; color: var(--text-muted); padding: 3rem;">No orders found.</td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong style="color: var(--secondary);">#<?php echo $o['id']; ?></strong></td>
                <?php if ($is_admin): ?><td><?php echo e($o['username']); ?></td><?php endif; ?>
                <td><small style="color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></small></td>
                <td><strong style="color: var(--primary);">$<?php echo number_format($o['total_amount'], 2); ?></strong></td>
                <td>
                    <?php
                        $bg = 'rgba(15,23,42,0.1)'; $color = 'var(--secondary)';
                        if ($o['status'] === 'Pending Payment') { $bg = 'rgba(244, 63, 94, 0.1)'; $color = 'var(--accent)'; }
                        if ($o['status'] === 'Delivered') { $bg = 'rgba(16, 185, 129, 0.1)'; $color = 'var(--primary)'; }
                        if ($o['status'] === 'Processing') { $bg = 'rgba(59, 130, 246, 0.1)'; $color = '#3b82f6'; }
                    ?>
                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-transform: uppercase;">
                        <?php echo $o['status']; ?>
                    </span>
                </td>
                <td style="text-align: right;">
                    <a href="/bolakausa/invoice?id=<?php echo $o['id']; ?>" class="btn btn-blue" style="padding: 0.5rem 1rem; font-size: 0.8rem;"><i class="fas fa-file-invoice"></i> View Invoice</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p style="margin-top: 2rem;"><a href="/bolakausa/home" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Shop</a></p>
