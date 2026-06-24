<?php
/**
 * User Order Editing & Reordering Module
 */
restrict_to(['wholesale_user', 'executive']);

$user_id = $_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);
$is_reorder = (isset($_GET['action']) && $_GET['action'] === 'reorder');

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found or access denied.");
}

// Check if editable (must be before Ready to Ship)
if (!$is_reorder) {
    $allowed_fulfillment_statuses = ['Pending', 'Processing', 'Pending Customer Approval'];
    if (!in_array($order['fulfillment_status'], $allowed_fulfillment_statuses)) {
        die("This order cannot be edited as it has already transitioned to " . htmlspecialchars($order['fulfillment_status']) . ".");
    }
}

$error_msg = '';
$success_msg = '';

// Fetch active shipping locations for address management
$locations = $pdo->query("SELECT * FROM locations WHERE is_deleted = 0")->fetchAll();

// Fetch user's addresses
$stmt = $pdo->prepare("SELECT a.*, l.name as location_name, l.tax_percent, l.base_delivery_charge, l.per_unit_weight_charge, l.min_order_amount, l.max_order_amount, l.shipping_type FROM user_addresses a LEFT JOIN locations l ON a.location_id = l.id WHERE a.user_id = ? AND a.is_deleted = 0");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Handle POST Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address_id = (int)($_POST['address_id'] ?? 0);
    $items_json = $_POST['order_items_json'] ?? '[]';
    $updated_items = json_decode($items_json, true);

    $pdo->beginTransaction();
    try {
        if (!$is_reorder) {
            // 1. Temporarily restore the order stock to check availability
            restore_order_stock($pdo, $order_id);
        }

        // 2. Fetch target address details
        $stmt = $pdo->prepare("SELECT a.*, l.name as location_name, l.tax_percent, l.base_delivery_charge, l.per_unit_weight_charge, l.min_order_amount, l.max_order_amount, l.shipping_type FROM user_addresses a LEFT JOIN locations l ON a.location_id = l.id WHERE a.id = ? AND a.user_id = ? AND a.is_deleted = 0");
        $stmt->execute([$address_id, $user_id]);
        $target_addr = $stmt->fetch();
        if (!$target_addr) {
            throw new Exception("Invalid shipping address selected.");
        }

        // 3. Recalculate and validate each item quantity and stock
        $new_subtotal = 0;
        $new_total_weight = 0;
        $saved_item_ids = [];
        $items_to_insert = [];

        foreach ($updated_items as $item) {
            $qty = (int)$item['qty'];
            if ($qty <= 0) continue;

            $pid = (int)$item['product_id'];
            $vid = (int)($item['variant_id'] ?? 0);
            $item_id = (int)($item['id'] ?? 0);

            // Fetch product info to validate
            $stmt = $pdo->prepare("SELECT stock_qty, min_order_qty, base_price, weight, name FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $prod = $stmt->fetch();
            if (!$prod) {
                throw new Exception("Product ID $pid no longer exists.");
            }

            $max_stock = (int)$prod['stock_qty'];
            $variant_name_str = '';
            if ($vid > 0) {
                $stmt = $pdo->prepare("SELECT CONCAT(variant_type, ': ', variant_value) AS name, price_modifier, stock_qty FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
                $stmt->execute([$vid, $pid]);
                $v_details = $stmt->fetch();
                if (!$v_details) {
                    throw new Exception("Variant selected is invalid.");
                }
                $max_stock = (int)$v_details['stock_qty'];
                $variant_name_str = ' (' . $v_details['name'] . ')';
            }

            if ($qty > $max_stock) {
                throw new Exception("Insufficient stock for product: " . htmlspecialchars($prod['name']) . $variant_name_str . " (Requested: $qty, Available: $max_stock).");
            }

            // Recalculate tiered prices
            $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
            $stmt->execute([$pid, $qty]);
            $tier = $stmt->fetch();

            $discount = get_product_discount($pdo, $pid, $user_id);
            $base_unit_price = $tier ? $tier['unit_price'] : $prod['base_price'];
            $price = calculate_discounted_price($base_unit_price, $discount);

            if ($vid > 0) {
                $price += (float)$v_details['price_modifier'];
            }

            $item_subtotal = $price * $qty;
            $new_subtotal += $item_subtotal;
            $new_total_weight += ($prod['weight'] * $qty);

            if ($is_reorder) {
                $items_to_insert[] = [
                    'product_id' => $pid,
                    'variant_id' => $vid > 0 ? $vid : null,
                    'qty' => $qty,
                    'price' => $price
                ];
            } else {
                if ($item_id > 0) {
                    // Update existing item
                    $stmt_up = $pdo->prepare("UPDATE order_items SET qty = ?, price_at_purchase = ?, is_deleted = 0 WHERE id = ? AND order_id = ?");
                    $stmt_up->execute([$qty, $price, $item_id, $order_id]);
                    $saved_item_ids[] = $item_id;
                } else {
                    // Check duplicate items
                    $stmt_dup = $pdo->prepare("SELECT id FROM order_items WHERE order_id = ? AND product_id = ? AND IFNULL(variant_id, 0) = ? AND is_deleted = 0");
                    $stmt_dup->execute([$order_id, $pid, $vid]);
                    $dup_id = $stmt_dup->fetchColumn();

                    if ($dup_id) {
                        $stmt_up = $pdo->prepare("UPDATE order_items SET qty = qty + ?, price_at_purchase = ? WHERE id = ?");
                        $stmt_up->execute([$qty, $price, $dup_id]);
                        $saved_item_ids[] = $dup_id;
                    } else {
                        // Insert new item
                        $stmt_ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, qty, price_at_purchase) VALUES (?, ?, ?, ?, ?)");
                        $stmt_ins->execute([$order_id, $pid, $vid > 0 ? $vid : null, $qty, $price]);
                        $saved_item_ids[] = $pdo->lastInsertId();
                    }
                }
            }
        }

        if (!$is_reorder) {
            // Soft-delete items not in the updated list
            if (!empty($saved_item_ids)) {
                $placeholders = implode(',', array_fill(0, count($saved_item_ids), '?'));
                $stmt_del = $pdo->prepare("UPDATE order_items SET is_deleted = 1 WHERE order_id = ? AND id NOT IN ($placeholders)");
                $stmt_del->execute(array_merge([$order_id], $saved_item_ids));
            } else {
                $stmt_del = $pdo->prepare("UPDATE order_items SET is_deleted = 1 WHERE order_id = ?");
                $stmt_del->execute([$order_id]);
            }
        }

        if ($new_subtotal <= 0) {
            throw new Exception("You cannot place or update an order to have 0 items.");
        }

        // Validate state minimum/maximum limits
        $state_min_order = (float)$target_addr['min_order_amount'];
        $state_max_order = (float)$target_addr['max_order_amount'];
        if ($new_subtotal < $state_min_order) {
            throw new Exception("Order subtotal ($" . number_format($new_subtotal, 2) . ") is below the minimum order amount ($" . number_format($state_min_order, 2) . ") for state " . htmlspecialchars($target_addr['location_name']) . ".");
        }
        if ($new_subtotal > $state_max_order) {
            throw new Exception("Order subtotal ($" . number_format($new_subtotal, 2) . ") exceeds the maximum order amount ($" . number_format($state_max_order, 2) . ") for state " . htmlspecialchars($target_addr['location_name']) . ".");
        }

        // 4. Recalculate Coupon or Global discounts
        $coupon_discount = 0;
        if ($order['coupon_id']) {
            $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
            $stmt->execute([$order['coupon_id']]);
            $coupon = $stmt->fetch();
            if ($coupon) {
                if ($coupon['type'] === 'fixed') {
                    $coupon_discount = (float)$coupon['value'];
                } elseif ($coupon['type'] === 'percentage') {
                    $coupon_discount = ($new_subtotal * (float)$coupon['value']) / 100;
                    if ($coupon['max_discount'] && $coupon_discount > $coupon['max_discount']) {
                        $coupon_discount = (float)$coupon['max_discount'];
                    }
                }
                if ($coupon_discount > $new_subtotal) $coupon_discount = $new_subtotal;
            }
        }
        $global_discount = calculate_global_discount_amount($pdo, $new_subtotal, $user_id);
        $total_discount = $coupon_discount + $global_discount;

        // 5. Recalculate Shipping and Taxes
        $shipping_type = $target_addr['shipping_type'] ?? 'default';
        if ($shipping_type === 'free' || $shipping_type === 'manual') {
            $shipping_charge = 0.00;
        } else {
            $shipping_charge = (float)$target_addr['base_delivery_charge'] + ($new_total_weight * (float)$target_addr['per_unit_weight_charge']);
        }

        $tax_on_shipping = (bool)get_setting($pdo, 'tax_on_shipping', 0);
        $taxable_amount = ($new_subtotal - $total_discount) + ($tax_on_shipping ? $shipping_charge : 0);
        if ($taxable_amount < 0) $taxable_amount = 0;
        $tax_amount = ($taxable_amount * (float)$target_addr['tax_percent']) / 100;

        $new_grand_total = ($new_subtotal - $total_discount) + $shipping_charge + $tax_amount;

        // 6. Wallet Balance Adjustments
        $address_string = $target_addr['address_line'] . ", " . $target_addr['city'];
        if (!empty($target_addr['location_name'])) {
            $address_string .= " (" . $target_addr['location_name'] . ", Tax: " . $target_addr['tax_percent'] . "%)";
        }

        if ($is_reorder) {
            $diff = $new_grand_total;

            $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet_balance = (float)($stmt->fetch()['balance'] ?? 0);
            $overdraft_limit = (float)get_setting($pdo, 'wallet_overdraft_limit', '1000.00');

            if (($wallet_balance - $diff) < -$overdraft_limit) {
                throw new Exception("Order rejected. This transaction requires a payment of $" . number_format($diff, 2) . ", which exceeds your wallet's maximum overdraft limit of $" . number_format($overdraft_limit, 2) . " (Current Balance: $" . number_format($wallet_balance, 2) . ").");
            }

            // Create a brand new order record
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, discount_amount, coupon_id, payment_method, delivery_address, status, payment_status, fulfillment_status) VALUES (?, ?, ?, ?, ?, ?, 'Wallet', ?, 'Payment Verified', 'Paid', 'Pending')");
            $stmt->execute([$user_id, $new_grand_total, $tax_amount, $shipping_charge, $total_discount, $order['coupon_id'] ?: null, $address_string]);
            $new_order_id = $pdo->lastInsertId();

            // Insert new items
            foreach ($items_to_insert as $itm) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, qty, price_at_purchase) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$new_order_id, $itm['product_id'], $itm['variant_id'], $itm['qty'], $itm['price']]);
            }

            // Debit user's wallet
            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
            $stmt->execute([$user_id, $new_grand_total, "Payment for new order #$new_order_id reordered from order #$order_id"]);

            // Deduct stock for the new order
            deduct_order_stock($pdo, $new_order_id);

            // Insert status history
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Payment Verified', ?, 'Order placed via reordering')");
            $stmt->execute([$new_order_id, $user_id]);

            $pdo->commit();

            // Dispatch invoice email
            require_once 'includes/mailer.php';
            send_invoice_email($pdo, $new_order_id, "Order Placed via Reorder");

            $_SESSION['edit_success_msg'] = "Reordered Order #$new_order_id placed successfully! Invoice details have been emailed.";
            header("Location: " . BASE_URL . "orders");
            exit;
        } else {
            $old_grand_total = (float)$order['total_amount'];
            $diff = $new_grand_total - $old_grand_total;

            $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet_balance = (float)($stmt->fetch()['balance'] ?? 0);
            $overdraft_limit = (float)get_setting($pdo, 'wallet_overdraft_limit', '1000.00');

            if (($wallet_balance - $diff) < -$overdraft_limit) {
                throw new Exception("Order edit rejected. This modification requires an additional payment of $" . number_format($diff, 2) . ", which exceeds your wallet's maximum overdraft limit of $" . number_format($overdraft_limit, 2) . " (Current Balance: $" . number_format($wallet_balance, 2) . ").");
            }

            if ($diff > 0) {
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
                $stmt->execute([$user_id, $diff, "Adjustment for Order #$order_id modification (Total increased)"]);
            } elseif ($diff < 0) {
                $refund = abs($diff);
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                $stmt->execute([$user_id, $refund, "Refund adjustment for Order #$order_id modification (Total decreased)"]);
            }

            if ($order['status'] === 'Pending Customer Approval' || $order['fulfillment_status'] === 'Pending Customer Approval') {
                $stmt = $pdo->prepare("UPDATE orders SET total_amount = ?, tax_amount = ?, shipping_amount = ?, discount_amount = ?, delivery_address = ?, status = 'Payment Verified', fulfillment_status = 'Pending', pending_change_details = NULL WHERE id = ?");
                $stmt->execute([$new_grand_total, $tax_amount, $shipping_charge, $total_discount, $address_string, $order_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET total_amount = ?, tax_amount = ?, shipping_amount = ?, discount_amount = ?, delivery_address = ? WHERE id = ?");
                $stmt->execute([$new_grand_total, $tax_amount, $shipping_charge, $total_discount, $address_string, $order_id]);
            }

            // Re-deduct stock
            deduct_order_stock($pdo, $order_id);

            // Status history logging
            $new_status = ($order['status'] === 'Pending Customer Approval') ? 'Payment Verified' : $order['status'];
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, 'Order modified by customer')");
            $stmt->execute([$order_id, $new_status, $user_id]);

            $pdo->commit();

            // Dispatch Invoice Email
            require_once 'includes/mailer.php';
            send_invoice_email($pdo, $order_id, "Order Modified by User");

            $_SESSION['edit_success_msg'] = "Order #$order_id modified successfully! Updated invoice details have been emailed.";
            header("Location: " . BASE_URL . "orders");
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        
        // Restore stock to order if failed
        if (!$is_reorder) {
            try {
                deduct_order_stock($pdo, $order_id);
            } catch (Exception $ex) {
                // Silently handle
            }
        }
        
        $error_msg = $e->getMessage();
    }
}

