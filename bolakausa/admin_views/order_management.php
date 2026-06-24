<?php
/**
 * Redesigned Admin Order Management & Advanced Inventory Dashboard - Premium Redesign
 */
restrict_to(['admin', 'manager', 'warehouse', 'viewer', 'editor']);

$user_role = get_user_role();
$success = '';
$error = '';

$status_weights = [
    'Pending Payment' => 1,
    'Pending Customer Approval' => 1.5,
    'Payment Verified' => 2,
    'Confirmed' => 3,
    'Processing' => 4,
    'Hold' => 5,
    'Stock Out' => 6,
    'Ready to Ship' => 7,
    'Shipped' => 8,
    'Out for Delivery' => 9,
    'Delivered' => 10,
    'Cancelled' => 11,
    'Rejected' => 12
];

$statuses = [
    'Pending Payment', 
    'Pending Customer Approval',
    'Payment Verified', 
    'Confirmed', 
    'Processing', 
    'Hold', 
    'Stock Out', 
    'Ready to Ship', 
    'Shipped', 
    'Out for Delivery', 
    'Delivered', 
    'Cancelled', 
    'Rejected'
];

// Handle Status Changes & Stock Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $user_role !== 'viewer') {
    $order_id = (int)$_POST['order_id'];
    $new_fulfillment = $_POST['fulfillment_status'];
    $new_payment = $_POST['payment_status'];
    $notes = $_POST['notes'] ?? '';

    // Fetch current status
    $stmt = $pdo->prepare("SELECT status, fulfillment_status, payment_status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $current_order = $stmt->fetch();

    if ($current_order) {
        $current_fulfillment = $current_order['fulfillment_status'];
        $current_payment = $current_order['payment_status'];
        $current_legacy = $current_order['status'];

        $current_weight = $status_weights[$current_fulfillment] ?? 0;
        $new_weight = $status_weights[$new_fulfillment] ?? 0;

        // Constraint: Status action cannot be backward without Admin approval
        if ($new_weight < $current_weight && $user_role !== 'admin') {
            $error = "Access Denied: Reverting status backward (from '$current_fulfillment' to '$new_fulfillment') is blocked for non-admin accounts.";
        } else {
            $pdo->beginTransaction();
            try {
                // Fetch customer ID and order details
                $stmt_ord = $pdo->prepare("SELECT user_id, total_amount FROM orders WHERE id = ?");
                $stmt_ord->execute([$order_id]);
                $ord_data = $stmt_ord->fetch();
                $cust_id = $ord_data['user_id'] ?? null;
                $order_total = (float)($ord_data['total_amount'] ?? 0);

                // Update Order Statuses
                // Keep legacy status in sync with fulfillment status
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, fulfillment_status = ?, payment_status = ? WHERE id = ?");
                $stmt->execute([$new_fulfillment, $new_fulfillment, $new_payment, $order_id]);

                // Restore stock from picking splits if Cancelled or Rejected
                if (in_array($new_fulfillment, ['Cancelled', 'Rejected'])) {
                    // Restore catalog stock if transitioning to Cancelled/Rejected from a non-Cancelled/Rejected status
                    if (!in_array($current_fulfillment, ['Cancelled', 'Rejected'])) {
                        restore_order_stock($pdo, $order_id);
                    }

                    $stmt_picks = $pdo->prepare("SELECT * FROM order_item_picks WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ? AND is_deleted = 0) AND is_deleted = 0");
                    $stmt_picks->execute([$order_id]);
                    $picks = $stmt_picks->fetchAll();
                    
                    foreach ($picks as $pick) {
                        $uStmt = $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + ? WHERE id = ?");
                        $uStmt->execute([$pick['qty'], $pick['lot_id']]);
                    }
                    
                    $dStmt = $pdo->prepare("UPDATE order_item_picks SET is_deleted = 1 WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ? AND is_deleted = 0)");
                    $dStmt->execute([$order_id]);

                    // Automatic Wallet Refund if Cancelled AND was Paid
                    if ($new_fulfillment === 'Cancelled' && $cust_id) {
                        // Check if already refunded to prevent double credit
                        $stmt_ref_chk = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE user_id = ? AND type = 'credit' AND description LIKE ?");
                        $stmt_ref_chk->execute([$cust_id, "Refund for Cancelled Order #$order_id%"]);
                        $already_refunded = $stmt_ref_chk->fetchColumn() > 0;
                        
                        if (!$already_refunded && $current_payment === 'Paid') {
                            $stmt_ref = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                            $stmt_ref->execute([$cust_id, $order_total, "Refund for Cancelled Order #$order_id"]);
                            
                            $stmt_up_pay = $pdo->prepare("UPDATE orders SET payment_status = 'Refunded' WHERE id = ?");
                            $stmt_up_pay->execute([$order_id]);
                        }
                    }
                }

                // If moving from Cancelled/Rejected to Pending/Processing/etc, re-deduct catalog stock
                if (in_array($current_fulfillment, ['Cancelled', 'Rejected']) && !in_array($new_fulfillment, ['Cancelled', 'Rejected'])) {
                    deduct_order_stock($pdo, $order_id);
                }

                // Record in Status History
                $hist_notes = "Fulfillment: $new_fulfillment | Payment: $new_payment";
                if ($notes) {
                    $hist_notes .= " | Notes: $notes";
                }
                $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $new_fulfillment, $_SESSION['user_id'], $hist_notes]);

                $pdo->commit();
                $success = "Order #$order_id successfully updated. Fulfillment: $new_fulfillment, Payment: $new_payment.";
                log_action($pdo, $_SESSION['user_id'], "Order Status Changed", "Order #$order_id: $current_fulfillment/$current_payment -> $new_fulfillment/$new_payment");

                // Trigger customer notification
                if ($cust_id) {
                    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $stmt_notif->execute([$cust_id, "Order Update #$order_id", "Your order has been updated. Fulfillment: $new_fulfillment, Payment: $new_payment."]);
                }

                // Trigger Email Notification
                require_once __DIR__ . '/../includes/mailer.php';
                if ($new_fulfillment === 'Ready to Ship') {
                    send_invoice_email($pdo, $order_id, "Order Ready to Ship");
                } else {
                    $uStmt = $pdo->prepare("SELECT u.email, u.full_name FROM users u JOIN orders o ON u.id = o.user_id WHERE o.id = ?");
                    $uStmt->execute([$order_id]);
                    $user_data = $uStmt->fetch();
                    if ($user_data) {
                        $email_body = "<h3>Order Status Update</h3>
                        <p>Hello " . e($user_data['full_name']) . ",</p>
                        <p>Your order <strong>#$order_id</strong> has been updated:</p>
                        <p>Fulfillment Status: <strong style='color: #3b82f6;'>" . e($new_fulfillment) . "</strong></p>
                        <p>Payment Status: <strong style='color: #10b981;'>" . e($new_payment) . "</strong></p>
                        <p>Notes: " . e($notes) . "</p>
                        <p>Log in to your wholesale dashboard to view full details.</p>";
                        send_system_email($pdo, $user_data['email'], "Order Update #$order_id: $new_fulfillment", $email_body);
                    }
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to update order status: " . $e->getMessage();
            }
        }
    }
}

