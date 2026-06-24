<?php
/**
 * Place Order Logic
 * Handles calculations and database insertion.
 */
restrict_to(['wholesale_user', 'executive']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'checkout');
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = (int)$_POST['address_id'];
$location_id = (int)$_POST['location_id'];
$payment_method = $_POST['payment_method'];

// Capture Payment Details (for Bank Transfer and Stripe)
$payment_details = null;
if ($payment_method === 'Bank Transfer') {
    $details = [
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'transaction_id' => trim($_POST['transaction_id'] ?? ''),
        'transfer_date' => $_POST['transfer_date'] ?? ''
    ];
    $payment_details = json_encode($details);
} elseif ($payment_method === 'Stripe') {
    $cardholder = trim($_POST['stripe_cardholder'] ?? '');
    $card_number = str_replace(' ', '', $_POST['stripe_card_number'] ?? '');
    $expiry = trim($_POST['stripe_expiry'] ?? '');
    $cvc = trim($_POST['stripe_cvc'] ?? '');

    if (empty($cardholder) || empty($card_number) || empty($expiry) || empty($cvc)) {
        die("All card fields are required for credit card processing.");
    }

    if ($card_number !== '4242424242424242') {
        die("Transaction Declined: Only standard Stripe test card (4242 4242 4242 4242) is accepted in demo mode.");
    }

    $details = [
        'charge_id' => 'ch_mock_stripe_' . bin2hex(random_bytes(8)),
        'status' => 'succeeded',
        'cardholder' => $cardholder,
        'last4' => substr($card_number, -4)
    ];
    $payment_details = json_encode($details);
}

if (empty($_SESSION['cart'])) {
    die("Cart is empty.");
}

// 1. Fetch Location Data for Calculations
$stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
$stmt->execute([$location_id]);
$location = $stmt->fetch();

if (!$location) die("Invalid location selected.");

// 2. Calculate Subtotal and Total Weight
$subtotal = 0;
$total_weight = 0;
$order_items_to_save = [];