// PHP side: prepare JSON variables
$products_js = [];
$db_products = $pdo->query("SELECT id, name, base_price, weight, stock_qty FROM products WHERE is_deleted = 0 ORDER BY name ASC")->fetchAll();
foreach ($db_products as $p) {
    $discount = get_product_discount($pdo, $p['id'], $user_id);
    $products_js[$p['id']] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'base_price' => (float)$p['base_price'],
        'weight' => (float)$p['weight'],
        'stock_qty' => (int)$p['stock_qty'],
        'discount_percent' => $discount && $discount['percent'] > 0 ? (float)$discount['percent'] : 0,
        'discount_amount' => $discount && $discount['amount'] > 0 ? (float)$discount['amount'] : 0
    ];
}

$db_tiers = $pdo->query("SELECT product_id, min_qty, unit_price FROM product_price_tiers WHERE is_deleted = 0 ORDER BY product_id ASC, min_qty ASC")->fetchAll();
$tiers_js = [];
foreach ($db_tiers as $t) {
    $tiers_js[$t['product_id']][] = [
        'min_qty' => (int)$t['min_qty'],
        'unit_price' => (float)$t['unit_price']
    ];
}

$db_variants = $pdo->query("SELECT id, product_id, CONCAT(variant_type, ': ', variant_value) AS name, price_modifier, stock_qty FROM product_variants WHERE is_deleted = 0 ORDER BY product_id ASC, variant_type ASC, variant_value ASC")->fetchAll();
$variants_js = [];
foreach ($db_variants as $v) {
    $variants_js[$v['product_id']][] = [
        'id' => (int)$v['id'],
        'name' => $v['name'],
        'price_modifier' => (float)$v['price_modifier'],
        'stock_qty' => (int)$v['stock_qty']
    ];
}

