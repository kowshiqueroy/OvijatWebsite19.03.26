<?php
/**
 * Pay the Rest (Outstanding Balance & Gated Modifications Approval)
 */
restrict_to(['wholesale_user', 'executive']);

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    // Fetch user's current wallet balance
    $stmt_bal = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
    $stmt_bal->execute([$user_id]);
    $wallet_balance = (float)($stmt_bal->fetch()['balance'] ?? 0);

    // Fetch the order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if ($order) {
        if ($action === 'reject_changes' && $order['status'] === 'Pending Customer Approval') {
            // Reject proposed changes
            $pdo->beginTransaction();
            try {
                // Find previous status from history
                $stmt_hist = $pdo->prepare("SELECT status FROM order_status_history WHERE order_id = ? AND status != 'Pending Customer Approval' ORDER BY id DESC LIMIT 1");
                $stmt_hist->execute([$order_id]);
                $prev_status = $stmt_hist->fetchColumn() ?: 'Payment Verified';

                $prev_payment_status = 'Unpaid';
                $prev_fulfillment_status = 'Pending';
                switch ($prev_status) {
                    case 'Payment Verified':
                        $prev_payment_status = 'Paid';
                        $prev_fulfillment_status = 'Pending';
                        break;
                    case 'Confirmed':
                    case 'Processing':
                        $prev_payment_status = 'Paid';
                        $prev_fulfillment_status = 'Processing';
                        break;
                    case 'Hold':
                        $prev_fulfillment_status = 'Hold';
                        break;
                    case 'Ready to Ship':
                        $prev_payment_status = 'Paid';
                        $prev_fulfillment_status = 'Ready to Ship';
                        break;
                    case 'Shipped':
                        $prev_payment_status = 'Paid';
                        $prev_fulfillment_status = 'Shipped';
                        break;
                    case 'Out for Delivery':
                        $prev_payment_status = 'Paid';
                        $prev_fulfillment_status = 'Out for Delivery';
                        break;
                    case 'Delivered':
                        $prev_payment_status = 'Paid';
                        $prev_fulfillment_status = 'Delivered';
                        break;
                    case 'Cancelled':
                        $prev_fulfillment_status = 'Cancelled';
                        break;
                    case 'Rejected':
                        $prev_fulfillment_status = 'Rejected';
                        break;
                    case 'Pending Payment':
                    default:
                        $prev_payment_status = 'Unpaid';
                        $prev_fulfillment_status = 'Pending';
                        break;
                }

                $stmt_up = $pdo->prepare("UPDATE orders SET status = ?, payment_status = ?, fulfillment_status = ?, pending_change_details = NULL WHERE id = ?");
                $stmt_up->execute([$prev_status, $prev_payment_status, $prev_fulfillment_status, $order_id]);

                $stmt_hist_ins = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, 'Customer rejected proposed edits')");
                $stmt_hist_ins->execute([$order_id, $prev_status, $user_id]);

                $pdo->commit();
                $success_msg = "You have rejected the proposed changes for Order #$order_id. The order has reverted to status '$prev_status'.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Error rejecting changes: " . $e->getMessage();
            }
        } elseif ($action === 'approve_changes_wallet' && $order['status'] === 'Pending Customer Approval') {
            // Approve changes and pay shortfall via wallet
            $change_details_raw = $order['pending_change_details'];
            $change_details = json_decode($change_details_raw, true);
            
            if ($change_details) {
                $proposed_total = (float)$change_details['total_amount'];
                $old_total = (float)$order['total_amount'];
                $shortfall = $proposed_total - $old_total;

                if ($shortfall > 0 && $wallet_balance < $shortfall) {
                    $error_msg = "Insufficient wallet balance. You need $" . number_format($shortfall, 2) . " but only have $" . number_format($wallet_balance, 2) . ".";
                } else {
                    $pdo->beginTransaction();
                    try {
                        // 1. Restore the current order stock temporarily
                        restore_order_stock($pdo, $order_id);

                        // 2. Validate proposed stock levels to avoid overselling
                        if (isset($change_details['items']) && is_array($change_details['items'])) {
                            foreach ($change_details['items'] as $item) {
                                $qty = (int)$item['qty'];
                                if ($qty <= 0) continue;
                                $stmt_check = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
                                $stmt_check->execute([$item['product_id']]);
                                $prod_qty = (int)$stmt_check->fetchColumn();
                                if ($item['variant_id'] > 0) {
                                    $stmt_check = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
                                    $stmt_check->execute([$item['variant_id'], $item['product_id']]);
                                    $v_stock = $stmt_check->fetchColumn();
                                    if ($v_stock !== false) {
                                        $prod_qty = (int)$v_stock;
                                    }
                                }
                                if ($qty > $prod_qty) {
                                    throw new Exception("Insufficient stock for product: " . htmlspecialchars($item['name']) . " (Requested: $qty, Available: $prod_qty).");
                                }
                            }
                        }

                        // 3. Apply proposed changes and item-level adjusted discounts
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

                        $stmt_up = $pdo->prepare("UPDATE orders SET delivery_address = ?, shipping_amount = ?, tax_amount = ?, total_amount = ?, admin_adjusted_discount = ?, pending_change_details = NULL, status = 'Payment Verified', payment_status = 'Paid', fulfillment_status = 'Pending' WHERE id = ?");
                        $stmt_up->execute([
                            $change_details['delivery_address'],
                            (float)$change_details['shipping_amount'],
                            (float)$change_details['tax_amount'],
                            $proposed_total,
                            (float)($change_details['admin_adjusted_discount'] ?? 0),
                            $order_id
                        ]);

                        // 4. Re-deduct the stock
                        deduct_order_stock($pdo, $order_id);

                        // Debit or credit wallet if shortfall is non-zero
                        if ($shortfall > 0) {
                            $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
                            $stmt_tx->execute([$user_id, $shortfall, "Shortfall payment for Order #$order_id changes"]);
                        } elseif ($shortfall < 0) {
                            $refund_amt = abs($shortfall);
                            $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                            $stmt_tx->execute([$user_id, $refund_amt, "Wallet credit for reduced total of Order #$order_id"]);
                        }

                        $stmt_hist_ins = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Payment Verified', ?, 'Customer approved edits and settled shortfall via wallet')");
                        $stmt_hist_ins->execute([$order_id, $user_id]);

                        $pdo->commit();
                        $success_msg = "Order #$order_id changes approved successfully.";
                        // Reload wallet balance
                        $wallet_balance -= $shortfall;

                        // Send the modification confirmation invoice email
                        require_once 'includes/mailer.php';
                        send_invoice_email($pdo, $order_id, "Order Modification Confirmed");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        // Re-deduct stock if it was restored and transaction failed
                        try {
                            deduct_order_stock($pdo, $order_id);
                        } catch (Exception $ex) {}
                        $error_msg = "Error approving changes: " . $e->getMessage();
                    }
                }
            } else {
                $error_msg = "Invalid proposed changes details.";
            }
        } elseif ($action === 'approve_changes_payment' && $order['status'] === 'Pending Customer Approval') {
            // Approve changes and submit top-up request for shortfall
            $change_details_raw = $order['pending_change_details'];
            $change_details = json_decode($change_details_raw, true);

            if ($change_details) {
                $proposed_total = (float)$change_details['total_amount'];
                $old_total = (float)$order['total_amount'];
                $shortfall = $proposed_total - $old_total;
                
                $payment_method = $_POST['payment_method'] ?? '';
                $payment_amount = (float)($_POST['payment_amount'] ?? 0);
                $transaction_id = trim($_POST['transaction_id'] ?? '');

                if (!in_array($payment_method, ['Stripe', 'Bank Transfer'])) {
                    $error_msg = "Invalid payment method selected.";
                } elseif ($payment_amount < $shortfall) {
                    $error_msg = "Payment amount must be at least the shortfall of $" . number_format($shortfall, 2) . ".";
                } elseif (empty($transaction_id)) {
                    $error_msg = "Please provide transaction reference/ID.";
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Insert wallet topup record
                        $t_stmt = $pdo->prepare("INSERT INTO wallet_topups (user_id, amount, payment_method, transaction_id, status, order_id) VALUES (?, ?, ?, ?, 'pending', ?)");
                        $t_stmt->execute([$user_id, $payment_amount, $payment_method, $transaction_id, $order_id]);

                        // Record in status history that payment was submitted for approval
                        $stmt_hist_ins = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Pending Customer Approval', ?, ?)");
                        $stmt_hist_ins->execute([$order_id, $user_id, "Customer approved edits. Submitted payment request of $" . number_format($payment_amount, 2) . " via $payment_method (Txn ID: $transaction_id)"]);

                        $pdo->commit();
                        $success_msg = "Proposed changes accepted. Your payment request of $" . number_format($payment_amount, 2) . " has been submitted for Admin verification.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_msg = "Error submitting payment: " . $e->getMessage();
                    }
                }
            } else {
                $error_msg = "Invalid proposed changes details.";
            }
        }
    } else {
        $error_msg = "Order not found.";
    }
}

