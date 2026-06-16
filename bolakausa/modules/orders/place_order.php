<?php
/**
 * Place Order Logic
 * Handles calculations and database insertion.
 */
restrict_to(['wholesale_user']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bolakausa/checkout');
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = (int)$_POST['address_id'];
$location_id = (int)$_POST['location_id'];
$payment_method = $_POST['payment_method'];

// Capture Payment Details (for Bank Transfer)
$payment_details = null;
if ($payment_method === 'Bank Transfer') {
    $details = [
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'transaction_id' => trim($_POST['transaction_id'] ?? ''),
        'transfer_date' => $_POST['transfer_date'] ?? ''
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

foreach ($_SESSION['cart'] as $pid => $qty) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if ($product) {
        // Find tier price
        $stmt = $pdo->prepare("SELECT unit_price FROM product_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
        $stmt->execute([$pid, $qty]);
        $tier = $stmt->fetch();

        $price = $tier ? $tier['unit_price'] : $product['base_price'];
        $item_total = $price * $qty;
        
        $subtotal += $item_total;
        $total_weight += ($product['weight'] * $qty);

        $order_items_to_save[] = [
            'product_id' => $pid,
            'qty' => $qty,
            'price' => $price
        ];
    }
}

// 3. Shipping & Tax Math
$shipping_charge = $location['base_delivery_charge'] + ($total_weight * $location['per_unit_weight_charge']);

// Get global settings for tax logic
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_on_shipping'");
$stmt->execute();
$tax_on_shipping = (bool)($stmt->fetch()['setting_value'] ?? 0);

$taxable_amount = $subtotal + ($tax_on_shipping ? $shipping_charge : 0);
$tax_amount = ($taxable_amount * $location['tax_percent']) / 100;

$grand_total = $subtotal + $shipping_charge + $tax_amount;

// 4. Wallet Payment Check
if ($payment_method === 'Wallet') {
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM wallet_transactions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = (float)($stmt->fetch()['balance'] ?? 0);

    if ($balance < $grand_total) {
        die("Insufficient wallet balance.");
    }
}

// 5. Database Transaction: Save Order
$pdo->beginTransaction();
try {
    // Get address string
    $stmt = $pdo->prepare("SELECT address_line, city FROM user_addresses WHERE id = ?");
    $stmt->execute([$address_id]);
    $addr = $stmt->fetch();
    $address_string = $addr['address_line'] . ", " . $addr['city'];

    // Insert Order
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, payment_method, payment_details, delivery_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending Payment')");
    $stmt->execute([$user_id, $grand_total, $tax_amount, $shipping_charge, $payment_method, $payment_details, $address_string]);
    $order_id = $pdo->lastInsertId();

    // Insert Order Items
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price_at_purchase) VALUES (?, ?, ?, ?)");
    foreach ($order_items_to_save as $item) {
        $stmt->execute([$order_id, $item['product_id'], $item['qty'], $item['price']]);
    }

    // Handle Wallet Deduction
    if ($payment_method === 'Wallet') {
        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
        $stmt->execute([$user_id, $grand_total, "Payment for Order #$order_id"]);
        
        // Update order status to Processing (since paid)
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Payment Verified' WHERE id = ?");
        $stmt->execute([$order_id]);
    }

    // Log History
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'Pending Payment', ?, 'Order Placed')");
    $stmt->execute([$order_id, $user_id]);

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
    
    // Clear Cart
    unset($_SESSION['cart']);
    
    header("Location: /bolakausa/orders?id=$order_id&success=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error placing order: " . $e->getMessage());
}