$addresses_js = [];
foreach ($addresses as $addr) {
    $addresses_js[$addr['id']] = [
        'id' => (int)$addr['id'],
        'address_line' => $addr['address_line'],
        'city' => $addr['city'],
        'location_name' => $addr['location_name'] ?? '',
        'tax_percent' => (float)($addr['tax_percent'] ?? 0),
        'base_delivery_charge' => (float)($addr['base_delivery_charge'] ?? 0),
        'per_unit_weight_charge' => (float)($addr['per_unit_weight_charge'] ?? 0),
        'min_order_amount' => (float)($addr['min_order_amount'] ?? 0),
        'max_order_amount' => (float)($addr['max_order_amount'] ?? 999999.99),
        'shipping_type' => $addr['shipping_type'] ?? 'default'
    ];
}

$coupon_js = null;
if ($order['coupon_id']) {
    $stmt = $pdo->prepare("SELECT id, code, type, value, max_discount, min_spend FROM coupons WHERE id = ?");
    $stmt->execute([$order['coupon_id']]);
    $coupon_js = $stmt->fetch(PDO::FETCH_ASSOC);
}

$global_discounts_js = get_active_global_discounts($pdo, $user_id);
$tax_on_shipping_setting = (bool)get_setting($pdo, 'tax_on_shipping', 0);

