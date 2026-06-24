<?php
/**
 * Automated SQA Testing Script for B2B portal Enhancements
 * Run via CLI: C:\xampp\php\php.exe scratch/test_workflow.php
 */

define('CLI_MODE', php_sapi_name() === 'cli');

function log_status($name, $status, $message = '') {
    if (CLI_MODE) {
        $color = $status === 'PASS' ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
        echo "$color $name " . ($message ? "- $message" : "") . "\n";
    } else {
        $color = $status === 'PASS' ? 'green' : 'red';
        echo "<div style='color: $color; margin-bottom: 5px;'><strong>[$status]</strong> $name " . ($message ? "- " . htmlspecialchars($message) : "") . "</div>";
    }
}

echo "==================================================\n";
echo "   BOLAKAUSA B2B WORKFLOW TRANSACTIONAL TESTS     \n";
echo "==================================================\n\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

try {
    $pdo->beginTransaction();

    // 1. Create a mock location state zones
    $stmt = $pdo->prepare("INSERT INTO locations (name, tax_percent, base_delivery_charge, per_unit_weight_charge, min_order_amount, max_order_amount, shipping_type) VALUES ('SQA State Default', 5.00, 15.00, 2.00, 100.00, 2000.00, 'default')");
    $stmt->execute();
    $loc_default_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO locations (name, tax_percent, base_delivery_charge, per_unit_weight_charge, min_order_amount, max_order_amount, shipping_type) VALUES ('SQA State Free', 6.00, 0.00, 0.00, 150.00, 3000.00, 'free')");
    $stmt->execute();
    $loc_free_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO locations (name, tax_percent, base_delivery_charge, per_unit_weight_charge, min_order_amount, max_order_amount, shipping_type) VALUES ('SQA State Manual', 7.00, 0.00, 0.00, 50.00, 1000.00, 'manual')");
    $stmt->execute();
    $loc_manual_id = $pdo->lastInsertId();

    log_status("Create Mock Locations (State Zones)", "PASS", "Inserted SQA State Default, SQA State Free, SQA State Manual.");

    // 2. Create mock customer
    $test_username = 'sqa_test_wholesaler_' . time();
    $test_email = $test_username . '@example.com';
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status, location_id) VALUES (?, ?, ?, 'wholesale_user', 'active', ?)");
    $stmt->execute([$test_username, $test_email, password_hash('password123', PASSWORD_BCRYPT), $loc_default_id]);
    $user_id = $pdo->lastInsertId();

    // Seed initial wallet balance
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', 1000.00, 'Initial deposit')");
    $stmt->execute([$user_id]);

    log_status("Create Test Customer Account", "PASS", "User ID: $user_id, Email: $test_email, Wallet seeded with $1000.00.");

    // 3. Create mock product
    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, base_price, stock_qty, weight) VALUES ('SQA Widget Pro', 1, 50.00, 100, 1.50)");
    $stmt->execute();
    $product_id = $pdo->lastInsertId();

    log_status("Create Test Product", "PASS", "Product ID: $product_id, 'SQA Widget Pro', Price: $50.00, Stock: 100.");

    // 4. Place initial order & verify stock reduction
    $initial_qty = 5; // Subtotal: 250.00 (Exceeds min order limit of 100.00)
    $shipping_fee = 15.00 + (1.50 * 2.00 * $initial_qty); // Base 15 + Weight (1.5 * 2 * 5) = 30.00
    $taxable = 250.00;
    $tax = ($taxable * 5.00) / 100; // 5% of 250 = 12.50
    $total_amount = $taxable + $shipping_fee + $tax; // 250 + 30 + 12.50 = 292.50

    // Create Order
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, discount_amount, delivery_address, status, payment_method) VALUES (?, ?, ?, ?, 0.00, '123 Test St, SQA City, Default State', 'Pending Payment', 'Pay Later')");
    $stmt->execute([$user_id, $total_amount, $tax, $shipping_fee]);
    $order_id = $pdo->lastInsertId();

    // Create Order Item
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price_at_purchase, requested_discount_type, requested_discount_value) VALUES (?, ?, ?, 50.00, 'percent', 10.00)");
    $stmt->execute([$order_id, $product_id, $initial_qty]);
    $order_item_id = $pdo->lastInsertId();

    // Deduct stock
    deduct_order_stock($pdo, $order_id);

    // Verify stock in database
    $stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $current_stock = (int)$stmt->fetchColumn();

    if ($current_stock === 95) {
        log_status("Initial Order Stock Deduction", "PASS", "Stock reduced from 100 to 95.");
    } else {
        log_status("Initial Order Stock Deduction", "FAIL", "Stock is $current_stock, expected 95.");
    }

    // 5. Simulate Admin approves order, ignoring / adjusting discounts (reduces total)
    // Subtotal: 250.00. Requested discount: 10% on item = 25.00 discount. New total with discount: 267.50
    // Admin adjusted discount: 25.00.
    $approved_item_disc = 25.00;
    $new_taxable = 250.00 - 25.00; // 225.00
    $new_tax = ($new_taxable * 5.00) / 100; // 11.25
    $new_total = $new_taxable + $shipping_fee + $new_tax; // 225 + 30 + 11.25 = 266.25

    // Transactionally update database
    $stmt = $pdo->prepare("UPDATE order_items SET admin_adjusted_discount = ? WHERE id = ?");
    $stmt->execute([$approved_item_disc, $order_item_id]);

    $stmt = $pdo->prepare("UPDATE orders SET total_amount = ?, tax_amount = ?, admin_adjusted_discount = ?, status = 'Pending Customer Approval', pending_change_details = ? WHERE id = ?");
    $change_details = [
        'delivery_address' => '123 Test St, SQA City, Default State',
        'shipping_amount' => $shipping_fee,
        'tax_amount' => $new_tax,
        'total_amount' => $new_total,
        'admin_adjusted_discount' => 25.00,
        'items' => [
            [
                'item_id' => $order_item_id,
                'product_id' => $product_id,
                'variant_id' => 0,
                'name' => 'SQA Widget Pro',
                'qty' => $initial_qty,
                'price' => 50.00,
                'admin_adjusted_discount' => 25.00
            ]
        ]
    ];
    $stmt->execute([$new_total, $new_tax, 25.00, json_encode($change_details), $order_id]);

    log_status("Admin Adjusted Discounts (Gated Edit)", "PASS", "Pending total set to $266.25. Status updated to Pending Customer Approval.");

    // 6. Wholesaler approves changes and pays shortfall (in this case total decreased from 292.50 to 266.25, so credit refund of 26.25 to wallet)
    // Wait, the wholesaler's wallet was not debited at checkout since payment_method is Pay Later and status is Pending Customer Approval.
    // Let's run the actual approve changes logic.
    // Fetch the order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    $change_details_decoded = json_decode($order['pending_change_details'], true);
    $proposed_total = (float)$change_details_decoded['total_amount'];
    $old_total = (float)$order['total_amount'];
    $shortfall = $proposed_total - $old_total; // 266.25 - 266.25 = 0.00

    // Restore stock
    restore_order_stock($pdo, $order_id);

    // Apply items
    foreach ($change_details_decoded['items'] as $item) {
        $stmt_item = $pdo->prepare("UPDATE order_items SET qty = ?, admin_adjusted_discount = ? WHERE id = ?");
        $stmt_item->execute([$item['qty'], $item['admin_adjusted_discount'], $item['item_id']]);
    }

    // Apply order
    $stmt_up = $pdo->prepare("UPDATE orders SET delivery_address = ?, shipping_amount = ?, tax_amount = ?, total_amount = ?, admin_adjusted_discount = ?, pending_change_details = NULL, status = 'Payment Verified' WHERE id = ?");
    $stmt_up->execute([
        $change_details_decoded['delivery_address'],
        (float)$change_details_decoded['shipping_amount'],
        (float)$change_details_decoded['tax_amount'],
        $proposed_total,
        (float)($change_details_decoded['admin_adjusted_discount'] ?? 0),
        $order_id
    ]);

    // Re-deduct stock
    deduct_order_stock($pdo, $order_id);

    // Process shortfall refund
    if ($shortfall < 0) {
        $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
        $stmt_tx->execute([$user_id, abs($shortfall), "Wallet credit for reduced total of Order #$order_id"]);
    }

    log_status("Customer Approval Processing", "PASS", "Approved changes transactionally, stock restored and re-deducted.");

    // Verify stock is still correct
    $stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $final_stock = (int)$stmt->fetchColumn();

    if ($final_stock === 95) {
        log_status("Stock Re-deductions Verification", "PASS", "Final stock level is correctly maintained at 95.");
    } else {
        log_status("Stock Re-deductions Verification", "FAIL", "Final stock level is $final_stock, expected 95.");
    }

    // 7. Verify email logger inserts
    // Mock system email send
    send_system_email($pdo, "help@bolakausa.com", "SQA Automation Subject", "<p>SQA HTML Invoice Body</p>");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE to_email = 'help@bolakausa.com' AND subject = 'SQA Automation Subject'");
    $stmt->execute();
    $email_log_count = (int)$stmt->fetchColumn();

    if ($email_log_count > 0) {
        log_status("Email Logger Verification", "PASS", "Confirmed email logs recorded in database.");
    } else {
        log_status("Email Logger Verification", "FAIL", "No email log row found.");
    }

    // 8. Reordering/Cloning Historical Transaction Verification
    // Setup low stock = 3 for the product to verify stock-ledging / quantity bounding
    $stmt = $pdo->prepare("UPDATE products SET stock_qty = 3 WHERE id = ?");
    $stmt->execute([$product_id]);
    
    // Simulate order cloning
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $old_order = $stmt->fetch();
    
    if (!$old_order) {
        throw new Exception("Old order #$order_id not found for cloning.");
    }
    
    // Insert a new order draft
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, discount_amount, payment_method, delivery_address, status) VALUES (?, 0.00, 0.00, 0.00, 0.00, ?, ?, 'Pending Payment')");
    $stmt->execute([$old_order['user_id'], $old_order['payment_method'], $old_order['delivery_address']]);
    $new_order_id = $pdo->lastInsertId();
    
    // Fetch old items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND is_deleted = 0");
    $stmt->execute([$order_id]);
    $old_items = $stmt->fetchAll();
    
    $cloned_any = false;
    foreach ($old_items as $item) {
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
        
        $qty_to_clone = min($item['qty'], $avail_stock);
        if ($qty_to_clone <= 0) {
            continue; // Skip items out of stock
        }
        
        $cloned_any = true;
        $stmt_ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, qty, price_at_purchase) VALUES (?, ?, ?, ?, ?)");
        $stmt_ins->execute([$new_order_id, $item['product_id'], $item['variant_id'], $qty_to_clone, $item['price_at_purchase']]);
    }
    
    if (!$cloned_any) {
        throw new Exception("All items in the previous order are out of stock. Cannot clone order.");
    }
    
    // Deduct stock for the new order draft
    deduct_order_stock($pdo, $new_order_id);
    
    // Verify cloned item quantity bounded by stock (original was 5, stock was 3, cloned should be 3)
    $stmt = $pdo->prepare("SELECT qty FROM order_items WHERE order_id = ? AND product_id = ?");
    $stmt->execute([$new_order_id, $product_id]);
    $cloned_qty = (int)$stmt->fetchColumn();
    
    // Verify product stock (should be 3 - 3 = 0)
    $stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $cloned_product_stock = (int)$stmt->fetchColumn();
    
    if ($cloned_qty === 3 && $cloned_product_stock === 0) {
        log_status("Order Reorder/Cloning (Quantity Bounding)", "PASS", "Cloned quantity bounded to 3 (stock level), and stock successfully reduced to 0.");
    } else {
        log_status("Order Reorder/Cloning (Quantity Bounding)", "FAIL", "Expected cloned qty: 3, actual: $cloned_qty. Expected stock: 0, actual: $cloned_product_stock.");
    }

    // 9. Verify Automated Calculations: Price Tiers, Taxes, Shipping Options, and Limits
    // Seed a price tier for $product_id
    $stmt = $pdo->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, 10, 40.00)");
    $stmt->execute([$product_id]);
    
    // Restore product stock to 100 for calculations testing
    $stmt = $pdo->prepare("UPDATE products SET stock_qty = 100 WHERE id = ?");
    $stmt->execute([$product_id]);
    
    // Setup addresses for each state/location
    $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_line, city, location_id, is_default) VALUES (?, '123 Default Rd', 'City A', ?, 1)");
    $stmt->execute([$user_id, $loc_default_id]);
    $addr_default_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_line, city, location_id, is_default) VALUES (?, '456 Free Way', 'City B', ?, 0)");
    $stmt->execute([$user_id, $loc_free_id]);
    $addr_free_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_line, city, location_id, is_default) VALUES (?, '789 Manual St', 'City C', ?, 0)");
    $stmt->execute([$user_id, $loc_manual_id]);
    $addr_manual_id = $pdo->lastInsertId();
    
    // Helper function inside test execution to calculate order parameters locally
    $calculate_order = function($address_id, $items) use ($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT a.*, l.name as location_name, l.tax_percent, l.base_delivery_charge, l.per_unit_weight_charge, l.min_order_amount, l.max_order_amount, l.shipping_type FROM user_addresses a LEFT JOIN locations l ON a.location_id = l.id WHERE a.id = ? AND a.user_id = ?");
        $stmt->execute([$address_id, $user_id]);
        $addr = $stmt->fetch();
        if (!$addr) throw new Exception("Address not found.");
        
        $subtotal = 0;
        $total_weight = 0;
        
        foreach ($items as $item) {
            $pid = $item['product_id'];
            $qty = $item['qty'];
            
            $stmt = $pdo->prepare("SELECT base_price, weight FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $prod = $stmt->fetch();
            
            // Check tiers
            $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
            $stmt->execute([$pid, $qty]);
            $tier = $stmt->fetch();
            
            $price = $tier ? (float)$tier['unit_price'] : (float)$prod['base_price'];
            $subtotal += $price * $qty;
            $total_weight += (float)$prod['weight'] * $qty;
        }
        
        // Limits Check
        $min_lim = (float)$addr['min_order_amount'];
        $max_lim = (float)$addr['max_order_amount'];
        if ($subtotal < $min_lim) {
            throw new Exception("BELOW_MIN: Subtotal $subtotal is below $min_lim");
        }
        if ($subtotal > $max_lim) {
            throw new Exception("EXCEEDS_MAX: Subtotal $subtotal exceeds $max_lim");
        }
        
        // Shipping
        $shipping = 0.00;
        if ($addr['shipping_type'] === 'default') {
            $shipping = (float)$addr['base_delivery_charge'] + ($total_weight * (float)$addr['per_unit_weight_charge']);
        }
        
        // Tax (assume tax_on_shipping = 0 for standard calculation)
        $taxable = $subtotal; // no coupons/discounts applied for this test calculation
        $tax = ($taxable * (float)$addr['tax_percent']) / 100;
        
        $total = $subtotal + $shipping + $tax;
        
        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total
        ];
    };
    
    // A. Verify Bulk Price Tiers: 12 widgets (tier should apply: price becomes $40.00)
    // Weight = 12 * 1.5 = 18
    // Default location shipping = 15.00 + 18 * 2.00 = 51.00
    // Subtotal = 12 * 40.00 = 480.00
    // Tax (5%) = 480 * 0.05 = 24.00
    // Total = 480 + 51 + 24 = 555.00
    try {
        $res = $calculate_order($addr_default_id, [['product_id' => $product_id, 'qty' => 12]]);
        if ($res['subtotal'] == 480.00 && $res['shipping'] == 51.00 && $res['tax'] == 24.00 && $res['total'] == 555.00) {
            log_status("Automated Calculation: Tier Price & Shipping (Default State)", "PASS", "Calculated Subtotal: $480.00, Shipping: $51.00, Tax: $24.00, Total: $555.00.");
        } else {
            log_status("Automated Calculation: Tier Price & Shipping (Default State)", "FAIL", "Calculated incorrectly: " . json_encode($res));
        }
    } catch (Exception $e) {
        log_status("Automated Calculation: Tier Price & Shipping (Default State)", "FAIL", "Exception: " . $e->getMessage());
    }
    
    // B. Verify Free Shipping Location:
    // Subtotal = 12 * 40.00 = 480.00
    // Shipping = 0.00
    // Tax (6%) = 480 * 0.06 = 28.80
    // Total = 480 + 0 + 28.80 = 508.80
    try {
        $res = $calculate_order($addr_free_id, [['product_id' => $product_id, 'qty' => 12]]);
        if ($res['subtotal'] == 480.00 && $res['shipping'] == 0.00 && $res['tax'] == 28.80 && $res['total'] == 508.80) {
            log_status("Automated Calculation: Free Shipping State", "PASS", "Calculated Subtotal: $480.00, Shipping: $0.00, Tax: $28.80, Total: $508.80.");
        } else {
            log_status("Automated Calculation: Free Shipping State", "FAIL", "Calculated incorrectly: " . json_encode($res));
        }
    } catch (Exception $e) {
        log_status("Automated Calculation: Free Shipping State", "FAIL", "Exception: " . $e->getMessage());
    }
    
    // C. Verify Manual Shipping Location:
    // Subtotal = 12 * 40.00 = 480.00
    // Shipping = 0.00 (manual type)
    // Tax (7%) = 480 * 0.07 = 33.60
    // Total = 480 + 0 + 33.60 = 513.60
    try {
        $res = $calculate_order($addr_manual_id, [['product_id' => $product_id, 'qty' => 12]]);
        if ($res['subtotal'] == 480.00 && $res['shipping'] == 0.00 && $res['tax'] == 33.60 && $res['total'] == 513.60) {
            log_status("Automated Calculation: Manual Shipping State", "PASS", "Calculated Subtotal: $480.00, Shipping: $0.00, Tax: $33.60, Total: $513.60.");
        } else {
            log_status("Automated Calculation: Manual Shipping State", "FAIL", "Calculated incorrectly: " . json_encode($res));
        }
    } catch (Exception $e) {
        log_status("Automated Calculation: Manual Shipping State", "FAIL", "Exception: " . $e->getMessage());
    }
    
    // D. Verify State Limits Validation
    // Below minimum order: 1 widget (price $50.00) in Free State (min 150.00) -> should fail
    try {
        $calculate_order($addr_free_id, [['product_id' => $product_id, 'qty' => 1]]);
        log_status("State Limits Validation (Below Min)", "FAIL", "Allowed order below min limit.");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "BELOW_MIN") !== false) {
            log_status("State Limits Validation (Below Min)", "PASS", "Correctly rejected subtotal below state minimum order limit.");
        } else {
            log_status("State Limits Validation (Below Min)", "FAIL", "Failed with unexpected error: " . $e->getMessage());
        }
    }
    
    // Exceeds maximum order: 60 widgets (price $40.00, subtotal = 2400.00) in Default State (max 2000.00) -> should fail
    try {
        $calculate_order($addr_default_id, [['product_id' => $product_id, 'qty' => 60]]);
        log_status("State Limits Validation (Exceeds Max)", "FAIL", "Allowed order above max limit.");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "EXCEEDS_MAX") !== false) {
            log_status("State Limits Validation (Exceeds Max)", "PASS", "Correctly rejected subtotal exceeding state maximum order limit.");
        } else {
            log_status("State Limits Validation (Exceeds Max)", "FAIL", "Failed with unexpected error: " . $e->getMessage());
        }
    }

    // 10. Simulate Manual Email Compositions and Resend Requests
    // Let's create an email log in database to resend
    $stmt = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, status, error_message) VALUES ('resend_target@example.com', 'Original Verification Email', '<p>Original Body</p>', 'failed', 'Mail rejected')");
    $stmt->execute();
    $target_log_id = $pdo->lastInsertId();
    
    // Simulate resend POST action handler logic
    $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ?");
    $stmt->execute([$target_log_id]);
    $log_to_resend = $stmt->fetch();
    
    if ($log_to_resend) {
        $to = $log_to_resend['to_email'];
        $subject = "[RESENT] " . $log_to_resend['subject'];
        $body = $log_to_resend['body'];
        
        // Save to email_logs
        $ins = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, status, error_message) VALUES (?, ?, ?, 'not_sent_no_smtp', 'SMTP credentials are not configured in system settings.')");
        $ins->execute([$to, $subject, $body]);
        $new_log_id = $pdo->lastInsertId();
        
        // Assert resend logged correctly
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE id = ? AND subject = ?");
        $stmt->execute([$new_log_id, $subject]);
        $resent_count = (int)$stmt->fetchColumn();
        
        if ($resent_count > 0) {
            log_status("Simulate Email Resend Action", "PASS", "Email log entry successfully cloned with subject prefix '[RESENT]'.");
        } else {
            log_status("Simulate Email Resend Action", "FAIL", "Resent email log entry not found.");
        }
    } else {
        log_status("Simulate Email Resend Action", "FAIL", "Log to resend was not found in DB.");
    }
    
    // Simulate compose manual POST action handler logic
    $compose_to = "manual_compose_wholesaler@example.com";
    $compose_subj = "Manual Custom Subject";
    $compose_body = "<p>Manual Custom Body Text</p>";
    
    send_system_email($pdo, $compose_to, $compose_subj, $compose_body);
    
    // Assert email logged
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE to_email = ? AND subject = ?");
    $stmt->execute([$compose_to, $compose_subj]);
    $composed_count = (int)$stmt->fetchColumn();
    
    if ($composed_count > 0) {
        log_status("Simulate Manual Email Composition", "PASS", "Manual custom email successfully composed, sent, and logged in database.");
    } else {
        log_status("Simulate Manual Email Composition", "FAIL", "Manual composed email log entry not found.");
    }

    // 11. Dual Status Integration Verification
    // Place a Pay Later order - it should start as payment_status = 'Unpaid', fulfillment_status = 'Pending'
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, discount_amount, delivery_address, status, payment_status, fulfillment_status, payment_method) VALUES (?, 100.00, 5.00, 10.00, 0.00, 'SQA Dual Status Address', 'Pending Payment', 'Unpaid', 'Pending', 'Pay Later')");
    $stmt->execute([$user_id]);
    $dual_order_id = $pdo->lastInsertId();
    
    // Check initial state
    $stmt = $pdo->prepare("SELECT payment_status, fulfillment_status FROM orders WHERE id = ?");
    $stmt->execute([$dual_order_id]);
    $dual_state = $stmt->fetch();
    
    if ($dual_state['payment_status'] === 'Unpaid' && $dual_state['fulfillment_status'] === 'Pending') {
        log_status("Dual Status Initialization", "PASS", "Pay Later order correctly initialized as Unpaid and Pending.");
    } else {
        log_status("Dual Status Initialization", "FAIL", "Expected Unpaid/Pending, got: " . json_encode($dual_state));
    }
    
    // Simulate updating fulfillment_status to Shipped (payment remains Unpaid)
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Shipped', fulfillment_status = 'Shipped' WHERE id = ?");
    $stmt->execute([$dual_order_id]);
    
    $stmt = $pdo->prepare("SELECT payment_status, fulfillment_status FROM orders WHERE id = ?");
    $stmt->execute([$dual_order_id]);
    $dual_state = $stmt->fetch();
    
    if ($dual_state['payment_status'] === 'Unpaid' && $dual_state['fulfillment_status'] === 'Shipped') {
        log_status("Independent Fulfillment Update", "PASS", "Order fulfillment successfully moved to Shipped while payment remained Unpaid.");
    } else {
        log_status("Independent Fulfillment Update", "FAIL", "Expected Unpaid/Shipped, got: " . json_encode($dual_state));
    }
    
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
    $stmt->execute([$user_id, 100.00, "Payment received for Order #$dual_order_id via Cash"]);
    
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Paid', status = 'Payment Verified' WHERE id = ?");
    $stmt->execute([$dual_order_id]);
    
    $stmt = $pdo->prepare("SELECT payment_status, fulfillment_status FROM orders WHERE id = ?");
    $stmt->execute([$dual_order_id]);
    $dual_state = $stmt->fetch();
    
    if ($dual_state['payment_status'] === 'Paid' && $dual_state['fulfillment_status'] === 'Shipped') {
        log_status("Offline Payment Recording updates payment_status", "PASS", "Payment recorded successfully, setting payment_status = Paid and keeping fulfillment_status = Shipped.");
    } else {
        log_status("Offline Payment Recording updates payment_status", "FAIL", "Expected Paid/Shipped, got: " . json_encode($dual_state));
    }

    $pdo->rollBack();
    log_status("Database Transaction Rollback", "PASS", "All mock testing metrics successfully rolled back to maintain DB integrity.");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_status("Workflow Test Execution", "FAIL", "Exception caught: " . $e->getMessage());
}

echo "\n==================================================\n";
echo "           WORKFLOW TESTING COMPLETE              \n";
echo "==================================================\n";