// Fetch user's current wallet balance
$stmt_bal = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
$stmt_bal->execute([$user_id]);
$wallet_balance = (float)($stmt_bal->fetch()['balance'] ?? 0);

// Fetch outstanding orders or orders pending customer approval
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND (payment_status = 'Unpaid' OR fulfillment_status = 'Pending Customer Approval') AND fulfillment_status NOT IN ('Cancelled', 'Rejected') ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Calculate outstanding totals
$total_outstanding = 0;
$pending_approval_count = 0;
foreach ($orders as $o) {
    if ($o['fulfillment_status'] === 'Pending Customer Approval') {
        $pending_approval_count++;
        $change_details = json_decode($o['pending_change_details'], true);
        if ($change_details) {
            $total_outstanding += max(0, (float)$change_details['total_amount'] - (float)$o['total_amount']);
        }
    } else {
        $total_outstanding += max(0, $o['total_amount'] - $wallet_balance);
    }
}
?>

<div class="section-title">
    <i class="fas fa-wallet" style="color: var(--primary);"></i>
    Pay the Rest (Outstanding Balance)
</div>

<?php if ($success_msg): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> <?php echo e($success_msg); ?>
    </div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error_msg); ?>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    <div class="card" style="padding: 1.5rem; text-align: center; border: 1px solid var(--border-light); background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(255,255,255,0.9));">
        <i class="fas fa-dollar-sign" style="font-size: 2rem; color: var(--rose); margin-bottom: 0.5rem;"></i>
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Outstanding Shortfall</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--rose);">$<?php echo number_format($total_outstanding, 2); ?></div>
    </div>
    <div class="card" style="padding: 1.5rem; text-align: center; border: 1px solid var(--border-light); background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(255,255,255,0.9));">
        <i class="fas fa-wallet" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">My Wallet Balance</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary-dark);">$<?php echo number_format($wallet_balance, 2); ?></div>
    </div>
    <div class="card" style="padding: 1.5rem; text-align: center; border: 1px solid var(--border-light); background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(255,255,255,0.9));">
        <i class="fas fa-bell" style="font-size: 2rem; color: var(--accent); margin-bottom: 0.5rem;"></i>
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Awaiting Approval</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--secondary);"><?php echo $pending_approval_count; ?> Orders</div>
    </div>