// Initialize existing items list
$existing_items_js = [];
$stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, CONCAT(pv.variant_type, ': ', pv.variant_value) as variant_name, p.weight, p.base_price FROM order_items oi JOIN products p ON oi.product_id = p.id LEFT JOIN product_variants pv ON oi.variant_id = pv.id WHERE oi.order_id = ? AND oi.is_deleted = 0");
$stmt->execute([$order_id]);
$db_items = $stmt->fetchAll();
foreach ($db_items as $item) {
    $qty = (int)$item['qty'];
    $price = (float)$item['price_at_purchase'];
    
    if ($is_reorder) {
        // Determine maximum available stock for product/variant
        $stmt_check = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $stmt_check->execute([$item['product_id']]);
        $avail_stock = (int)$stmt_check->fetchColumn();
        if ($item['variant_id'] > 0) {
            $stmt_check = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
            $stmt_check->execute([$item['variant_id'], $item['product_id']]);
            $v_stock = $stmt_check->fetchColumn();
            if ($v_stock !== false) {
                $avail_stock = (int)$v_stock;
            }
        }
        
        $qty = min($qty, $avail_stock);
        if ($qty <= 0) {
            continue; // Skip items out of stock when reordering
        }
        
        // Recalculate price in case price tiers changed
        $stmt_tier = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
        $stmt_tier->execute([$item['product_id'], $qty]);
        $tier = $stmt_tier->fetch();
        
        $discount = get_product_discount($pdo, $item['product_id'], $user_id);
        $base_unit_price = $tier ? $tier['unit_price'] : $item['base_price'];
        $price = calculate_discounted_price($base_unit_price, $discount);
        
        if ($item['variant_id'] > 0) {
            $stmt_v = $pdo->prepare("SELECT price_modifier FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
            $stmt_v->execute([$item['variant_id'], $item['product_id']]);
            $v_modifier = $stmt_v->fetchColumn();
            if ($v_modifier !== false) {
                $price += (float)$v_modifier;
            }
        }
    }

    $existing_items_js[] = [
        'id' => $is_reorder ? 0 : (int)$item['id'],
        'product_id' => (int)$item['product_id'],
        'variant_id' => (int)$item['variant_id'],
        'name' => $item['product_name'],
        'variant_name' => $item['variant_name'] ?: '',
        'qty' => $qty,
        'weight' => (float)$item['weight'],
        'price' => $price
    ];
}
?>

<div class="section-title">
    <?php if ($is_reorder): ?>
        <i class="fas fa-redo" style="color: var(--primary);"></i> Reorder from Past Order #<?php echo $order['id']; ?>
    <?php else: ?>
        <i class="fas fa-edit" style="color: var(--primary);"></i> Edit Order #<?php echo $order['id']; ?>
    <?php endif; ?>
</div>

<?php if ($error_msg): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1.25rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error_msg); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['reorder_cloned'])): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> Cloned past order items into a new draft. You can now adjust, add products, select shipping address, and submit to finalize!
    </div>
