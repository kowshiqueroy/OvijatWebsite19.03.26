<?php
/**
 * Admin Order Management & Inventory Control - Premium Glassmorphic Redesign
 */
restrict_to(['admin', 'manager', 'warehouse', 'viewer']);

$user_role = $_SESSION['user_role'];
$success = '';
$error = '';

// Handle Status Changes & Stock Deduction (BLOCKED FOR VIEWER)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $user_role !== 'viewer') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';

    // Fetch current status
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $current_status = $stmt->fetch()['status'] ?? '';

    if ($current_status && $current_status !== $new_status) {
        $pdo->beginTransaction();
        try {
            // Update Order Status
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            // LOGIC: Deduct Stock only when moving to "Payment Verified"
            if ($new_status === 'Payment Verified' && $current_status === 'Pending Payment') {
                $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $items = $stmt->fetchAll();

                foreach ($items as $item) {
                    // 1. Deduct from main product stock
                    $uStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                    $uStmt->execute([$item['qty'], $item['product_id']]);

                    // 2. Deduct from variant stock if applicable
                    if ($item['variant_id']) {
                        $vStmt = $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?");
                        $vStmt->execute([$item['qty'], $item['variant_id']]);
                    }
                }
            }

            // Record in Status History
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $new_status, $_SESSION['user_id'], $notes]);

            $pdo->commit();
            $success = "Order #$order_id updated to $new_status.";
            log_action($pdo, $_SESSION['user_id'], "Order Status Changed", "Order #$order_id: $current_status -> $new_status");

            // Trigger Email Notification
            require_once 'includes/mailer.php';
            $uStmt = $pdo->prepare("SELECT u.email, u.full_name FROM users u JOIN orders o ON u.id = o.user_id WHERE o.id = ?");
            $uStmt->execute([$order_id]);
            $user_data = $uStmt->fetch();
            if ($user_data) {
                $email_body = "<h3>Order Status Update</h3>
                <p>Hello " . e($user_data['full_name']) . ",</p>
                <p>The status of your order <strong>#$order_id</strong> has been updated to: <strong style='color: #3b82f6;'>$new_status</strong></p>
                <p>Notes: " . e($notes) . "</p>
                <p>Log in to your wholesale dashboard to view full details.</p>";
                send_system_email($pdo, $user_data['email'], "Order Update #$order_id: $new_status", $email_body);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update order: " . $e->getMessage();
        }
    }
}

// Filter Logic
$status_filter = $_GET['status'] ?? '';
$query = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id";
if ($status_filter) {
    $query .= " WHERE o.status = " . $pdo->quote($status_filter);
}
$query .= " ORDER BY o.created_at DESC";
$orders = $pdo->query($query)->fetchAll();

$statuses = ['Pending Payment', 'Payment Verified', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
?>

<div class="section-title">
    <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i>
    Order & Fulfillment Ledger
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.1); color: var(--accent); padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.2);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <strong style="color: var(--secondary); margin-right: 1rem;"><i class="fas fa-filter"></i> Filter Status:</strong>
    <a href="/bolakausa/admin/orders" class="btn <?php echo !$status_filter ? 'btn-blue' : ''; ?>" style="padding: 0.5rem 1rem; margin-right: 0.5rem; <?php echo !$status_filter ? '' : 'background: rgba(15,23,42,0.05); color: var(--text-muted); box-shadow: none;'; ?>">All</a>
    <?php foreach ($statuses as $st): ?>
        <a href="/bolakausa/admin/orders?status=<?php echo urlencode($st); ?>" class="btn <?php echo ($status_filter === $st) ? 'btn-blue' : ''; ?>" style="padding: 0.5rem 1rem; margin-right: 0.5rem; margin-bottom: 0.5rem; <?php echo ($status_filter === $st) ? '' : 'background: rgba(15,23,42,0.05); color: var(--text-muted); box-shadow: none;'; ?>">
            <?php echo $st; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order Details</th>
                <th>Client</th>
                <th>Financials</th>
                <th>Payment Route</th>
                <th>Fulfillment State</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td>
                    <strong style="color: var(--secondary); font-size: 1.1rem;">#<?php echo $o['id']; ?></strong><br>
                    <small style="color: var(--text-muted);"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></small>
                </td>
                <td style="font-weight: 700; color: var(--secondary);"><?php echo e($o['username']); ?></td>
                <td>
                    <strong style="color: var(--primary); font-size: 1.1rem;">$<?php echo number_format($o['total_amount'], 2); ?></strong><br>
                    <small style="color: var(--text-muted);">Tax: $<?php echo number_format($o['tax_amount'], 2); ?></small>
                </td>
                <td>
                    <span style="background: rgba(15,23,42,0.05); padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">
                        <?php echo $o['payment_method']; ?>
                    </span>
                    <?php if ($o['payment_details']): ?>
                        <br><small style="color: var(--primary); cursor: pointer;" title='<?php echo e($o['payment_details']); ?>'><i class="fas fa-info-circle"></i> Has Metadata</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                        $bg = 'rgba(15,23,42,0.1)'; $color = 'var(--secondary)';
                        if ($o['status'] === 'Pending Payment') { $bg = 'rgba(244, 63, 94, 0.1)'; $color = 'var(--accent)'; }
                        if ($o['status'] === 'Delivered') { $bg = 'rgba(16, 185, 129, 0.1)'; $color = 'var(--primary)'; }
                        if ($o['status'] === 'Processing') { $bg = 'rgba(59, 130, 246, 0.1)'; $color = '#3b82f6'; }
                    ?>
                    <span style="padding: 6px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 800; background: <?php echo $bg; ?>; color: <?php echo $color; ?>;">
                        <?php echo $o['status']; ?>
                    </span>
                </td>
                <td>
                    <?php if ($user_role !== 'viewer'): ?>
                        <form method="POST" style="display:flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <select name="status" style="padding: 0.5rem; font-size: 0.8rem;">
                                <?php foreach ($statuses as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo ($o['status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" name="notes" placeholder="Admin notes..." style="padding: 0.5rem; font-size: 0.8rem; flex: 1;">
                                <button type="submit" name="update_status" class="btn btn-blue" style="padding: 0.5rem 1rem; font-size: 0.8rem;">Save</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    <a href="/bolakausa/invoice?id=<?php echo $o['id']; ?>" target="_blank" style="font-size: 0.85rem; color: var(--primary); font-weight: 700; text-decoration: none;"><i class="fas fa-file-invoice"></i> View Invoice</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