// Handle Wallet Refund Approval for Rejected Orders (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_refund']) && $user_role === 'admin') {
    $order_id = (int)$_POST['order_id'];
    $rejection_charge = max(0, (float)($_POST['rejection_charge'] ?? 0));

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order && $order['status'] === 'Rejected' && !$order['refund_approved']) {
        $net_refund = $order['total_amount'] - $rejection_charge;
        if ($net_refund < 0) $net_refund = 0;

        $pdo->beginTransaction();
        try {
            // Update order details
            $stmt = $pdo->prepare("UPDATE orders SET rejection_charge = ?, refund_approved = 1 WHERE id = ?");
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
            $success = "Refund of $$net_refund approved for Order #$order_id (Rejection Charge: $$rejection_charge).";
            log_action($pdo, $_SESSION['user_id'], "Refund Approved", "Order #$order_id: Charge: $rejection_charge, Net: $net_refund");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to approve refund: " . $e->getMessage();
        }
    }
}

// Handle Order Editing (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_order']) && $user_role === 'admin') {
    $order_id = (int)$_POST['order_id'];
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $shipping_amount = (float)($_POST['shipping_amount'] ?? 0);
    $tax_amount = (float)($_POST['tax_amount'] ?? 0);
    $admin_adjusted_discount = (float)($_POST['admin_adjusted_discount'] ?? 0);
    $item_quantities = is_array($_POST['item_qty'] ?? null) ? $_POST['item_qty'] : [];
    $item_discounts = is_array($_POST['item_discount'] ?? null) ? $_POST['item_discount'] : [];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $old_order = $stmt->fetch();
        if (!$old_order) {
            throw new Exception("Order not found.");
        }
        $old_total = (float)$old_order['total_amount'];

        $stmt = $pdo->prepare("SELECT oi.id, oi.qty, oi.product_id, oi.variant_id, oi.price_at_purchase, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND oi.is_deleted = 0");
        $stmt->execute([$order_id]);
        $old_items = $stmt->fetchAll();
        $old_items_map = [];
        foreach ($old_items as $oi) {
            $old_items_map[$oi['id']] = $oi;
        }

        $subtotal = 0;
        $qty_increased = false;
        $proposed_items = [];
        
        foreach ($item_quantities as $item_id => $qty) {
            $qty = (int)$qty;
            if (isset($old_items_map[$item_id])) {
                $old_item = $old_items_map[$item_id];
                if ($qty > $old_item['qty']) {
                    $qty_increased = true;
                }
                $item_disc = (float)($item_discounts[$item_id] ?? 0);
                if ($qty > 0) {
                    $subtotal += (($old_item['price_at_purchase'] - $item_disc) * $qty);
                    $proposed_items[] = [
                        'item_id' => $item_id,
                        'product_id' => $old_item['product_id'],
                        'variant_id' => $old_item['variant_id'],
                        'name' => $old_item['name'],
                        'qty' => $qty,
                        'price' => $old_item['price_at_purchase'],
                        'admin_adjusted_discount' => $item_disc
                    ];
                } else {
                    $proposed_items[] = [
                        'item_id' => $item_id,
                        'product_id' => $old_item['product_id'],
                        'variant_id' => $old_item['variant_id'],
                        'name' => $old_item['name'],
                        'qty' => 0,
                        'price' => $old_item['price_at_purchase'],
                        'admin_adjusted_discount' => $item_disc
                    ];
                }
            }
        }
        
        $new_total = max(0, $subtotal + $shipping_amount + $tax_amount - (float)$old_order['discount_amount'] - $admin_adjusted_discount);
        $requires_approval = ($new_total > $old_total) || $qty_increased;
        
        if ($requires_approval) {
            $proposed_change = [
                'delivery_address' => $delivery_address,
                'shipping_amount' => $shipping_amount,
                'tax_amount' => $tax_amount,
                'total_amount' => $new_total,
                'admin_adjusted_discount' => $admin_adjusted_discount,
                'items' => $proposed_items
            ];
            $pending_json = json_encode($proposed_change);
            
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Pending Customer Approval', fulfillment_status = 'Pending Customer Approval', pending_change_details = ? WHERE id = ?");
            $stmt->execute([$pending_json, $order_id]);
            
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Pending Customer Approval', ?, ?)");
            $stmt->execute([$order_id, $_SESSION['user_id'], "Admin edited order (Total modified from $" . number_format($old_total, 2) . " to $" . number_format($new_total, 2) . "). Awaiting customer approval."]);
            
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$old_order['user_id'], "Order Action Required #$order_id", "Admin proposed edits to your Order #$order_id. Please review and pay the shortfall of $" . number_format($new_total - $old_total, 2) . "."]);
            
            $success = "Order #$order_id changes saved. Status updated to 'Pending Customer Approval' (Awaiting wholesaler confirmation).";
            log_action($pdo, $_SESSION['user_id'], "Order Edit Gated", "Order #$order_id: Awaiting customer approval for proposed total: $new_total");
        } else {
            foreach ($item_quantities as $item_id => $qty) {
                $qty = (int)$qty;
                $item_disc = (float)($item_discounts[$item_id] ?? 0);
                if ($qty <= 0) {
                    $stmt = $pdo->prepare("UPDATE order_items SET is_deleted = 1 WHERE id = ?");
                    $stmt->execute([$item_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE order_items SET qty = ?, admin_adjusted_discount = ? WHERE id = ?");
                    $stmt->execute([$qty, $item_disc, $item_id]);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET delivery_address = ?, shipping_amount = ?, tax_amount = ?, total_amount = ?, admin_adjusted_discount = ?, pending_change_details = NULL WHERE id = ?");
            $stmt->execute([$delivery_address, $shipping_amount, $tax_amount, $new_total, $admin_adjusted_discount, $order_id]);
            
            // Adjust wallet for decreased total immediately (refund approved discount difference)
            $diff = $old_total - $new_total;
            if ($diff > 0) {
                $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt_tx->execute([$old_order['user_id'], $diff, "Wallet credit for reduced total of Order #$order_id (Edited by Admin)"]);
            }

            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$old_order['user_id'], "Order Updated #$order_id", "Admin has updated details on your Order #$order_id. New Total: $" . number_format($new_total, 2)]);
            
            $success = "Order #$order_id details successfully updated. New Total: $$new_total.";
            log_action($pdo, $_SESSION['user_id'], "Order Edited by Admin", "Order #$order_id: Address: $delivery_address, Total: $new_total");
            
            // Dispatch Invoice Email
            require_once __DIR__ . '/../includes/mailer.php';
            send_invoice_email($pdo, $order_id, "Order Modified by Admin");
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to edit order: " . $e->getMessage();
    }
}

// Handle Saving Picking Splits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_picks']) && $user_role !== 'viewer') {
    $order_id = (int)$_POST['order_id'];
    $picks = is_array($_POST['picks'] ?? null) ? $_POST['picks'] : [];
    
    $pdo->beginTransaction();
    try {
        $all_ok = true;
        $error_msg = '';
        
        foreach ($picks as $item_id => $lot_splits) {
            $stmt = $pdo->prepare("SELECT qty, product_id FROM order_items WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) continue;
            
            $qty_needed = (int)$item['qty'];
            $total_picked = 0;
            
            foreach ($lot_splits as $lot_id => $pick_qty) {
                $total_picked += (int)$pick_qty;
            }
            
            if ($total_picked !== $qty_needed) {
                $all_ok = false;
                $error_msg = "Total picked quantity ($total_picked) does not match required quantity ($qty_needed) for item ID #$item_id.";
                break;
            }
            
            // Revert old picks for this item
            $stmt_old = $pdo->prepare("SELECT * FROM order_item_picks WHERE order_item_id = ? AND is_deleted = 0");
            $stmt_old->execute([$item_id]);
            $old_picks = $stmt_old->fetchAll();
            foreach ($old_picks as $op) {
                $stmt_restore = $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + ? WHERE id = ?");
                $stmt_restore->execute([$op['qty'], $op['lot_id']]);
            }
            
            // Delete old picks
            $stmt_del = $pdo->prepare("UPDATE order_item_picks SET is_deleted = 1 WHERE order_item_id = ?");
            $stmt_del->execute([$item_id]);
            
            // Insert new picks and deduct from lots
            $stmt_ins = $pdo->prepare("INSERT INTO order_item_picks (order_item_id, lot_id, qty) VALUES (?, ?, ?)");
            $stmt_deduct = $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining - ? WHERE id = ?");
            
            foreach ($lot_splits as $lot_id => $pick_qty) {
                $pick_qty = (int)$pick_qty;
                if ($pick_qty > 0) {
                    $stmt_lot = $pdo->prepare("SELECT qty_remaining, lot_number FROM inventory_lots WHERE id = ?");
                    $stmt_lot->execute([$lot_id]);
                    $lot_data = $stmt_lot->fetch();
                    
                    if ($lot_data['qty_remaining'] < $pick_qty) {
                        $all_ok = false;
                        $error_msg = "Insufficient remaining quantity in LOT '{$lot_data['lot_number']}' (Available: {$lot_data['qty_remaining']}, Requested: $pick_qty).";
                        break 2;
                    }
                    
                    $stmt_ins->execute([$item_id, $lot_id, $pick_qty]);
                    $stmt_deduct->execute([$pick_qty, $lot_id]);
                }
            }
        }
        
        if ($all_ok) {
            // Move order to Ready to Ship
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Ready to Ship', fulfillment_status = 'Ready to Ship' WHERE id = ?");
            $stmt->execute([$order_id]);
            
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Ready to Ship', ?, 'Inventory picking split allocated')");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            
            $pdo->commit();
            $success = "Picking splits saved. Order #$order_id status updated to 'Ready to Ship'.";
            log_action($pdo, $_SESSION['user_id'], "Order Pick Splits Saved", "Order #$order_id");
            
            // Add customer notification
            $stmt_cust = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt_cust->execute([$order_id]);
            $cust_id = $stmt_cust->fetchColumn();
            
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$cust_id, "Order Ready for Shipping", "Your order #$order_id items have been picked and verified."]);
            
            // Trigger Email Notification
            require_once __DIR__ . '/../includes/mailer.php';
            send_invoice_email($pdo, $order_id, "Order Ready to Ship");
            
        } else {
            $pdo->rollBack();
            $error = $error_msg;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to save picks: " . $e->getMessage();
    }
}