<?php endif; ?>

<form method="POST" id="edit-order-form">
    <input type="hidden" name="order_items_json" id="order-items-json-input" value="">
    
    <div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; align-items: flex-start;">
        
        <!-- Left Side: Items & Product Selector -->
        <div>
            <!-- Order Items Editor -->
            <div class="card" style="padding: 2rem; margin-bottom: 1.5rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">
                    Modify Items List
                </h3>
                
                <div class="table-wrap" style="overflow-x: auto; border: none; margin-bottom: 0;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 45%;">Item Description</th>
                                <th style="width: 20%;">Price</th>
                                <th style="width: 25%; text-align: center;">Quantity</th>
                                <th style="width: 10%; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="order-items-tbody">
                            <!-- JS Rendered -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Product Adder -->
            <div class="card" style="padding: 2rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;">
                    <i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add Product to Order
                </h3>
                
                <div style="display: grid; grid-template-columns: 1.8fr 1fr 0.8fr auto; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Select Product</label>
                        <select id="add-product-select" style="padding: 0.55rem; border-radius: 8px; width: 100%; border: 1px solid var(--border-dark); font-family: inherit;" onchange="updateVariantOptions()">
                            <option value="">-- Choose Product --</option>
                            <!-- JS Rendered -->
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Variant</label>
                        <select id="add-variant-select" style="padding: 0.55rem; border-radius: 8px; width: 100%; border: 1px solid var(--border-dark); font-family: inherit;">
                            <option value="0">Standard (No Variant)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Qty</label>
                        <input type="number" id="add-qty-input" value="1" min="1" style="padding: 0.55rem; border-radius: 8px; width: 100%; border: 1px solid var(--border-dark); text-align: center; font-family: inherit;">
                    </div>
                    
                    <button type="button" class="btn btn-blue" onclick="addOrderItem()" style="height: 42px; border-radius: 8px; font-size: 0.85rem; border: none; font-weight: 800; justify-content: center; box-shadow: 0 4px 10px rgba(59,130,246,0.2);">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Side: Shipping & Summary -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Address Matrix selection -->
            <div class="card" style="padding: 2rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;">
                    <i class="fas fa-truck-loading" style="color: var(--primary);"></i> Shipping Destination
                </h3>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: 700; font-size: 0.85rem; color: var(--secondary); margin-bottom: 0.5rem; display: block;">Select Destination Address</label>
                    <select name="address_id" id="address-select" required style="border-radius: 8px; width: 100%; padding: 0.65rem; border: 1px solid var(--border-dark); font-family: inherit;" onchange="recalculateTotals()">
                        <?php foreach ($addresses as $addr): ?>
                            <?php 
                            $is_current = false;
                            $addr_clean = $addr['address_line'] . ", " . $addr['city'];
                            if (strpos($order['delivery_address'], $addr_clean) === 0) {
                                $is_current = true;
                            }
                            ?>
                            <option value="<?php echo $addr['id']; ?>" <?php echo $is_current ? 'selected' : ''; ?>>
                                <?php echo e($addr['address_line']) . ', ' . e($addr['city']); ?>
                                <?php if (!empty($addr['location_name'])): ?>
                                    — <?php echo e($addr['location_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Recalculations summary card -->
            <div class="card" style="padding: 2rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;">
                    <i class="fas fa-calculator" style="color: var(--primary);"></i> Price Summary
                </h3>
                
                <table style="width: 100%; font-size: 0.9rem; border-collapse: collapse; margin-bottom: 1.5rem;">
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td style="padding: 8px 0; color: var(--text-muted);">Items Subtotal:</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;" id="calc-subtotal">$0.00</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-light); color: #155724;" id="calc-discount-row">
                        <td style="padding: 8px 0;">Discounts:</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;" id="calc-discount">-$0.00</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td style="padding: 8px 0; color: var(--text-muted);">Shipping Charge:</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;" id="calc-shipping">$0.00</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td style="padding: 8px 0; color: var(--text-muted);">Taxes:</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;" id="calc-tax">$0.00</td>
                    </tr>
                    <tr style="font-size: 1.15rem; font-weight: 800;">
                        <td style="padding: 12px 0; color: var(--secondary);">Grand Total:</td>
                        <td style="padding: 12px 0; text-align: right; color: var(--primary);" id="calc-total">$0.00</td>
                    </tr>
                </table>

                <!-- Limits validation alerts -->
                <div id="limits-alert-box" style="display: none; background: rgba(244,63,94,0.08); color: #991b1b; padding: 1rem; border-radius: 8px; border: 1px solid rgba(244,63,94,0.15); margin-bottom: 1.5rem; font-size: 0.8rem; font-weight: 700;">
                    <i class="fas fa-times-circle"></i> <span id="limits-alert-text">Warning</span>
                </div>
                
                <div style="font-size: 0.775rem; color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.45;">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i> Submitting updates will automatically recalculate tiered prices, state tax rates, and logistics freight fees. Wallet transactions are processed transactionally.
                </div>
                
                <button type="submit" id="save-order-btn" class="btn btn-green" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1rem; border-radius: 10px; border: none; box-shadow: 0 10px 20px -5px var(--primary-glow);">
                    <i class="fas fa-shopping-basket"></i> <?php echo $is_reorder ? 'Place Reordered Order' : 'Save Order Changes'; ?>
                </button>
                <a href="/bolakausa/orders" class="btn btn-back" style="width: 100%; justify-content: center; padding: 0.8rem; font-size: 0.9rem; border-radius: 10px; margin-top: 0.75rem;">
                    Cancel & Return
                </a>
            </div>
        </div>
        
    </div>
</form>

<!-- JS Engine -->
<script>
// JSON Data exported from PHP
const products = <?php echo json_encode($products_js); ?>;
const priceTiers = <?php echo json_encode($tiers_js); ?>;
const productVariants = <?php echo json_encode($variants_js); ?>;
const addresses = <?php echo json_encode($addresses_js); ?>;
const coupon = <?php echo json_encode($coupon_js); ?>;
const globalDiscounts = <?php echo json_encode($global_discounts_js); ?>;
const taxOnShipping = <?php echo json_encode($tax_on_shipping_setting); ?>;

let orderItems = <?php echo json_encode($existing_items_js); ?>;

// Render initial elements
document.addEventListener('DOMContentLoaded', () => {
    populateProductDropdown();
    renderOrderItemsTable();
});

function populateProductDropdown() {
    const select = document.getElementById('add-product-select');
    select.innerHTML = '<option value="">-- Choose Product --</option>';
    for (const pid in products) {
        const p = products[pid];
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `${p.name} (Stock: ${p.stock_qty})`;
        select.appendChild(opt);
    }
}

function updateVariantOptions() {
    const productSelect = document.getElementById('add-product-select');
    const variantSelect = document.getElementById('add-variant-select');
    const pid = parseInt(productSelect.value);
    
    variantSelect.innerHTML = '<option value="0">Standard (No Variant)</option>';
    
    if (pid && productVariants[pid]) {
        const variantsList = productVariants[pid];
        variantsList.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            const sign = v.price_modifier >= 0 ? '+' : '';
            opt.textContent = `${v.name} (${sign}$${parseFloat(v.price_modifier).toFixed(2)}, Stock: ${v.stock_qty})`;
            variantSelect.appendChild(opt);
        });
    }
}

