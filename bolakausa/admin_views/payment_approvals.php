<?php
/**
 * Dedicated Admin Payment Approvals Gate (Administrators Only)
 */
restrict_to(['admin']);

$success = '';
$error = '';

// 1. Handle Incoming Bank/Stripe Payment Approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_topup'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action']; // 'approved' or 'rejected'
    $notes = trim($_POST['admin_notes'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM wallet_topups WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if ($request) {
        $pdo->beginTransaction();
        try {
            // Update request status
            $stmt = $pdo->prepare("UPDATE wallet_topups SET status = ?, admin_notes = ?, processed_at = NOW() WHERE id = ?");
            $stmt->execute([$action, $notes, $request_id]);

            if ($action === 'approved') {
                // Add balance to wallet
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt->execute([$request['user_id'], $request['amount'], "Wallet Top-up: Approved Request #$request_id"]);
                
                log_action($pdo, $_SESSION['user_id'], "Wallet Top-up Approved", "User ID: {$request['user_id']}, Amount: {$request['amount']}");

                // If linked to an order, automatically update order status to Payment Verified
                if (!empty($request['order_id'])) {
                    $order_id = $request['order_id'];
                    $o_stmt = $pdo->prepare("SELECT total_amount, status, delivery_address, shipping_amount, tax_amount FROM orders WHERE id = ?");
                    $o_stmt->execute([$order_id]);
                    $order = $o_stmt->fetch();
                    
                    if ($order && ($order['status'] === 'Pending Payment' || $order['status'] === 'Pending Customer Approval')) {
                        $old_total = (float)$order['total_amount'];
                        $order_total = $old_total;
                        
                        if ($order['status'] === 'Pending Customer Approval') {
                            // Apply proposed changes first
                            $q_det = $pdo->prepare("SELECT pending_change_details FROM orders WHERE id = ?");
                            $q_det->execute([$order_id]);
                            $change_details_raw = $q_det->fetchColumn();
                            if ($change_details_raw) {
                                $change_details = json_decode($change_details_raw, true);
                                if ($change_details) {
                                    // Restore the current order stock temporarily
                                    require_once __DIR__ . '/../includes/auth_helper.php';
                                    restore_order_stock($pdo, $order_id);

                                    // Apply items
                                    if (isset($change_details['items']) && is_array($change_details['items'])) {
                                        foreach ($change_details['items'] as $item) {
                                            $item_id = (int)$item['item_id'];
                                            $qty = (int)$item['qty'];
                                            $item_disc = (float)($item['admin_adjusted_discount'] ?? 0);
                                            if ($qty <= 0) {
                                                $stmt_item = $pdo->prepare("UPDATE order_items SET is_deleted = 1 WHERE id = ?");
                                                $stmt_item->execute([$item_id]);
                                            } else {
                                                $stmt_item = $pdo->prepare("UPDATE order_items SET qty = ?, admin_adjusted_discount = ? WHERE id = ?");
                                                $stmt_item->execute([$qty, $item_disc, $item_id]);
                                            }
                                        }
                                    }
                                    // Set proposed values
                                    $order_total = (float)($change_details['total_amount'] ?? $order['total_amount']);
                                    $u_stmt = $pdo->prepare("UPDATE orders SET delivery_address = ?, shipping_amount = ?, tax_amount = ?, total_amount = ?, admin_adjusted_discount = ?, pending_change_details = NULL WHERE id = ?");
                                    $u_stmt->execute([
                                        $change_details['delivery_address'] ?? $order['delivery_address'],
                                        (float)($change_details['shipping_amount'] ?? $order['shipping_amount']),
                                        (float)($change_details['tax_amount'] ?? $order['tax_amount']),
                                        $order_total,
                                        (float)($change_details['admin_adjusted_discount'] ?? 0),
                                        $order_id
                                    ]);
                                    
                                    // Re-deduct the stock
                                    deduct_order_stock($pdo, $order_id);

                                    // Debit/credit the difference (shortfall) in the wallet
                                    $shortfall = $order_total - $old_total;
                                    if ($shortfall > 0) {
                                        $d_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
                                        $d_stmt->execute([$request['user_id'], $shortfall, "Shortfall adjustment for Order #$order_id changes"]);
                                    } elseif ($shortfall < 0) {
                                        $refund_amt = abs($shortfall);
                                        $c_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                                        $c_stmt->execute([$request['user_id'], $refund_amt, "Wallet credit for reduced total of Order #$order_id"]);
                                    }
                                }
                            }
                        }
                        
                        // Note: stock is already deducted at checkout. Wallet debit for original total is also at checkout.
                        
                        // Update order status to Payment Verified
                        $u_stmt = $pdo->prepare("UPDATE orders SET status = 'Payment Verified', payment_status = 'Paid', fulfillment_status = 'Pending' WHERE id = ?");
                        $u_stmt->execute([$order_id]);
                        
                        // Add Order History record
                        $h_stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Payment Verified', ?, 'Payment verified and credited to wallet balance')");
                        $h_stmt->execute([$order_id, $_SESSION['user_id']]);
                        
                        // Add Customer Notification
                        $n_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Payment Verified #$order_id', 'Your payment for Order #$order_id has been verified. The order is now being processed.')");
                        $n_stmt->execute([$request['user_id']]);

                        // Send the verification invoice email
                        require_once __DIR__ . '/../includes/mailer.php';
                        send_invoice_email($pdo, $order_id, "Payment Approved / Order Confirmed");
                    }
                }
            } else {
                log_action($pdo, $_SESSION['user_id'], "Wallet Top-up Rejected", "User ID: {$request['user_id']}, Request ID: $request_id");
            }

            $pdo->commit();
            $success = "Payment request has been successfully " . $action . ".";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to process payment approval: " . $e->getMessage();
        }
    }
}

// 2. Handle Wallet Refund Approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_refund'])) {
    $order_id = (int)$_POST['order_id'];
    $rejection_charge = max(0, (float)($_POST['rejection_charge'] ?? 0));

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order && $order['fulfillment_status'] === 'Rejected' && !$order['refund_approved']) {
        $net_refund = $order['total_amount'] - $rejection_charge;
        if ($net_refund < 0) $net_refund = 0;

        $pdo->beginTransaction();
        try {
            // Update order details
            $stmt = $pdo->prepare("UPDATE orders SET rejection_charge = ?, refund_approved = 1, payment_status = 'Refunded' WHERE id = ?");
            $stmt->execute([$rejection_charge, $order_id]);

            // Credit wallet with net refund
            if ($net_refund > 0) {
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt->execute([$order['user_id'], $net_refund, "Refund for Rejected Order #$order_id (Net of $$rejection_charge rejection fee)"]);
            }

            // Log Status History
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Rejected', ?, ?)");
            $stmt->execute([$order_id, $_SESSION['user_id'], "Admin Refund Approved. Rejection charge: $$rejection_charge. Net Refunded: $$net_refund."]);

            // Add notification
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$order['user_id'], "Order Refund Issued", "A wallet refund of $" . number_format($net_refund, 2) . " has been approved for rejected Order #$order_id."]);

            $pdo->commit();
            $success = "Refund of $" . number_format($net_refund, 2) . " approved for Order #$order_id (Rejection Charge: $" . number_format($rejection_charge, 2) . ").";
            log_action($pdo, $_SESSION['user_id'], "Refund Approved", "Order #$order_id: Charge: $rejection_charge, Net: $net_refund");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to approve refund: " . $e->getMessage();
        }
    }
}