</div>

<h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; margin-bottom:1.5rem; color:var(--secondary);"><i class="fas fa-list-ol"></i> Invoices & Modification Proposals</h3>

<?php if (!$orders): ?>
    <div class="card" style="padding: 4rem 2rem; text-align: center; color: var(--text-muted); border: 1px dashed var(--border-light);">
        <i class="fas fa-file-invoice" style="font-size: 3rem; margin-bottom: 1rem; color: var(--text-muted); opacity:0.4;"></i>
        <p style="font-size:1.1rem; font-weight:600; margin:0;">No outstanding balances or modification requests.</p>
        <p style="font-size:0.85rem; margin-top:0.3rem;">All your invoices are paid and settled!</p>
    </div>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:2rem;">
    <?php foreach ($orders as $o): ?>
        <?php
        $is_gated = ($o['fulfillment_status'] === 'Pending Customer Approval');
        $change_details = $is_gated ? json_decode($o['pending_change_details'], true) : null;
        $order_total = (float)$o['total_amount'];
        $proposed_total = $is_gated && $change_details ? (float)$change_details['total_amount'] : $order_total;
        $shortfall = $is_gated ? ($proposed_total - $order_total) : max(0, $order_total - $wallet_balance);
        ?>
        
        <div class="card" style="padding: 2rem; border: 1px solid <?php echo $is_gated ? 'var(--accent)' : 'var(--border-light)'; ?>; position: relative; background: white; box-shadow: var(--shadow-sm);">
            
            <?php if ($is_gated): ?>
                <div style="position: absolute; top: 1.5rem; right: 2rem; background: rgba(245, 158, 11, 0.15); color: #d97706; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid rgba(245, 158, 11, 0.3);">
                    Awaiting Your Approval
                </div>
            <?php else: ?>
                <div style="position: absolute; top: 1.5rem; right: 2rem; background: rgba(244, 63, 94, 0.08); color: var(--rose); padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid rgba(244, 63, 94, 0.15);">
                    Pending Payment
                </div>
            <?php endif; ?>
            
            <h4 style="margin: 0 0 0.5rem 0; font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; color:var(--secondary); font-size:1.2rem;">
                Order #<?php echo $o['id']; ?> <span style="font-size: 0.85rem; color: var(--text-muted); font-weight:600;">(Placed on <?php echo date('M d, Y', strtotime($o['created_at'])); ?>)</span>
            </h4>
            
            <div style="display:flex; gap:3rem; margin-bottom:1.5rem; flex-wrap:wrap; font-size:0.875rem;">
                <div>
                    <span style="color:var(--text-muted); font-weight:600; display:block;">Current Total</span>
                    <strong style="color:var(--secondary); font-size:1.1rem;">$<?php echo number_format($order_total, 2); ?></strong>
                </div>
                <?php if ($is_gated): ?>
                    <div>
                        <span style="color:var(--text-muted); font-weight:600; display:block;">Proposed New Total</span>
                        <strong style="color:var(--accent); font-size:1.1rem;">$<?php echo number_format($proposed_total, 2); ?></strong>
                    </div>
                <?php endif; ?>
                <div>
                    <span style="color:var(--text-muted); font-weight:600; display:block;"><?php echo $is_gated ? 'Shortfall to Pay' : 'Required Payment'; ?></span>
                    <strong style="color:var(--rose); font-size:1.1rem;">$<?php echo number_format($shortfall, 2); ?></strong>
                </div>
                <div>
                    <span style="color:var(--text-muted); font-weight:600; display:block;">Method Selected</span>
                    <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:700; color:var(--secondary);"><?php echo e($o['payment_method']); ?></span>
                </div>
            </div>
            
            <!-- Proposed Diff Block -->
            <?php if ($is_gated && $change_details): ?>
                <div style="background:#fefbf6; border:1px solid #f59e0b50; padding:1.5rem; border-radius:12px; margin-bottom:2rem;">
                    <h5 style="margin: 0 0 1rem 0; font-weight:800; color:#d97706; font-size:0.95rem; display:flex; align-items:center; gap:0.4rem;">
                        <i class="fas fa-exclamation-circle"></i> Proposed Modifications Detail
                    </h5>
                    
                    <div class="grid-stack-mobile" style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:1rem; font-size:0.85rem;">
                        <div>
                            <strong style="display:block; margin-bottom:0.25rem; color:var(--secondary);">Proposed Delivery Address:</strong>
                            <span style="color:var(--text-main);"><?php echo e($change_details['delivery_address']); ?></span>
                        </div>
                        <div>
                            <strong style="display:block; margin-bottom:0.25rem; color:var(--secondary);">Financial Adjustments:</strong>
                            <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                                <tr>
                                    <td style="padding:4px 0; color:var(--text-muted);">Shipping Fee:</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:700;">$<?php echo number_format($change_details['shipping_amount'], 2); ?> <span style="font-weight:400; color:var(--text-muted);">(Old: $<?php echo number_format($o['shipping_amount'], 2); ?>)</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:var(--text-muted);">State Tax:</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:700;">$<?php echo number_format($change_details['tax_amount'], 2); ?> <span style="font-weight:400; color:var(--text-muted);">(Old: $<?php echo number_format($o['tax_amount'], 2); ?>)</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <strong style="display:block; font-size:0.85rem; color:var(--secondary); margin-bottom:0.5rem;">Proposed Items:</strong>
                    <div class="table-wrap" style="background:white; border-radius:8px; border:1px solid #f1f5f9;">
                        <table style="margin:0;">
                            <thead>
                                <tr>
                                    <th>Item Description</th>
                                    <th>Proposed Qty</th>
                                    <th>Unit Price</th>
                                    <th style="text-align:right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($change_details['items'] as $item): ?>
                                    <tr>
                                        <td><strong style="color:var(--secondary);"><?php echo e($item['name']); ?></strong></td>
                                        <td>
                                            <span style="font-weight:700; color:var(--secondary);"><?php echo $item['qty']; ?> units</span>
                                        </td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td style="text-align:right; font-weight:800; color:var(--primary-dark);">$<?php echo number_format($item['qty'] * $item['price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Actions Block -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-top:1px solid var(--border-light); padding-top:1.5rem;">
                <div>
                    <a href="/bolakausa/invoice?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.8rem; border-radius: 6px;"><i class="fas fa-file-invoice"></i> View Invoice</a>
                </div>
                
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <?php if ($is_gated): ?>
                        <!-- Reject Changes form -->
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="reject_changes">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <button type="submit" class="btn btn-red" style="padding:0.6rem 1.25rem; font-size:0.825rem;" onclick="return confirm('Are you sure you want to REJECT the admin proposed edits? The order status will revert to its previous status.');"><i class="fas fa-times-circle"></i> Reject Edits</button>
                        </form>
                        
                        <!-- Approve with Wallet form -->
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="approve_changes_wallet">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <?php if ($shortfall > 0): ?>
                                <button type="submit" class="btn btn-green" style="padding:0.6rem 1.25rem; font-size:0.825rem;" <?php echo ($wallet_balance < $shortfall) ? 'disabled style="opacity:0.5; cursor:not-allowed;" title="Insufficient wallet balance"' : ''; ?>><i class="fas fa-check-circle"></i> Approve & Pay from Wallet ($<?php echo number_format($shortfall, 2); ?>)</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-green" style="padding:0.6rem 1.25rem; font-size:0.825rem;"><i class="fas fa-check-circle"></i> Approve Changes (Refund $<?php echo number_format(abs($shortfall), 2); ?> to Wallet)</button>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Approve with payment form toggle -->
                        <?php if ($shortfall > 0): ?>
                            <button type="button" onclick="togglePaymentForm(<?php echo $o['id']; ?>)" class="btn btn-blue" style="padding:0.6rem 1.25rem; font-size:0.825rem;"><i class="fas fa-university"></i> Approve & Settle via Stripe/Bank</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="font-size:0.85rem; color:var(--text-muted); font-style:italic; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i> Wallet has been debited. Payment verification is pending admin approval of the transaction proof submitted at checkout.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stripe / Bank wire offline payment form -->
            <div id="payment-form-<?php echo $o['id']; ?>" style="display:none; margin-top:1.5rem; background:#f8fafc; border:1px solid #e2e8f0; padding:1.5rem; border-radius:10px;">
                <h5 style="margin: 0 0 1rem 0; font-weight:800; color:var(--secondary); font-size:0.9rem;"><i class="fas fa-university"></i> Offline Stripe or Bank Transfer Settle Portal</h5>
                
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $is_gated ? 'approve_changes_payment' : 'pay_order_payment'; ?>">
                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                    
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1rem;">
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.25rem; display:block;">Select Route</label>
                            <select name="payment_method" id="payment_method_<?php echo $o['id']; ?>" style="padding:0.5rem; width:100%; border-radius:6px;" onchange="updatePaymentDetailsLabel(<?php echo $o['id']; ?>)">
                                <option value="Stripe">Stripe Checkout / Card</option>
                                <option value="Bank Transfer">Bank Wire Transfer</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.25rem; display:block;">Amount to Pay ($) <span style="color:var(--rose);">(Min: $<?php echo number_format($shortfall, 2); ?>)</span></label>
                            <input type="number" step="0.01" name="payment_amount" value="<?php echo number_format($shortfall, 2, '.', ''); ?>" min="<?php echo number_format($shortfall, 2, '.', ''); ?>" style="padding:0.5rem; width:100%; border-radius:6px; font-weight:700;">
                            <small style="font-size:0.7rem; color:var(--text-muted); display:block; margin-top:2px;">Overpayments will be credited to your wallet balance.</small>
                        </div>
                        
                        <div class="form-group" style="margin:0;">
                            <label id="tx_label_<?php echo $o['id']; ?>" style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.25rem; display:block;">Stripe Charge ID / Receipt Reference</label>
                            <input type="text" name="transaction_id" placeholder="ch_... / wire_..." required style="padding:0.5rem; width:100%; border-radius:6px;">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-green" style="padding:0.6rem 1.5rem; font-size:0.8rem; width:100%;"><i class="fas fa-paper-plane"></i> Submit Payment for Verification</button>
                </form>
            </div>
            
        </div>
    <?php endforeach; ?>
</div>

<script>
function togglePaymentForm(orderId) {
    const el = document.getElementById('payment-form' + '-' + orderId);
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
        if (el.style.display === 'block') {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

function updatePaymentDetailsLabel(orderId) {
    const methodEl = document.getElementById('payment_method_' + orderId);
    const labelEl = document.getElementById('tx_label_' + orderId);
    if (methodEl && labelEl) {
        if (methodEl.value === 'Stripe') {
            labelEl.textContent = 'Stripe Charge ID / Receipt Reference';
        } else {
            labelEl.textContent = 'Bank Wire Reference / Transaction ID';
        }
    }
}
</script>

<p style="margin-top: 2.5rem;"><a href="/bolakausa/home" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Return to Catalog</a></p>