function renderOrderItemsTable() {
    const tbody = document.getElementById('order-items-tbody');
    tbody.innerHTML = '';
    
    if (orderItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:2rem;">No items in the order. Please add products.</td></tr>';
        recalculateTotals();
        return;
    }
    
    orderItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        
        const nameCell = document.createElement('td');
        nameCell.innerHTML = `<strong style="color: var(--secondary); font-size: 0.9rem;">${escapeHtml(item.name)}</strong>` +
                             (item.variant_name ? `<small style="display:block; color:var(--text-muted);">${escapeHtml(item.variant_name)}</small>` : '');
                             
        const priceCell = document.createElement('td');
        priceCell.innerHTML = `<strong style="font-size:0.95rem; color:var(--primary-dark);">$${parseFloat(item.price).toFixed(2)}</strong>`;
        
        const qtyCell = document.createElement('td');
        qtyCell.style.textAlign = 'center';
        qtyCell.innerHTML = `<input type="number" value="${item.qty}" min="1" onchange="updateItemQty(${index}, this.value)" style="width: 65px; padding: 0.4rem; text-align: center; border-radius: 6px; border: 1px solid var(--border-dark); font-family:inherit;">`;
        
        const actionCell = document.createElement('td');
        actionCell.style.textAlign = 'right';
        actionCell.innerHTML = `<button type="button" class="btn btn-red" onclick="removeOrderItem(${index})" style="padding:0.4rem 0.6rem; font-size:0.75rem; border-radius:6px; border:none; justify-content:center;"><i class="fas fa-trash-alt"></i></button>`;
        
        tr.appendChild(nameCell);
        tr.appendChild(priceCell);
        tr.appendChild(qtyCell);
        tr.appendChild(actionCell);
        tbody.appendChild(tr);
    });
    
    recalculateTotals();
}