// 3. Fetch Pending Payments & Refunds
$pending_topups = $pdo->query("SELECT t.*, u.username, u.full_name FROM wallet_topups t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending' ORDER BY t.created_at DESC")->fetchAll();
$pending_refunds = $pdo->query("SELECT o.*, u.username, u.full_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.fulfillment_status = 'Rejected' AND o.refund_approved = 0 ORDER BY o.created_at DESC")->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-credit-card" style="color: var(--primary);"></i>
    Payment Approvals Gate
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

<!-- TAB NAVIGATION -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--glass-border); padding-bottom: 1rem; flex-wrap: wrap;">
    <button onclick="switchTab('tab-topups')" id="btn-topups" class="btn btn-blue" style="background: var(--primary);"><i class="fas fa-handholding-usd"></i> Incoming Bank/Stripe Payments (<?php echo count($pending_topups); ?>)</button>
    <button onclick="switchTab('tab-refunds')" id="btn-refunds" class="btn btn-blue" style="background: rgba(15,23,42,0.05); color: var(--secondary); box-shadow: none;"><i class="fas fa-undo"></i> Pending Refund Approvals (<?php echo count($pending_refunds); ?>)</button>
</div>

<!-- TAB CONTENT: Incoming Payments -->
<div id="tab-topups" class="tab-content" style="display: block;">
    <div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
        <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-sign-in-alt" style="color: var(--primary);"></i> Incoming Bank Wire & Stripe Verifications</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">Wholesalers pay direct order invoices or wallet top-ups using these offline methods. Admins must verify incoming funds before approving.</p>
        
        <div class="table-wrap" style="margin: 0;">
            <table>
                <thead>
                    <tr>
                        <th>User & Amount</th>
                        <th>Details</th>
                        <th style="width: 320px;">Verification Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pending_topups): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 3rem;">No pending bank/stripe payments.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending_topups as $t): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--secondary);"><?php echo e($t['full_name'] ?: $t['username']); ?></strong><br>
                            <small style="color: var(--text-muted);">@<?php echo e($t['username']); ?></small><br>
                            <strong style="color: var(--primary); font-size: 1.15rem; display: block; margin-top: 0.25rem;">$<?php echo number_format($t['amount'], 2); ?></strong>
                        </td>
                        <td>
                            <small style="color: var(--text-muted);">Method:</small> <strong><?php echo e($t['payment_method']); ?></strong><br>
                            <small style="color: var(--text-muted);">Tx ID Reference:</small> <code style="background: rgba(15,23,42,0.05); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;"><?php echo e($t['transaction_id']); ?></code><br>
                            <small style="color: var(--text-muted);">Submitted:</small> <?php echo date('M d, Y H:i', strtotime($t['created_at'])); ?><br>
                            <?php if ($t['proof_image']): ?>
                                <a href="/bolakausa/public/uploads/proofs/<?php echo e($t['proof_image']); ?>" target="_blank" style="font-size: 0.825rem; color: var(--primary); font-weight: 800; display: inline-block; margin-top: 0.25rem;"><i class="fas fa-image"></i> View Receipt Proof</a>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem; display: inline-block; margin-top: 0.25rem;"><i class="fas fa-times-circle"></i> No Receipt File</span>
                            <?php endif; ?>
                            <?php if ($t['order_id']): ?>
                                <div style="margin-top: 0.5rem; background: rgba(59, 130, 246, 0.05); padding: 6px 10px; border-radius: 6px; border: 1px dashed rgba(59, 130, 246, 0.2);">
                                    <small style="color: var(--text-muted);">Linked Order:</small> 
                                    <a href="/bolakausa/admin/orders?search=<?php echo $t['order_id']; ?>" style="font-weight: 800; color: var(--primary); text-decoration: none;">Order #<?php echo $t['order_id']; ?></a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; flex-direction: column; gap: 0.5rem; background: rgba(15,23,42,0.02); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-light);">
                                <input type="hidden" name="request_id" value="<?php echo $t['id']; ?>">
                                <div style="display: flex; gap: 0.5rem;">
                                    <select name="action" style="padding: 0.45rem; font-size: 0.85rem; flex: 1; border-radius: 6px;">
                                        <option value="approved">Approve & Credit</option>
                                        <option value="rejected">Reject Request</option>
                                    </select>
                                    <button type="submit" name="process_topup" value="1" class="btn btn-green" style="padding: 0.45rem 1rem; font-size: 0.85rem; border-radius: 6px;"><i class="fas fa-check"></i> Apply</button>
                                </div>
                                <input type="text" name="admin_notes" placeholder="Approval/rejection notes..." style="padding: 0.45rem; font-size: 0.8rem; border-radius: 6px; width: 100%;">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB CONTENT: Refund Approvals -->