foreach ($_SESSION['cart'] as $item_key => $qty) {
    $parts = explode('_', $item_key);
    $pid = (int)$parts[0];
    $variant_id = (int)($parts[1] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if ($product) {
        // Find tier price
        $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
        $stmt->execute([$pid, $qty]);
        $tier = $stmt->fetch();

        $discount = get_product_discount($pdo, $pid, $user_id);
        $base_unit_price = $tier ? $tier['unit_price'] : $product['base_price'];
        $price = calculate_discounted_price($base_unit_price, $discount);
        
        if ($variant_id > 0) {
            $vstmt = $pdo->prepare("SELECT price_modifier FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
            $vstmt->execute([$variant_id, $pid]);
            $mod = $vstmt->fetchColumn();
            if ($mod !== false) {
                $price += (float)$mod;
            }
        }
        
        $item_total = $price * $qty;
        
        $subtotal += $item_total;
        $total_weight += ($product['weight'] * $qty);

        $order_items_to_save[] = [
            'product_id' => $pid,
            'variant_id' => $variant_id,
            'qty' => $qty,
            'price' => $price
        ];
    }
}

// 3. Shipping, Coupon & Tax Math
$shipping_charge = $location['base_delivery_charge'] + ($total_weight * $location['per_unit_weight_charge']);

// Calculate Coupon Discount on Server Side
$discount_amount = 0;
$coupon_id = null;
if (!empty($_SESSION['applied_coupon'])) {
    $applied = $_SESSION['applied_coupon'];
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? AND code = ? AND is_active = 1");
    $stmt->execute([$applied['id'], $applied['code']]);
    $coupon = $stmt->fetch();
    
    if ($coupon) {
        $now = date('Y-m-d H:i:s');
        $valid = true;
        if ($coupon['start_date'] && $coupon['start_date'] > $now) $valid = false;
        if ($coupon['end_date'] && $coupon['end_date'] < $now) $valid = false;
        if ($coupon['expiry_date'] && $coupon['expiry_date'] < $now) $valid = false;
        if ($coupon['used_count'] >= $coupon['usage_limit']) $valid = false;
        if ($subtotal < $coupon['min_spend']) $valid = false;
        
        if (!is_wholesaler_targeted($pdo, $user_id, $coupon['target_wholesalers'])) $valid = false;
        
        if ($valid) {
            $coupon_id = $coupon['id'];
            if ($coupon['type'] === 'fixed') {
                $discount_amount = (float)$coupon['value'];
            } elseif ($coupon['type'] === 'percentage') {
                $discount_amount = ($subtotal * (float)$coupon['value']) / 100;
                if ($coupon['max_discount'] && $discount_amount > $coupon['max_discount']) {
                    $discount_amount = (float)$coupon['max_discount'];
                }
            }
            if ($discount_amount > $subtotal) $discount_amount = $subtotal;
        }
    }
}

$global_discount = calculate_global_discount_amount($pdo, $subtotal, $user_id);

// Get global settings for tax logic
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_on_shipping'");
$stmt->execute();
$tax_on_shipping = (bool)($stmt->fetch()['setting_value'] ?? 0);

$taxable_amount = ($subtotal - $discount_amount - $global_discount) + ($tax_on_shipping ? $shipping_charge : 0);
if ($taxable_amount < 0) $taxable_amount = 0;
$tax_amount = ($taxable_amount * $location['tax_percent']) / 100;

$grand_total = ($subtotal - $discount_amount - $global_discount) + $shipping_charge + $tax_amount;

// 4. Fetch Wallet Balance & Check Wallet Payment
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = (float)($stmt->fetch()['balance'] ?? 0);

$overdraft_limit = (float)get_setting($pdo, 'wallet_overdraft_limit', '1000.00');

if ($payment_method === 'Wallet') {
    if ($balance < $grand_total) {
        die("Insufficient wallet balance.");
    }
} elseif (in_array($payment_method, ['COD', 'Pay Later'])) {
    if (($balance - $grand_total) < -$overdraft_limit) {
        die("Order placement blocked. This order exceeds your wallet's maximum overdraft limit of $" . number_format($overdraft_limit, 2) . " (Current Balance: $" . number_format($balance, 2) . ", Order Total: $" . number_format($grand_total, 2) . ").");
    }
}

// 5. Final Stock Validation Before Order
foreach ($order_items_to_save as $item) {
    // Check main product stock first
    $stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
    $stmt->execute([$item['product_id']]);
    $main_stock = (int)$stmt->fetchColumn();
    if ($item['qty'] > $main_stock) {
        die("Insufficient overall stock for product ID {$item['product_id']}. Available: $main_stock, requested: {$item['qty']}.");
    }

    // Check variant stock if applicable
    if ($item['variant_id'] > 0) {
        $stmt = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ? AND product_id = ? AND is_deleted = 0");
        $stmt->execute([$item['variant_id'], $item['product_id']]);
        $v_stock = $stmt->fetchColumn();
        if ($v_stock === false) {
            die("Invalid variant selected.");
        }
        $v_stock = (int)$v_stock;
        if ($item['qty'] > $v_stock) {
            die("Insufficient stock for the selected variant. Available: $v_stock, requested: {$item['qty']}.");
        }
    }
}

// 6. Database Transaction: Save Order
$pdo->beginTransaction();
try {
    // Get address string with location state and tax percentage
    $stmt = $pdo->prepare("SELECT a.address_line, a.city, l.name as location_name, l.tax_percent FROM user_addresses a LEFT JOIN locations l ON a.location_id = l.id WHERE a.id = ?");
    $stmt->execute([$address_id]);
    $addr = $stmt->fetch();
    $address_string = $addr['address_line'] . ", " . $addr['city'];
    if (!empty($addr['location_name'])) {
        $address_string .= " (" . $addr['location_name'] . ", Tax: " . $addr['tax_percent'] . "%)";
    }

    // Insert Order
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, discount_amount, coupon_id, payment_method, payment_details, delivery_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Payment')");
    $stmt->execute([$user_id, $grand_total, $tax_amount, $shipping_charge, $discount_amount + $global_discount, $coupon_id, $payment_method, $payment_details, $address_string]);
    $order_id = $pdo->lastInsertId();

    // Insert Order Items
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, qty, price_at_purchase) VALUES (?, ?, ?, ?, ?)");
    foreach ($order_items_to_save as $item) {
        $stmt->execute([$order_id, $item['product_id'], $item['variant_id'] ?: null, $item['qty'], $item['price']]);
    }

    // Increment Coupon used count
    if ($coupon_id) {
        $stmt_inc = $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
        $stmt_inc->execute([$coupon_id]);
    }

    // Debit wallet immediately for ALL payment methods (automated B2B credit ledger)
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt->execute([$user_id, $grand_total, "Payment for Order #$order_id"]);

    // Deduct catalog stock immediately to prevent double selling
    deduct_order_stock($pdo, $order_id);

    // Initial Status setting
    if (in_array($payment_method, ['Wallet', 'COD', 'Pay Later'])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Payment Verified' WHERE id = ?");
        $stmt->execute([$order_id]);
    } else {
        // Stripe or Bank Transfer
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Pending Payment' WHERE id = ?");
        $stmt->execute([$order_id]);

        $payment_amount = (float)($_POST['payment_amount'] ?? 0);
        $shortfall = max(0, $grand_total - $balance);
        if ($payment_amount < $shortfall) {
            die("Invalid payment amount. Minimum required: $" . number_format($shortfall, 2));
        }

        // Extract transaction ID from JSON
        $details = json_decode($payment_details, true);
        $txn_ref = '';
        if ($payment_method === 'Bank Transfer') {
            $txn_ref = $details['transaction_id'] ?? '';
        } elseif ($payment_method === 'Stripe') {
            $txn_ref = $details['charge_id'] ?? '';
        }
        
        $t_stmt = $pdo->prepare("INSERT INTO wallet_topups (user_id, amount, payment_method, transaction_id, status, order_id) VALUES (?, ?, ?, ?, 'pending', ?)");
        $t_stmt->execute([$user_id, $payment_amount, $payment_method, $txn_ref, $order_id]);
    }

    // Log History
    $status_for_history = in_array($payment_method, ['Wallet', 'COD', 'Pay Later']) ? 'Payment Verified' : 'Pending Payment';
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, 'Order Placed')");
    $stmt->execute([$order_id, $status_for_history, $user_id]);

    $pdo->commit();
    
    // Trigger Email Notification
    require_once 'includes/mailer.php';
    $uStmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $user_data = $uStmt->fetch();
    if ($user_data) {
        $email_body = "<h3>Order Received!</h3>
        <p>Hello " . e($user_data['full_name']) . ",</p>
        <p>Your order <strong>#$order_id</strong> has been successfully placed.</p>
        <p>Total: <strong>$" . number_format($grand_total, 2) . "</strong></p>
        <p>You can track your order status in your account dashboard.</p>";
        send_system_email($pdo, $user_data['email'], "Order Confirmation #$order_id", $email_body);
    }
    
    // Clear Cart & Coupon
    unset($_SESSION['cart']);
    unset($_SESSION['applied_coupon']);
    
    header("Location: " . BASE_URL . "orders?id=$order_id&success=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error placing order: " . $e->getMessage());
}