function updateItemQty(index, newQty) {
    let qty = parseInt(newQty);
    if (isNaN(qty) || qty < 1) qty = 1;
    orderItems[index].qty = qty;
    
    // Recalculate price based on bulk tiers
    const item = orderItems[index];
    const prod = products[item.product_id];
    let unitPrice = prod.base_price;
    
    // Find bulk tier price
    if (priceTiers[item.product_id]) {
        const tiers = priceTiers[item.product_id];
        let applicableTierPrice = null;
        tiers.forEach(tier => {
            if (qty >= tier.min_qty) {
                applicableTierPrice = tier.unit_price;
            }
        });
        if (applicableTierPrice !== null) {
            unitPrice = applicableTierPrice;
        }
    }
    
    // Apply discount
    if (prod.discount_percent > 0) {
        unitPrice = unitPrice * (1 - prod.discount_percent / 100);
    } else if (prod.discount_amount > 0) {
        unitPrice = Math.max(0, unitPrice - prod.discount_amount);
    }
    
    // Add variant modifier if exists
    if (item.variant_id > 0 && productVariants[item.product_id]) {
        const variantsList = productVariants[item.product_id];
        const vari = variantsList.find(v => v.id === item.variant_id);
        if (vari) {
            unitPrice += vari.price_modifier;
        }
    }
    
    orderItems[index].price = unitPrice;
    renderOrderItemsTable();
}

function addOrderItem() {
    const productSelect = document.getElementById('add-product-select');
    const variantSelect = document.getElementById('add-variant-select');
    const qtyInput = document.getElementById('add-qty-input');
    
    const pid = parseInt(productSelect.value);
    const vid = parseInt(variantSelect.value);
    const qty = parseInt(qtyInput.value);
    
    if (!pid || isNaN(qty) || qty < 1) {
        alert("Please select a product and enter a valid quantity.");
        return;
    }
    
    const prod = products[pid];
    let vname = '';
    let vmodifier = 0;
    
    if (vid > 0 && productVariants[pid]) {
        const variantsList = productVariants[pid];
        const vari = variantsList.find(v => v.id === vid);
        if (vari) {
            vname = vari.name;
            vmodifier = vari.price_modifier;
        }
    }
    
    // Check if item already exists in current list
    const existingIndex = orderItems.findIndex(item => item.product_id === pid && item.variant_id === vid);
    if (existingIndex !== -1) {
        orderItems[existingIndex].qty += qty;
        updateItemQty(existingIndex, orderItems[existingIndex].qty);
        return;
    }
    
    // Determine unit price
    let unitPrice = prod.base_price;
    if (priceTiers[pid]) {
        const tiers = priceTiers[pid];
        let applicableTierPrice = null;
        tiers.forEach(tier => {
            if (qty >= tier.min_qty) {
                applicableTierPrice = tier.unit_price;
            }
        });
        if (applicableTierPrice !== null) {
            unitPrice = applicableTierPrice;
        }
    }
    
    // Apply discount
    if (prod.discount_percent > 0) {
        unitPrice = unitPrice * (1 - prod.discount_percent / 100);
    } else if (prod.discount_amount > 0) {
        unitPrice = Math.max(0, unitPrice - prod.discount_amount);
    }
    
    unitPrice += vmodifier;
    
    orderItems.push({
        id: 0, // Mark as new item
        product_id: pid,
        variant_id: vid,
        name: prod.name,
        variant_name: vname,
        qty: qty,
        weight: prod.weight,
        price: unitPrice
    });
    
    qtyInput.value = 1;
    productSelect.value = '';
    variantSelect.innerHTML = '<option value="0">Standard (No Variant)</option>';
    
    renderOrderItemsTable();
}