<div id="tab-refunds" class="tab-content" style="display: none;">
    <div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
        <h3 style="font-weight: 800; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-undo" style="color: var(--accent);"></i> Wallet Refund Approval Gate</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">Rejected B2B orders with pre-paid wallet amounts are listed here. Admins must confirm the refund amount and optional rejection fee deduction.</p>
        
        <div class="table-wrap" style="margin: 0;">
            <table>
                <thead>
                    <tr>
                        <th>Order & Wholesaler</th>
                        <th>Financial Details</th>
                        <th style="width: 320px;">Refund Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pending_refunds): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 3rem;">No pending refund approvals.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending_refunds as $o): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--secondary);">Order #<?php echo $o['id']; ?></strong><br>
                            <small style="color: var(--text-muted);">Customer: <?php echo e($o['full_name'] ?: $o['username']); ?> (@<?php echo e($o['username']); ?>)</small><br>
                            <small style="color: var(--text-muted);"><i class="far fa-calendar-alt"></i> Rejected on: <?php echo date('M d, Y', strtotime($o['created_at'])); ?></small>
                        </td>
                        <td>
                            <small style="color: var(--text-muted);">Payment Method:</small> <strong><?php echo e($o['payment_method']); ?></strong><br>
                            <small style="color: var(--text-muted);">Order Total:</small> <strong style="color: var(--secondary); font-size: 1.05rem;">$<?php echo number_format($o['total_amount'], 2); ?></strong>
                        </td>
                        <td>
                            <form method="POST" style="background: rgba(15,23,42,0.02); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-light);">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <div style="margin-bottom: 0.5rem;">
                                    <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.25rem;">Rejection Fee ($)</label>
                                    <input type="number" step="0.01" name="rejection_charge" value="0.00" min="0" max="<?php echo $o['total_amount']; ?>" style="padding: 0.45rem; font-size: 0.85rem; border-radius: 6px; width: 100%; border: 1px solid #cbd5e1;">
                                </div>
                                <button type="submit" name="approve_refund" class="btn btn-green" style="width: 100%; padding: 0.55rem; font-size: 0.85rem; border-radius: 6px; justify-content: center;"><i class="fas fa-undo"></i> Process Wallet Refund</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    // Reset all buttons to inactive style
    document.querySelectorAll('[id^="btn-"]').forEach(btn => {
        btn.style.background = 'rgba(15,23,42,0.05)';
        btn.style.color = 'var(--secondary)';
        btn.style.boxShadow = 'none';
    });

    // Show selected tab
    document.getElementById(tabId).style.display = 'block';
    
    // Highlight active button
    const activeBtn = document.getElementById('btn-' + tabId.replace('tab-', ''));
    if (activeBtn) {
        activeBtn.style.background = 'var(--primary)';
        activeBtn.style.color = 'white';
    }
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