// Handle Recording Offline Payment (Admin/Manager Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_order_payment']) && in_array($user_role, ['admin', 'manager'])) {
    $order_id = (int)$_POST['order_id'];
    $amount = (float)$_POST['payment_amount'];
    $method_ref = trim($_POST['payment_method_ref'] ?? 'Other');
    $notes = trim($_POST['payment_notes'] ?? '');

    if ($order_id && $amount > 0) {
        $pdo->beginTransaction();
        try {
            // Get user_id for the order
            $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $ord_user_id = $stmt->fetchColumn();

            if ($ord_user_id) {
                // Insert credit transaction into wallet
                $desc = "Payment received for Order #$order_id via $method_ref";
                if ($notes) {
                    $desc .= " ($notes)";
                }
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt->execute([$ord_user_id, $amount, $desc]);

                // Update order's payment status to Paid
                $stmt_up = $pdo->prepare("UPDATE orders SET payment_status = 'Paid' WHERE id = ?");
                $stmt_up->execute([$order_id]);

                // Also if legacy status was 'Pending Payment', change it to 'Payment Verified'
                $stmt_status = $pdo->prepare("UPDATE orders SET status = 'Payment Verified' WHERE id = ? AND status = 'Pending Payment'");
                $stmt_status->execute([$order_id]);

                // Log history for status update/payment recorded
                $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, (SELECT status FROM orders WHERE id = ?), ?, ?)");
                $stmt->execute([$order_id, $order_id, $_SESSION['user_id'], "Admin recorded offline payment of $" . number_format($amount, 2) . " via $method_ref. Payment status marked Paid."]);

                $pdo->commit();
                $success = "Payment of $" . number_format($amount, 2) . " successfully recorded and credited to wholesaler's wallet.";
                log_action($pdo, $_SESSION['user_id'], "Recorded Order Payment", "Order #$order_id: Amount: $amount");
            } else {
                throw new Exception("Wholesaler user not found for this order.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to record payment: " . $e->getMessage();
        }
    }
}

// Date Range Filter Logic
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$show_all = isset($_GET['show_all']);
$status_filter = $_GET['status'] ?? '';

$query = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.is_deleted = 0";
$params = [];

if ($status_filter) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}
if (!$show_all) {
    $query .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}
$query .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Determine last active order to keep open
$active_order_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_order_id = (int)($_POST['order_id'] ?? 0);
} else {
    $active_order_id = (int)($_GET['active_order_id'] ?? 0);
}
if (!$active_order_id && !empty($orders)) {
    $active_order_id = $orders[0]['id'];
}
?>

<style>
/* REDESIGNED CSS FOR ALL-IN-ONE HUB */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.25fr 1fr;
    gap: 2rem;
    align-items: start;
}
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

/* Master List Styling */
.ledger-list-card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    box-shadow: var(--glass-shadow);
    padding: 1.5rem;
}
.search-bar-wrap {
    position: relative;
    margin-bottom: 1.25rem;
}
.search-bar-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-bar-wrap input {
    padding-left: 2.5rem;
    font-size: 0.9rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.date-presets {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}
.date-presets a {
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    background: #f1f5f9;
    color: var(--text-muted);
    transition: all 0.2s;
}
.date-presets a.active, .date-presets a:hover {
    background: var(--accent);
    color: white;
}

/* Order Rows */
.order-ledger-table {
    width: 100%;
    border-collapse: collapse;
}
.order-ledger-table th {
    padding: 0.75rem 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    border-bottom: 2px solid #f1f5f9;
}
.order-ledger-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.15s;
}
.order-row:hover {
    background: #f8fafc;
}
.order-row.active-row {
    background: rgba(99, 102, 241, 0.05);
    border-left: 4px solid var(--accent);
}

/* Detail Panel Container */
.detail-hub-panel {
    position: sticky;
    top: 90px;
}
.hub-card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    box-shadow: var(--glass-shadow);
    overflow: hidden;
}
.hub-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    padding: 1.5rem;
    color: white;
}
.hub-header h3 {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 1.25rem;
}
.hub-body {
    padding: 1.5rem;
}

/* Visual Stepper styling */
.stepper-wrapper {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2.25rem;
    padding: 0 0.5rem;
}
.stepper-bg-line {
    position: absolute;
    top: 18px;
    left: 10px;
    right: 10px;
    height: 4px;
    background: #e2e8f0;
    z-index: 1;
    border-radius: 2px;
}
.stepper-progress-line {
    position: absolute;
    top: 18px;
    left: 10px;
    height: 4px;
    background: var(--primary);
    z-index: 2;
    transition: width 0.4s ease;
    border-radius: 2px;
}
.step-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 3;
}
.step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: white;
    border: 2.5px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--text-muted);
    transition: all 0.3s;
}
.step-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-top: 0.4rem;
    text-align: center;
    white-space: nowrap;
}
.step-node.completed .step-circle {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 0 10px var(--primary-glow);
}
.step-node.completed .step-label {
    color: var(--primary-dark);
}
.step-node.active .step-circle {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
    box-shadow: 0 0 10px var(--accent-glow);
}
.step-node.active .step-label {
    color: var(--accent);
}

/* Tabs */
.hub-tabs {
    display: flex;
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 1.5rem;
    gap: 0.25rem;
}
.hub-tab-btn {
    padding: 0.6rem 0.85rem;
    border: none;
    background: none;
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}
.hub-tab-btn:hover {
    color: var(--secondary);
}
.hub-tab-btn.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}
.tab-pane {
    display: none;
}
.tab-pane.active {
    display: block;
}