function removeOrderItem(index) {
    orderItems.splice(index, 1);
    renderOrderItemsTable();
}

function recalculateTotals() {
    const addressSelect = document.getElementById('address-select');
    const selectedAddrId = parseInt(addressSelect.value);
    const addr = addresses[selectedAddrId];
    
    let subtotal = 0;
    let totalWeight = 0;
    
    orderItems.forEach(item => {
        subtotal += item.price * item.qty;
        totalWeight += item.weight * item.qty;
    });
    
    // 1. Calculate Discounts
    let globalDiscountAmt = 0;
    globalDiscounts.forEach(d => {
        if (d.percent > 0) {
            globalDiscountAmt += (subtotal * (parseFloat(d.percent) / 100));
        } else if (d.amount > 0) {
            globalDiscountAmt += parseFloat(d.amount);
        }
    });
    
    let couponDiscountAmt = 0;
    if (coupon) {
        if (coupon.type === 'fixed') {
            couponDiscountAmt = parseFloat(coupon.value);
        } else if (coupon.type === 'percentage') {
            couponDiscountAmt = (subtotal * parseFloat(coupon.value)) / 100;
            if (coupon.max_discount && couponDiscountAmt > parseFloat(coupon.max_discount)) {
                couponDiscountAmt = parseFloat(coupon.max_discount);
            }
        }
    }
    
    const totalDiscounts = Math.min(subtotal, globalDiscountAmt + couponDiscountAmt);
    
    // 2. Calculate Shipping
    let shippingCharge = 0.00;
    if (addr && addr.shipping_type !== 'free' && addr.shipping_type !== 'manual') {
        shippingCharge = parseFloat(addr.base_delivery_charge) + (totalWeight * parseFloat(addr.per_unit_weight_charge));
    }
    
    // 3. Calculate Tax
    let taxPercent = addr ? parseFloat(addr.tax_percent) : 0;
    let taxable = Math.max(0, subtotal - totalDiscounts) + (taxOnShipping ? shippingCharge : 0);
    let taxAmt = (taxable * taxPercent) / 100;
    
    // 4. Calculate Grand Total
    let grandTotal = Math.max(0, subtotal - totalDiscounts) + shippingCharge + taxAmt;
    
    // Update summary UI elements
    document.getElementById('calc-subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('calc-discount').textContent = `-$${totalDiscounts.toFixed(2)}`;
    document.getElementById('calc-tax').textContent = `$${taxAmt.toFixed(2)}`;
    
    if (addr && addr.shipping_type === 'manual') {
        document.getElementById('calc-shipping').textContent = 'Set later by Admin';
    } else if (addr && addr.shipping_type === 'free') {
        document.getElementById('calc-shipping').textContent = 'Free Shipping';
    } else {
        document.getElementById('calc-shipping').textContent = `$${shippingCharge.toFixed(2)}`;
    }
    
    document.getElementById('calc-total').textContent = `$${grandTotal.toFixed(2)}`;
    
    // 5. Validate State Logistics Limits
    const alertBox = document.getElementById('limits-alert-box');
    const alertText = document.getElementById('limits-alert-text');
    const saveBtn = document.getElementById('save-order-btn');
    
    let limitsViolated = false;
    alertBox.style.display = 'none';
    saveBtn.disabled = false;
    saveBtn.style.opacity = '1';
    saveBtn.style.cursor = 'pointer';
    
    if (addr) {
        const minLim = parseFloat(addr.min_order_amount);
        const maxLim = parseFloat(addr.max_order_amount);
        
        if (subtotal < minLim) {
            limitsViolated = true;
            alertText.textContent = `Order subtotal ($${subtotal.toFixed(2)}) is below the minimum order limit ($${minLim.toFixed(2)}) for state ${addr.location_name || 'selected zone'}.`;
        } else if (subtotal > maxLim) {
            limitsViolated = true;
            alertText.textContent = `Order subtotal ($${subtotal.toFixed(2)}) exceeds the maximum order limit ($${maxLim.toFixed(2)}) for state ${addr.location_name || 'selected zone'}.`;
        }
    }
    
    if (limitsViolated) {
        alertBox.style.display = 'block';
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.5';
        saveBtn.style.cursor = 'not-allowed';
    }
    
    // Serialize item state to hidden input
    document.getElementById('order-items-json-input').value = JSON.stringify(orderItems);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