/* Vertical timeline styling */
.timeline-trail {
    position: relative;
    padding-left: 1.5rem;
    margin-top: 0.5rem;
    border-left: 2.5px solid #e2e8f0;
}
.timeline-event {
    position: relative;
    margin-bottom: 1.25rem;
}
.timeline-event:last-child {
    margin-bottom: 0;
}
.timeline-dot {
    position: absolute;
    left: -1.95rem;
    top: 4px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #cbd5e1;
    border: 2px solid white;
}
.timeline-event.active-event .timeline-dot {
    background: var(--accent);
    box-shadow: 0 0 8px var(--accent-glow);
}
.timeline-time-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-muted);
}
.timeline-header-text {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--secondary);
}
.timeline-notes-box {
    font-size: 0.75rem;
    color: var(--text-main);
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    padding: 0.4rem 0.6rem;
    border-radius: 6px;
    margin-top: 0.2rem;
}

/* Picking Badges */
.pick-badge {
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 8px;
    display: inline-block;
}
.pick-badge.success {
    background: rgba(16,185,129,0.1);
    color: var(--primary-dark);
}
.pick-badge.warning {
    background: rgba(245,158,11,0.1);
    color: #d97706;
}
</style>

<div class="section-title">
    <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i>
    Order & Fulfillment Workspace
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    
    <!-- LEFT COLUMN: LEDGER LIST -->
    <div class="ledger-list-card">
        
        <!-- Live search bar -->
        <div class="search-bar-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="orderSearch" onkeyup="filterOrders()" placeholder="Search by Order ID, Client name, or address...">
        </div>
        
        <!-- Quick Date Presets -->
        <div class="date-presets">
            <?php
            $today_str = date('Y-m-d');
            $yesterday_str = date('Y-m-d', strtotime('-1 day'));
            $last_7_str = date('Y-m-d', strtotime('-7 days'));
            $last_30_str = date('Y-m-d', strtotime('-30 days'));
            ?>
            <a href="?date_from=<?php echo $today_str; ?>&date_to=<?php echo $today_str; ?>" class="<?php echo ($date_from===$today_str && $date_to===$today_str && !$show_all)?'active':''; ?>">Today</a>
            <a href="?date_from=<?php echo $yesterday_str; ?>&date_to=<?php echo $yesterday_str; ?>" class="<?php echo ($date_from===$yesterday_str && $date_to===$yesterday_str && !$show_all)?'active':''; ?>">Yesterday</a>
            <a href="?date_from=<?php echo $last_7_str; ?>&date_to=<?php echo $today_str; ?>" class="<?php echo ($date_from===$last_7_str && $date_to===$today_str && !$show_all)?'active':''; ?>">Last 7 Days</a>
            <a href="?date_from=<?php echo $last_30_str; ?>&date_to=<?php echo $today_str; ?>" class="<?php echo ($date_from===$last_30_str && $date_to===$today_str && !$show_all)?'active':''; ?>">Last 30 Days</a>
            <a href="?show_all=1" class="<?php echo $show_all?'active':''; ?>">All Time</a>
        </div>
        
        <!-- Hidden Date Range Controls for customization -->
        <form method="GET" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; align-items: flex-end;">
            <input type="hidden" name="url" value="admin/orders">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
            <?php endif; ?>
            <div style="flex: 1;">
                <label style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Custom Start</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 0.35rem 0.5rem; font-size: 0.8rem; border-radius: 6px;">
            </div>
            <div style="flex: 1;">
                <label style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Custom End</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 0.35rem 0.5rem; font-size: 0.8rem; border-radius: 6px;">
            </div>
            <button type="submit" class="btn btn-blue" style="padding: 0.45rem 0.75rem; font-size: 0.8rem; border-radius: 6px;"><i class="fas fa-arrow-right"></i></button>
        </form>

        <!-- Dynamic Status Filter Tags -->
        <div style="display: flex; gap: 0.3rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <a href="/bolakausa/admin/orders?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $show_all?'&show_all=1':''; ?>" class="btn <?php echo !$status_filter ? 'btn-blue' : 'btn-outline'; ?>" style="font-size:0.75rem; padding:0.35rem 0.65rem;">All</a>
            <?php foreach ($statuses as $st): ?>
                <a href="/bolakausa/admin/orders?status=<?php echo urlencode($st); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $show_all?'&show_all=1':''; ?>" class="btn <?php echo ($status_filter === $st) ? 'btn-blue' : 'btn-outline'; ?>" style="font-size:0.75rem; padding:0.35rem 0.65rem; white-space:nowrap;">
                    <?php echo $st; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="max-height: 700px; overflow-y: auto;">
            <table class="order-ledger-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Order info</th>
                        <th style="text-align: left;">Client</th>
                        <th style="text-align: right;">Total</th>
                        <th style="text-align: center;">Payment</th>
                        <th style="text-align: center;">Fulfillment</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php if (!$orders): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem;">No orders matched these filter parameters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                    <tr class="order-row <?php echo ($active_order_id === (int)$o['id']) ? 'active-row' : ''; ?>" id="ledger-row-<?php echo $o['id']; ?>" onclick="selectOrder(<?php echo $o['id']; ?>)">
                        <td>
                            <strong>#<?php echo $o['id']; ?></strong>
                            <br><small style="color: var(--text-muted);"><?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></small>
                        </td>
                        <td style="font-weight: 700;"><?php echo e($o['username']); ?></td>
                        <td style="text-align: right; font-weight: 800; color: var(--primary);">$<?php echo number_format($o['total_amount'], 2); ?></td>
                        <td style="text-align: center;">
                            <?php
                                $p_bg = 'rgba(15,23,42,0.05)'; $p_color = 'var(--secondary)';
                                if ($o['payment_status'] === 'Unpaid') { $p_bg = 'rgba(244, 63, 94, 0.08)'; $p_color = 'var(--rose)'; }
                                elseif ($o['payment_status'] === 'Paid') { $p_bg = 'rgba(16, 185, 129, 0.08)'; $p_color = 'var(--primary-dark)'; }
                                elseif ($o['payment_status'] === 'Refunded') { $p_bg = 'rgba(99, 102, 241, 0.08)'; $p_color = '#4f46e5'; }
                            ?>
                            <span style="padding: 4px 8px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; background: <?php echo $p_bg; ?>; color: <?php echo $p_color; ?>; text-transform: uppercase; white-space: nowrap;">
                                <?php echo htmlspecialchars($o['payment_status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
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
                            <span style="padding: 4px 8px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; background: <?php echo $f_bg; ?>; color: <?php echo $f_color; ?>; text-transform: uppercase; white-space: nowrap;">
                                <?php echo htmlspecialchars($o['fulfillment_status']); ?>
                            </span>
                        </td>
                        <!-- Hidden search columns -->
                        <td style="display:none;" class="search-data"><?php echo e($o['id'] . ' ' . $o['username'] . ' ' . $o['delivery_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- RIGHT COLUMN: ACTIVE ORDER OPERATIONS HUB -->
    <div class="detail-hub-panel">
        
        <div id="orderPlaceholder" class="card" style="text-align: center; padding: 6rem 2rem; color: var(--text-muted); <?php echo $active_order_id ? 'display:none;' : ''; ?>">
            <i class="fas fa-file-invoice-dollar" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.25; color: var(--accent);"></i>
            <h3>Fulfillment Hub</h3>
            <p style="font-size: 0.85rem; margin-top: 0.5rem; line-height: 1.5;">Select an active order ledger record on the left to load operational progress tracking, lot allocations, status overrides, and admin edit parameters.</p>
        </div>

        <?php foreach ($orders as $o): ?>
        <div id="hub-container-<?php echo $o['id']; ?>" class="hub-card order-hub-panel" style="<?php echo ($active_order_id === (int)$o['id']) ? '' : 'display:none;'; ?>">
            
            <!-- Hub Header -->
            <div class="hub-header">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3>Fulfillment Hub #<?php echo $o['id']; ?></h3>
                        <span style="font-size:0.75rem; color:#94a3b8;"><i class="far fa-clock"></i> Logged <?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></span>
                    </div>
                    <a href="/bolakausa/invoice?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-outline" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2); color:white; padding:0.4rem 0.8rem; font-size:0.75rem;"><i class="fas fa-file-invoice"></i> PDF Invoice</a>
                </div>
            </div>
            
            <div class="hub-body">
                
                <!-- Visual Stepper Progress Bar -->
                <?php if (!in_array($o['fulfillment_status'], ['Cancelled', 'Rejected'])): ?>
                    <?php
                    // Stepper Progress Calculations
                    $prog_p = 10;
                    $status = $o['fulfillment_status'];
                    if ($status === 'Pending') $prog_p = 10;
                    if ($status === 'Processing') $prog_p = 28;
                    if ($status === 'Hold') $prog_p = 46;
                    if ($status === 'Ready to Ship') $prog_p = 64;
                    if (in_array($status, ['Shipped', 'Out for Delivery'])) $prog_p = 82;
                    if ($status === 'Delivered') $prog_p = 100;
                    
                    $w_status = 0;
                    if ($status === 'Pending') $w_status = 1;
                    elseif ($status === 'Processing') $w_status = 2;
                    elseif ($status === 'Hold') $w_status = 3;
                    elseif ($status === 'Ready to Ship') $w_status = 4;
                    elseif (in_array($status, ['Shipped', 'Out for Delivery'])) $w_status = 5;
                    elseif ($status === 'Delivered') $w_status = 6;
                    ?>
                    <div class="stepper-wrapper">
                        <div class="stepper-bg-line"></div>
                        <div class="stepper-progress-line" style="width: <?php echo $prog_p; ?>%;"></div>
                        
                        <div class="step-node <?php echo ($w_status >= 1) ? (($w_status == 1) ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle"><i class="fas fa-file-invoice"></i></div>
                            <div class="step-label">Pending</div>
                        </div>
                        <div class="step-node <?php echo ($w_status >= 2) ? (($w_status == 2) ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle"><i class="fas fa-cog"></i></div>
                            <div class="step-label">Processing</div>
                        </div>
                        <div class="step-node <?php echo ($w_status >= 3) ? (($w_status == 3) ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle"><i class="fas fa-pause"></i></div>
                            <div class="step-label">Hold</div>
                        </div>
                        <div class="step-node <?php echo ($w_status >= 4) ? (($w_status == 4) ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle"><i class="fas fa-boxes"></i></div>
                            <div class="step-label">Ready</div>
                        </div>
                        <div class="step-node <?php echo ($w_status >= 5) ? (($w_status == 5) ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle"><i class="fas fa-truck"></i></div>
                            <div class="step-label">Ship</div>
                        </div>
                        <div class="step-node <?php echo ($w_status >= 6) ? (($w_status == 6) ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle"><i class="fas fa-home"></i></div>
                            <div class="step-label">Done</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background: rgba(244, 63, 94, 0.08); border: 1px solid rgba(244, 63, 94, 0.15); padding: 0.75rem 1rem; border-radius: 10px; text-align: center; margin-bottom: 1.5rem; font-weight: 800; color: var(--rose); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">
                        <i class="fas fa-ban"></i> Order Terminated - Fulfillment Status: <?php echo htmlspecialchars($o['fulfillment_status']); ?>
                    </div>
                <?php endif; ?>

                <!-- Tabs navigation -->
                <div class="hub-tabs">
                    <button type="button" class="hub-tab-btn active" onclick="switchHubTab(<?php echo $o['id']; ?>, 'ops')"><i class="fas fa-tasks"></i> Operations</button>
                    <button type="button" class="hub-tab-btn" onclick="switchHubTab(<?php echo $o['id']; ?>, 'picks')"><i class="fas fa-dolly"></i> Pick Lots</button>
                    <button type="button" class="hub-tab-btn" onclick="switchHubTab(<?php echo $o['id']; ?>, 'edit')"><i class="fas fa-edit"></i> Parameters</button>
                    <button type="button" class="hub-tab-btn" onclick="switchHubTab(<?php echo $o['id']; ?>, 'audit')"><i class="fas fa-history"></i> Audit Trail</button>
                </div>
                
                <!-- TAB 1: OPERATIONS -->
                <div id="tab-ops-<?php echo $o['id']; ?>" class="tab-pane active">
                    
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem;">
                        <h4 style="margin:0 0 0.5rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary);">Order Financials</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.8rem;">
                            <div>Subtotal Products:</div>
                            <div style="text-align:right; font-weight:700;">$<?php echo number_format($o['total_amount'] - $o['shipping_amount'] - $o['tax_amount'] + $o['discount_amount'], 2); ?></div>
                            <div>Applied Coupon Discount:</div>
                            <div style="text-align:right; font-weight:700; color:var(--rose);">-$<?php echo number_format($o['discount_amount'], 2); ?></div>
                            <div>Shipping:</div>
                            <div style="text-align:right; font-weight:700;">$<?php echo number_format($o['shipping_amount'], 2); ?></div>
                            <div>Tax Rate Charges:</div>
                            <div style="text-align:right; font-weight:700;">$<?php echo number_format($o['tax_amount'], 2); ?></div>
                            <div style="font-weight:800; font-size:0.9rem; border-top:1px solid #cbd5e1; padding-top:0.4rem; margin-top:0.4rem;">Total Charge:</div>
                            <div style="font-weight:800; font-size:0.9rem; border-top:1px solid #cbd5e1; padding-top:0.4rem; margin-top:0.4rem; text-align:right; color:var(--primary-dark);">$<?php echo number_format($o['total_amount'], 2); ?></div>
                        </div>
                        <div style="margin-top:0.8rem; font-size:0.75rem; color:var(--text-muted); border-top:1px solid #e2e8f0; padding-top:0.8rem;">
                            <i class="fas fa-credit-card"></i> Payment Method: <strong><?php echo $o['payment_method']; ?></strong>
                            <?php if ($o['payment_details']): ?>
                                <div style="background:#f1f5f9; padding:0.5rem; border-radius:6px; font-family:monospace; font-size:0.7rem; word-break:break-all; margin-top:0.25rem; margin-bottom: 0.5rem;"><?php echo e($o['payment_details']); ?></div>
                            <?php endif; ?>
                            <?php
                            // Fetch customer's current wallet balance
                            $stmt_w = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
                            $stmt_w->execute([$o['user_id']]);
                            $user_wallet_balance = (float)($stmt_w->fetch()['balance'] ?? 0);
                            ?>
                            <div style="margin-top:0.4rem; font-size:0.8rem;">
                                Wholesaler Wallet Balance: 
                                <strong style="color: <?php echo ($user_wallet_balance >= 0) ? 'var(--primary)' : 'var(--accent)'; ?>;">
                                    $<?php echo number_format($user_wallet_balance, 2); ?>
                                </strong>
                                <?php if ($user_wallet_balance < 0): ?>
                                    <span style="background:rgba(244,63,94,0.1); color:var(--rose); padding:2px 6px; border-radius:4px; font-size:0.65rem; font-weight:800; margin-left:0.25rem;">Outstanding Debt</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Record Offline Payment Form (Admin/Manager Only) -->
                    <?php if (in_array($user_role, ['admin', 'manager'])): ?>
                        <div style="background: rgba(16, 185, 129, 0.03); border: 1px dashed rgba(16, 185, 129, 0.3); padding: 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
                            <h4 style="margin:0 0 0.75rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:#15803d; font-size:0.9rem;"><i class="fas fa-handholding-usd"></i> Record Offline Payment</h4>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <input type="hidden" name="record_order_payment" value="1">
                                
                                <div class="form-group" style="margin-bottom:0.75rem;">
                                    <label style="font-size:0.7rem; font-weight:700; display:block; margin-bottom:2px;">Payment Amount ($)</label>
                                    <input type="number" step="0.01" name="payment_amount" value="<?php echo number_format($o['total_amount'], 2, '.', ''); ?>" min="0.01" required style="border-radius:6px; font-size:0.8rem; padding:0.4rem;">
                                </div>
                                <div class="form-group" style="margin-bottom:0.75rem;">
                                    <label style="font-size:0.7rem; font-weight:700; display:block; margin-bottom:2px;">Method Reference</label>
                                    <select name="payment_method_ref" required style="border-radius:6px; font-size:0.8rem; padding:0.4rem;">
                                        <option value="Cash">Physical Cash Payment</option>
                                        <option value="Check">Corporate Check</option>
                                        <option value="Bank Wire">Bank Wire Transfer</option>
                                        <option value="Stripe">Stripe Checkout / Offline Card</option>
                                        <option value="Other">Other Method</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:1rem;">
                                    <label style="font-size:0.7rem; font-weight:700; display:block; margin-bottom:2px;">Reference/Transaction Notes</label>
                                    <input type="text" name="payment_notes" placeholder="e.g. Wire confirmation ref / Check #" style="border-radius:6px; font-size:0.8rem; padding:0.4rem;">
                                </div>
                                <button type="submit" class="btn btn-green" style="width:100%; padding:0.5rem; font-size:0.8rem; justify-content:center;"><i class="fas fa-check"></i> Credit User Wallet</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($user_role !== 'viewer'): ?>
                        <form method="POST" style="background:rgba(99,102,241,0.02); border:1px solid rgba(99,102,241,0.1); padding:1.25rem; border-radius:12px;">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <h4 style="margin:0 0 0.85rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary); font-size:0.9rem;"><i class="fas fa-exchange-alt" style="color:var(--accent);"></i> Transition Status Gate</h4>
                            
                            <div class="form-group">
                                <label style="font-size:0.75rem; font-weight:700;">Fulfillment Status</label>
                                <select name="fulfillment_status" style="border-radius:8px; font-size:0.85rem; padding:0.6rem; border:1px solid #cbd5e1; width: 100%;">
                                    <?php foreach ($statuses as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo ($o['fulfillment_status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="font-size:0.75rem; font-weight:700;">Payment Status</label>
                                <select name="payment_status" style="border-radius:8px; font-size:0.85rem; padding:0.6rem; border:1px solid #cbd5e1; width: 100%;">
                                    <?php foreach (['Unpaid', 'Paid', 'Refunded'] as $pst): ?>
                                        <option value="<?php echo $pst; ?>" <?php echo ($o['payment_status'] === $pst) ? 'selected' : ''; ?>><?php echo $pst; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom:1.25rem;">
                                <label style="font-size:0.75rem; font-weight:700;">Audit Log Transition Notes</label>
                                <input type="text" name="notes" placeholder="Reason/Details for status change..." required style="border-radius:8px; font-size:0.85rem; padding:0.6rem; border:1px solid #cbd5e1;">
                            </div>
                            
                            <button type="submit" name="update_status" class="btn btn-blue" style="width:100%; padding:0.75rem;"><i class="fas fa-save"></i> Execute Transition</button>
                        </form>
                    <?php else: ?>
                        <div style="border: 1px dashed var(--border-light); padding:1rem; border-radius:12px; font-size:0.8rem; color:var(--text-muted); text-align:center; font-style:italic;">
                            <i class="fas fa-lock"></i> Operations actions are disabled for System Auditor account.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Refund gate for admin -->
                    <?php if ($o['fulfillment_status'] === 'Rejected' && !$o['refund_approved']): ?>
                        <div style="margin-top: 1.5rem; background: rgba(244, 63, 94, 0.05); border: 1px dashed var(--rose); padding: 1.25rem; border-radius: 12px;">
                            <h4 style="margin:0 0 0.5rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--rose); font-size:0.9rem;"><i class="fas fa-undo"></i> B2B Refund Management Gate</h4>
                            <?php if ($user_role === 'admin'): ?>
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <div style="font-size:0.75rem; color:var(--text-main); margin-bottom:0.75rem;">Set the restocking/rejection fee to be deducted. The rest of the balance will be returned to the client's wallet.</div>
                                    
                                    <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:1rem;">
                                        <div style="font-size: 0.8rem; font-weight: 700;">Deduct Fee ($):</div>
                                        <input type="number" step="0.01" name="rejection_charge" value="0.00" min="0" max="<?php echo $o['total_amount']; ?>" style="width: 100px; padding: 0.5rem; font-size: 0.8rem; border-radius: 6px; border:1px solid #cbd5e1;">
                                    </div>
                                    <button type="submit" name="approve_refund" class="btn btn-red" style="width:100%; padding:0.6rem;"><i class="fas fa-wallet"></i> Approve & Refund Wallet</button>
                                </form>
                            <?php else: ?>
                                <div style="font-size:0.8rem; color:var(--text-muted); font-style:italic;"><i class="fas fa-lock"></i> Refund approvals are strictly gated to Administrator accounts.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: PICKING SPLITS -->
                <div id="tab-picks-<?php echo $o['id']; ?>" class="tab-pane">
                    <?php
                    // Fetch order items
                    $stmt_items = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND oi.is_deleted = 0");
                    $stmt_items->execute([$o['id']]);
                    $o_items = $stmt_items->fetchAll();
                    ?>
                    <?php if ($o['fulfillment_status'] === 'Processing'): ?>
                        <h4 style="margin:0 0 1rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary); font-size:0.9rem;"><i class="fas fa-boxes" style="color:var(--accent);"></i> Inventory Lot Picking Allocation</h4>
                        
                        <form method="POST">
                            <input type="hidden" name="save_picks" value="1">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            
                            <div style="display:flex; flex-direction:column; gap:1.25rem; margin-bottom:1.5rem;">
                                <?php foreach ($o_items as $item): ?>
                                    <?php
                                    // Fetch active lots
                                    $stmt_lots = $pdo->prepare("SELECT * FROM inventory_lots WHERE product_id = ? AND (qty_remaining > 0 OR id IN (SELECT lot_id FROM order_item_picks WHERE order_item_id = ? AND is_deleted = 0)) AND status = 'active' AND is_deleted = 0 ORDER BY expiry_date ASC");
                                    $stmt_lots->execute([$item['product_id'], $item['id']]);
                                    $p_lots = $stmt_lots->fetchAll();
                                    
                                    // Fetch currently saved picks
                                    $stmt_saved = $pdo->prepare("SELECT lot_id, qty FROM order_item_picks WHERE order_item_id = ? AND is_deleted = 0");
                                    $stmt_saved->execute([$item['id']]);
                                    $saved_picks = $stmt_saved->fetchAll(PDO::FETCH_KEY_PAIR);
                                    
                                    $allocated_sum = array_sum($saved_picks);
                                    ?>
                                    <div style="border:1px solid #f1f5f9; border-radius:10px; padding:1rem; background:#f8fafc;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; align-items:center;">
                                            <strong style="color:var(--secondary); font-size:0.8rem; max-width:60%;"><?php echo e($item['name']); ?></strong>
                                            <span style="font-size:0.75rem; font-weight:800;">Target Qty: <?php echo $item['qty']; ?></span>
                                        </div>
                                        
                                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:0.5rem;">
                                            <?php if (empty($p_lots)): ?>
                                                <div style="font-size:0.75rem; color:var(--rose); font-weight:700;"><i class="fas fa-exclamation-circle"></i> Out of stock: No active inventory LOTs found!</div>
                                            <?php endif; ?>
                                            <?php foreach ($p_lots as $lot): ?>
                                                <?php 
                                                $allocated_qty = $saved_picks[$lot['id']] ?? 0;
                                                $available_stock = $lot['qty_remaining'] + $allocated_qty;
                                                ?>
                                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; background:white; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #cbd5e1;">
                                                    <div>
                                                        <strong><?php echo e($lot['lot_number']); ?></strong> 
                                                        <span style="color:var(--text-muted); font-size:0.65rem;"> (Shelf: <?php echo e($lot['shelf_location'] ?: 'N/A'); ?> | Exp: <?php echo $lot['expiry_date'] ?: 'None'; ?>)</span>
                                                        <br><span style="color:var(--primary-dark); font-weight:700; font-size:0.65rem;">Stock Available: <?php echo $available_stock; ?> units</span>
                                                    </div>
                                                    <div style="display:flex; align-items:center; gap:0.25rem;">
                                                        <span style="font-size:0.65rem;">Pick:</span>
                                                        <input type="number" 
                                                               class="pick-input-<?php echo $o['id']; ?>-<?php echo $item['id']; ?>"
                                                               name="picks[<?php echo $item['id']; ?>][<?php echo $lot['id']; ?>]" 
                                                               value="<?php echo $allocated_qty; ?>" 
                                                               min="0" 
                                                               max="<?php echo $available_stock; ?>" 
                                                               onchange="validatePicks(<?php echo $o['id']; ?>, <?php echo $item['id']; ?>, <?php echo $item['qty']; ?>)"
                                                               onkeyup="validatePicks(<?php echo $o['id']; ?>, <?php echo $item['id']; ?>, <?php echo $item['qty']; ?>)"
                                                               style="width:55px; padding:0.25rem; text-align:center; border-radius:4px; border:1px solid #cbd5e1; font-size:0.75rem;">
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Pick status indicator -->
                                        <div style="text-align:right;">
                                            <span id="pick-status-<?php echo $o['id']; ?>-<?php echo $item['id']; ?>" class="pick-badge <?php echo ($allocated_sum === (int)$item['qty']) ? 'success' : 'warning'; ?>">
                                                <?php if ($allocated_sum === (int)$item['qty']): ?>
                                                    ✔ Allocated: <?php echo $allocated_sum; ?> / <?php echo $item['qty']; ?>
                                                <?php else: ?>
                                                    ⚠ Allocated: <?php echo $allocated_sum; ?> / <?php echo $item['qty']; ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($user_role !== 'viewer'): ?>
                                <button type="submit" name="save_picks" class="btn btn-green" style="width:100%; padding:0.75rem;"><i class="fas fa-truck-loading"></i> Save allocations & Ready to Ship</button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <!-- Show allocations as read-only -->
                        <h4 style="margin:0 0 1rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary); font-size:0.9rem;"><i class="fas fa-boxes" style="color:var(--text-muted);"></i> Allocated Inventory Picking Details</h4>
                        <div style="display:flex; flex-direction:column; gap:1rem;">
                            <?php foreach ($o_items as $item): ?>
                                <?php
                                $stmt_saved = $pdo->prepare("
                                    SELECT p.*, l.lot_number, l.shelf_location 
                                    FROM order_item_picks p 
                                    JOIN inventory_lots l ON p.lot_id = l.id 
                                    WHERE p.order_item_id = ? AND p.is_deleted = 0
                                ");
                                $stmt_saved->execute([$item['id']]);
                                $item_picks = $stmt_saved->fetchAll();
                                ?>
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.85rem;">
                                    <div style="font-weight:700; font-size:0.8rem; margin-bottom:0.4rem;"><?php echo e($item['name']); ?> (Qty Ordered: <?php echo $item['qty']; ?>)</div>
                                    <?php if (empty($item_picks)): ?>
                                        <div style="font-size:0.75rem; color:var(--text-muted); font-style:italic;"><i class="fas fa-info-circle"></i> No lots allocated for this item yet.</div>
                                    <?php endif; ?>
                                    <?php foreach ($item_picks as $ip): ?>
                                        <div style="font-size:0.75rem; display:flex; justify-content:space-between; padding:0.25rem 0; border-bottom:1px dashed #f1f5f9;">
                                            <span>Lot: <strong><?php echo e($ip['lot_number']); ?></strong> (Shelf: <?php echo e($ip['shelf_location'] ?: 'Unassigned'); ?>)</span>
                                            <span>Allocated: <strong><?php echo $ip['qty']; ?> units</strong></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="background:rgba(99,102,241,0.05); color:var(--accent); padding:0.75rem; border-radius:8px; font-size:0.75rem; font-weight:700; text-align:center; margin-top:1.5rem;">
                            <i class="fas fa-info-circle"></i> Picking splits are only editable during the 'Processing' status stage.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 3: EDIT PARAMETERS -->
                <div id="tab-edit-<?php echo $o['id']; ?>" class="tab-pane">
                    <?php if ($user_role === 'admin'): ?>
                        <h4 style="margin:0 0 1rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary); font-size:0.9rem;"><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Order Parameters</h4>
                        
                        <form method="POST">
                            <input type="hidden" name="edit_order" value="1">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label style="font-size:0.75rem; font-weight:700;">Delivery Address String</label>
                                <input type="text" name="delivery_address" value="<?php echo e($o['delivery_address']); ?>" style="padding:0.6rem; border-radius:8px; font-size:0.85rem; border:1px solid #cbd5e1;">
                            </div>
                            
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.75rem; font-weight:700;">Shipping Fee ($)</label>
                                    <input type="number" step="0.01" name="shipping_amount" value="<?php echo $o['shipping_amount']; ?>" style="padding:0.6rem; border-radius:8px; font-size:0.85rem; border:1px solid #cbd5e1;">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.75rem; font-weight:700;">Tax Charge ($)</label>
                                    <input type="number" step="0.01" name="tax_amount" value="<?php echo $o['tax_amount']; ?>" style="padding:0.6rem; border-radius:8px; font-size:0.85rem; border:1px solid #cbd5e1;">
                                </div>
                            </div>

                            <div style="background: #f8fafc; border: 1px solid var(--border-light); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                                <div style="font-size: 0.75rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.25rem;">Negotiated Grand Total Discount:</div>
                                <div style="font-size: 0.8rem; color: #b45309; margin-bottom: 0.5rem; font-weight: 700;">
                                    <?php 
                                    if ($o['requested_discount_type'] !== 'none') {
                                        $symbol = $o['requested_discount_type'] === 'percent' ? '%' : '$';
                                        echo "Wholesaler requested: " . $o['requested_discount_value'] . $symbol;
                                    } else {
                                        echo "No grand discount requested.";
                                    }
                                    ?>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.7rem; font-weight:700; color: var(--secondary);">Admin Approved Discount ($) *</label>
                                    <input type="number" step="0.01" min="0" name="admin_adjusted_discount" value="<?php echo $o['admin_adjusted_discount']; ?>" style="padding:0.5rem; border-radius:6px; font-size:0.8rem; border:1px solid #cbd5e1; width: 100%;">
                                </div>
                            </div>
                            
                            <h5 style="font-weight:800; margin:0 0 0.5rem 0; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">Adjust Items Quantities & Discounts</h5>
                            <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem;">
                                <?php foreach ($o_items as $item): ?>
                                    <div style="background:#f8fafc; padding:1rem; border-radius:8px; border:1px solid #f1f5f9;">
                                        <div style="font-weight:700; font-size:0.8rem; color:var(--secondary); margin-bottom:0.5rem;">
                                            <?php echo e($item['name']); ?> ($<?php echo number_format($item['price_at_purchase'], 2); ?>)
                                        </div>
                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
                                            <div class="form-group" style="margin:0;">
                                                <label style="font-size:0.65rem; color:var(--text-muted); font-weight:700;">Quantity:</label>
                                                <input type="number" name="item_qty[<?php echo $item['id']; ?>]" value="<?php echo $item['qty']; ?>" min="0" style="width:100%; padding:0.35rem; text-align:center; border-radius:6px; border:1px solid #cbd5e1; font-size:0.8rem;">
                                            </div>
                                            <div class="form-group" style="margin:0;">
                                                <label style="font-size:0.65rem; color:var(--text-muted); font-weight:700;">
                                                    Discount Adjust ($):
                                                    <?php if ($item['requested_discount_type'] !== 'none'): ?>
                                                        <small style="color:#b45309;">(Req: <?php echo $item['requested_discount_value']; ?><?php echo $item['requested_discount_type'] === 'percent' ? '%' : '$'; ?>)</small>
                                                    <?php endif; ?>
                                                </label>
                                                <input type="number" step="0.01" min="0" name="item_discount[<?php echo $item['id']; ?>]" value="<?php echo $item['admin_adjusted_discount']; ?>" style="width:100%; padding:0.35rem; text-align:center; border-radius:6px; border:1px solid #cbd5e1; font-size:0.8rem;">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="background:rgba(245,158,11,0.05); color:#d97706; border:1px solid rgba(245,158,11,0.1); padding:0.75rem; border-radius:8px; font-size:0.725rem; font-weight:600; margin-bottom:1.25rem; line-height:1.4;">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Increasing quantities or totals past the client's paid amount redirects the status to <strong>Pending Customer Approval</strong> for shortfall payments.
                            </div>

                            <button type="submit" class="btn btn-green" style="width:100%; padding:0.75rem;"><i class="fas fa-save"></i> Save Parameter Edits</button>
                        </form>
                    <?php else: ?>
                        <div style="text-align:center; padding:3rem 1.5rem; border: 1px dashed var(--border-light); border-radius:12px; color:var(--text-muted);">
                            <i class="fas fa-lock" style="font-size:2.5rem; color:var(--rose); opacity:0.4; margin-bottom:1rem; display:block;"></i>
                            <h4 style="margin:0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary);">Restricted Action</h4>
                            <p style="font-size:0.8rem; margin-top:0.5rem; line-height:1.4;">Only users with the <strong>Administrator (admin)</strong> role are authorized to edit order item quantities, shipping fees, or tax parameters.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 4: AUDIT TIMELINE -->
                <div id="tab-audit-<?php echo $o['id']; ?>" class="tab-pane">
                    <h4 style="margin:0 0 1rem 0; font-family:'Plus Jakarta Sans',sans-serif; color:var(--secondary); font-size:0.9rem;"><i class="fas fa-history" style="color:var(--accent);"></i> Order Status History Trail</h4>
                    
                    <?php
                    // Fetch history
                    $stmt_h = $pdo->prepare("
                        SELECT h.*, u.username 
                        FROM order_status_history h 
                        LEFT JOIN users u ON h.changed_by = u.id 
                        WHERE h.order_id = ? 
                        ORDER BY h.created_at DESC
                    ");
                    $stmt_h->execute([$o['id']]);
                    $history_logs = $stmt_h->fetchAll();
                    ?>
                    
                    <?php if (empty($history_logs)): ?>
                        <div style="font-size:0.8rem; color:var(--text-muted); font-style:italic; text-align:center; padding:2rem 0;">No status change logs found for this order.</div>
                    <?php else: ?>
                        <div class="timeline-trail">
                            <?php foreach ($history_logs as $h_idx => $hl): ?>
                                <div class="timeline-event <?php echo ($h_idx === 0) ? 'active-event' : ''; ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-time-label"><i class="far fa-clock"></i> <?php echo date('M d, Y H:i:s', strtotime($hl['created_at'])); ?></div>
                                    <div class="timeline-header-text">Transitioned to: <span style="color:var(--accent);"><?php echo e($hl['status']); ?></span></div>
                                    <div style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">By user: @<?php echo e($hl['username'] ?: 'system'); ?></div>
                                    <?php if ($hl['notes']): ?>
                                        <div class="timeline-notes-box"><?php echo e($hl['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Keep selected order active in JS
let activeOrderId = <?php echo json_encode($active_order_id); ?>;

function selectOrder(orderId) {
    if (!orderId) return;

    // Remove active highlight from all rows
    const rows = document.querySelectorAll('.order-row');
    rows.forEach(r => r.classList.remove('active-row'));

    // Highlight selected row
    const selectedRow = document.getElementById('ledger-row-' + orderId);
    if (selectedRow) {
        selectedRow.classList.add('active-row');
    }

    // Hide placeholder
    const placeholder = document.getElementById('orderPlaceholder');
    if (placeholder) placeholder.style.display = 'none';

    // Hide all order hub panels
    const hubs = document.querySelectorAll('.order-hub-panel');
    hubs.forEach(h => h.style.display = 'none');

    // Show active hub panel
    const activeHub = document.getElementById('hub-container-' + orderId);
    if (activeHub) {
        activeHub.style.display = 'block';
    }

    activeOrderId = orderId;
}

function switchHubTab(orderId, tabName) {
    // Hide all tabs for this order
    const tabPanes = document.querySelectorAll('#hub-container-' + orderId + ' .tab-pane');
    tabPanes.forEach(pane => pane.classList.remove('active'));

    // Remove active class from buttons
    const tabButtons = document.querySelectorAll('#hub-container-' + orderId + ' .hub-tab-btn');
    tabButtons.forEach(btn => btn.classList.remove('active'));

    // Show selected pane and button
    const targetPane = document.getElementById('tab-' + tabName + '-' + orderId);
    if (targetPane) targetPane.classList.add('active');

    // Find and set active tab button
    event.currentTarget.classList.add('active');
}

// Live client-side search filtering
function filterOrders() {
    const query = document.getElementById('orderSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.order-row');
    
    rows.forEach(row => {
        const searchContent = row.querySelector('.search-data').textContent.toLowerCase();
        if (searchContent.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Real-time Pick Quantity Validation
function validatePicks(orderId, itemId, requiredQty) {
    const inputs = document.querySelectorAll('.pick-input-' + orderId + '-' + itemId);
    let total = 0;
    inputs.forEach(input => {
        total += parseInt(input.value || 0);
    });
    
    const statusLabel = document.getElementById('pick-status-' + orderId + '-' + itemId);
    if (statusLabel) {
        if (total === requiredQty) {
            statusLabel.className = 'pick-badge success';
            statusLabel.innerHTML = `✔ Allocated: ${total} / ${requiredQty}`;
        } else {
            statusLabel.className = 'pick-badge warning';
            statusLabel.innerHTML = `⚠ Allocated: ${total} / ${requiredQty}`;
        }
    }
}

// Trigger initial selection on load
window.addEventListener('DOMContentLoaded', () => {
    if (activeOrderId) {
        selectOrder(activeOrderId);
    }
});
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
