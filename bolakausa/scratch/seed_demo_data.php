<?php
/**
 * Seeding Script for Premium B2B Wholesale Demo Data
 */
require_once 'config/database.php';

try {
    // 1. Clean existing transactional/catalog data (DDL causes implicit commit in MySQL, run before starting transaction)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE order_items");
    $pdo->exec("TRUNCATE TABLE order_status_history");
    $pdo->exec("TRUNCATE TABLE orders");
    $pdo->exec("TRUNCATE TABLE product_price_tiers");
    $pdo->exec("TRUNCATE TABLE product_images");
    $pdo->exec("TRUNCATE TABLE product_variants");
    $pdo->exec("TRUNCATE TABLE products");
    $pdo->exec("TRUNCATE TABLE categories");
    $pdo->exec("TRUNCATE TABLE user_addresses");
    $pdo->exec("TRUNCATE TABLE chats");
    $pdo->exec("TRUNCATE TABLE wallet_transactions");
    $pdo->exec("TRUNCATE TABLE coupons");
    $pdo->exec("TRUNCATE TABLE discounts");
    $pdo->exec("TRUNCATE TABLE promotions");
    $pdo->exec("TRUNCATE TABLE inventory_lots");
    $pdo->exec("TRUNCATE TABLE order_item_picks");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Cleared old catalog, addresses, orders, wallet transactions, chat records, coupons, discounts, and campaigns.\n";

    // Start transaction for DML inserts
    $pdo->beginTransaction();

    // 2. Seed Categories
    $categories = [
        ['Organic Grains & Flours', 'Bulk organic grains, rice varieties, and stone-ground baking flours.'],
        ['Dairy & Plant Alternatives', 'Premium wholesale cheeses, butter, soy, almond, and oat milk containers.'],
        ['Fresh Produce (Cases)', 'High-demand restaurant produce packaged in commercial volumes.'],
        ['Beverages & Syrups', 'Case-packed juices, organic sodas, and coffee bar syrups.']
    ];

    $cat_ids = [];
    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    foreach ($categories as $c) {
        $stmt->execute($c);
        $cat_ids[$c[0]] = $pdo->lastInsertId();
    }
    echo "Seeded " . count($categories) . " product categories.\n";

    // 3. Seed Products
    // [category_name, name, description, base_price, stock_qty, min_order_qty, max_order_qty, weight]
    $products = [
        ['Organic Grains & Flours', 'Organic Jasmine Rice (50 lb)', 'Premium long-grain white jasmine rice, direct import sourcing.', 45.00, 120, 5, 100, 22.68],
        ['Organic Grains & Flours', 'Whole Wheat Bakery Flour (25 lb)', 'Unbleached, high-protein stone-ground wheat flour ideal for artisan baking.', 22.50, 85, 10, 200, 11.34],
        ['Organic Grains & Flours', 'White Quinoa Bulk (20 lb)', 'Pre-washed saponin-free high-protein organic white quinoa grain.', 55.00, 40, 2, 50, 9.07],
        
        ['Dairy & Plant Alternatives', 'Barista Oat Milk Case (12x32oz)', 'Premium steaming oat milk designed specifically for specialty coffee bars.', 32.00, 150, 10, 500, 12.00],
        ['Dairy & Plant Alternatives', 'Salted Butter Blocks (36x1lb)', 'Grade A butter sticks boxed in bulk wholesale crates for food service.', 115.00, 30, 2, 20, 16.33],
        
        ['Fresh Produce (Cases)', 'Hass Avocados Case (48 count)', 'Fresh green avocados, selected firm for transit, grade A selection.', 65.00, 60, 4, 30, 9.50],
        ['Fresh Produce (Cases)', 'Organic Lemons Case (100 count)', 'Juicy restaurant-grade bulk lemons, USDA certified organic.', 38.00, 45, 5, 40, 15.00],
        
        ['Beverages & Syrups', 'Cold Brew Coffee Concentrate (4x1Gal)', 'Strong cold brew base packed in boxes with dispensing taps.', 78.00, 25, 3, 15, 16.00]
    ];

    $product_ids = [];
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, base_price, stock_qty, min_order_qty, max_order_qty, weight, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($products as $p) {
        $cat_id = $cat_ids[$p[0]];
        $stmt->execute([$cat_id, $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7]]);
        $product_ids[$p[1]] = $pdo->lastInsertId();
    }
    echo "Seeded " . count($products) . " wholesale products.\n";

    // 4. Seed Product Price Tiers (Bulk Discounts)
    // [product_name, min_qty, unit_price]
    $tiers = [
        ['Organic Jasmine Rice (50 lb)', 10, 42.00],
        ['Organic Jasmine Rice (50 lb)', 25, 38.00],
        ['Whole Wheat Bakery Flour (25 lb)', 20, 20.00],
        ['Whole Wheat Bakery Flour (25 lb)', 50, 18.00],
        ['Barista Oat Milk Case (12x32oz)', 30, 29.00],
        ['Barista Oat Milk Case (12x32oz)', 100, 26.50],
        ['Hass Avocados Case (48 count)', 10, 60.00],
        ['Hass Avocados Case (48 count)', 20, 56.00]
    ];

    $stmt = $pdo->prepare("INSERT INTO product_price_tiers (product_id, min_qty, unit_price) VALUES (?, ?, ?)");
    foreach ($tiers as $t) {
        $pid = $product_ids[$t[0]];
        $stmt->execute([$pid, $t[1], $t[2]]);
    }
    echo "Seeded bulk pricing discount tiers.\n";

    // 4.5. Seed Inventory Lots
    $lots = [
        ['Organic Jasmine Rice (50 lb)', 'JR-2026-001', '2027-06-01', 'A-12', 100, 80],
        ['Organic Jasmine Rice (50 lb)', 'JR-2026-002', '2027-12-01', 'A-13', 40, 40],
        ['Whole Wheat Bakery Flour (25 lb)', 'WF-2026-001', '2027-04-15', 'B-04', 85, 85],
        ['White Quinoa Bulk (20 lb)', 'WQ-2026-001', '2027-09-20', 'B-08', 40, 40],
        ['Barista Oat Milk Case (12x32oz)', 'OM-2026-001', '2026-12-15', 'C-02', 150, 150],
        ['Salted Butter Blocks (36x1lb)', 'BB-2026-001', '2026-11-30', 'D-01', 30, 30],
        ['Hass Avocados Case (48 count)', 'HA-2026-001', '2026-07-10', 'E-01', 60, 60],
        ['Organic Lemons Case (100 count)', 'OL-2026-001', '2026-07-25', 'E-05', 45, 45],
        ['Cold Brew Coffee Concentrate (4x1Gal)', 'CB-2026-001', '2027-01-10', 'F-03', 25, 25]
    ];

    $stmt = $pdo->prepare("INSERT INTO inventory_lots (product_id, lot_number, expiry_date, shelf_location, qty_received, qty_remaining, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    foreach ($lots as $l) {
        $pid = $product_ids[$l[0]];
        $stmt->execute([$pid, $l[1], $l[2], $l[3], $l[4], $l[5]]);
    }
    echo "Seeded warehouse inventory lots.\n";

    // 5. Fetch Seeded/Existing Users for Linking Addresses and Orders
    // We already have user (wholesale_user) and executive (executive)
    $stmt = $pdo->prepare("SELECT username, id FROM users WHERE username IN ('user', 'executive')");
    $stmt->execute();
    $db_users = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (empty($db_users['user']) || empty($db_users['executive'])) {
        throw new Exception("Default users 'user' and 'executive' must be seeded first by running setup.php.");
    }
    
    $user_uid = $db_users['user'];
    $exec_uid = $db_users['executive'];

    // 6. Seed User Addresses
    // [user_id, address_line, city, location_id, is_default]
    $addresses = [
        [$user_uid, '456 Bakery Boulevard, Suite A', 'New York, NY 10002', 2, 1],
        [$user_uid, '789 Grocery Way', 'Austin, TX 78701', 3, 0],
        [$exec_uid, '101 Executive Parkway, Plaza 4', 'Los Angeles, CA 90025', 1, 1],
        [$exec_uid, '202 Partner Boulevard', 'Miami, FL 33101', 4, 0]
    ];

    $addr_ids = [];
    $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_line, city, location_id, is_default) VALUES (?, ?, ?, ?, ?)");
    foreach ($addresses as $a) {
        $stmt->execute($a);
        $addr_ids[] = $pdo->lastInsertId();
    }
    echo "Seeded B2B customer shipping addresses.\n";

    // Seed Wallet Balances for B2B Clients
    $wallet_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
    $wallet_stmt->execute([$user_uid, 'credit', 5000.00, 'Initial wholesale account credit']);
    $wallet_stmt->execute([$exec_uid, 'credit', 8500.00, 'Initial partner wallet topup']);
    echo "Funded digital wallets with demo credits.\n";

    // 7. Seed Orders & Order Items
    // Let's create a few realistic past orders for testing

    // Order 1: Placed by 'user', Status: Delivered, Paid via COD
    $o1_items = [
        ['Organic Jasmine Rice (50 lb)', 10, 42.00], // Hits 1st tier price
        ['Barista Oat Milk Case (12x32oz)', 15, 32.00]
    ];
    $o1_total = (10 * 42.00) + (15 * 32.00); // 420 + 480 = 900.00
    $o1_shipping = 45.00;
    $o1_tax = 0.00; // COD New York
    
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'COD', '456 Bakery Boulevard, Suite A, New York, NY 10002 (New York, Tax: 8.88%)', 'Delivered')");
    $stmt->execute([$user_uid, $o1_total + $o1_shipping + $o1_tax, $o1_tax, $o1_shipping]);
    $o1_id = $pdo->lastInsertId();

    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price_at_purchase) VALUES (?, ?, ?, ?)");
    foreach ($o1_items as $item) {
        $item_stmt->execute([$o1_id, $product_ids[$item[0]], $item[1], $item[2]]);
    }
    
    // Status History
    $hist_stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
    $hist_stmt->execute([$o1_id, 'Pending Payment', $user_uid, 'Order Submitted']);
    $hist_stmt->execute([$o1_id, 'Payment Verified', 1, 'COD verified']);
    $hist_stmt->execute([$o1_id, 'Processing', 1, 'Packed and sorted']);
    $hist_stmt->execute([$o1_id, 'Shipped', 1, 'Dispatched via freight courier']);
    $hist_stmt->execute([$o1_id, 'Delivered', 1, 'Signed by warehouse receiver']);

    // Order 2: Placed by 'executive', Status: Processing, Paid via Wallet (Confirms partner order queue testing)
    $o2_items = [
        ['Salted Butter Blocks (36x1lb)', 3, 115.00], // 345.00
        ['Cold Brew Coffee Concentrate (4x1Gal)', 4, 78.00] // 312.00
    ];
    $o2_total = 345.00 + 312.00; // 657.00
    $o2_shipping = 75.00;
    $o2_tax = 54.20;
    
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'Wallet', '101 Executive Parkway, Plaza 4, Los Angeles, CA 90025 (California, Tax: 8.25%)', 'Processing')");
    $stmt->execute([$exec_uid, $o2_total + $o2_shipping + $o2_tax, $o2_tax, $o2_shipping]);
    $o2_id = $pdo->lastInsertId();

    foreach ($o2_items as $item) {
        $item_stmt->execute([$o2_id, $product_ids[$item[0]], $item[1], $item[2]]);
    }
    $hist_stmt->execute([$o2_id, 'Pending Payment', $exec_uid, 'Order Submitted']);
    $hist_stmt->execute([$o2_id, 'Payment Verified', $exec_uid, 'Settled instantly via Digital Wallet']);
    $hist_stmt->execute([$o2_id, 'Processing', 1, 'Processing inbound batching']);

    // Deduct wallet for order 2
    $wallet_stmt->execute([$exec_uid, 'debit', $o2_total + $o2_shipping + $o2_tax, "Instant Wallet settlement for Order #$o2_id"]);

    // Order 3: Placed by 'user', Status: Pending Payment, Paid via Bank Transfer
    $o3_items = [
        ['Hass Avocados Case (48 count)', 12, 60.00] // 720.00
    ];
    $o3_total = 720.00;
    $o3_shipping = 35.00;
    $o3_tax = 62.29;
    $wire_details = json_encode([
        'bank_name' => 'Wells Fargo',
        'transaction_id' => 'WIRE-9920192837',
        'transfer_date' => '2026-06-19'
    ]);
    
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, tax_amount, shipping_amount, payment_method, payment_details, delivery_address, status) VALUES (?, ?, ?, ?, 'Bank Transfer', ?, '456 Bakery Boulevard, Suite A, New York, NY 10002 (New York, Tax: 8.88%)', 'Pending Payment')");
    $stmt->execute([$user_uid, $o3_total + $o3_shipping + $o3_tax, $o3_tax, $o3_shipping, $wire_details]);
    $o3_id = $pdo->lastInsertId();

    foreach ($o3_items as $item) {
        $item_stmt->execute([$o3_id, $product_ids[$item[0]], $item[1], $item[2]]);
    }
    $hist_stmt->execute([$o3_id, 'Pending Payment', $user_uid, 'Bank Transfer proof submitted. Awaiting verification.']);

    echo "Seeded 3 demo wholesale orders (1 Delivered, 1 Processing, 1 Pending Payment).\n";

    // 8. Seed Chats
    // [user_id, admin_id, message, sender_role, is_read]
    $chats = [
        [$user_uid, 1, 'Hello, we are planning to order 100 cases of Jasmine Rice next month. Can we unlock a custom price tier?', 'wholesale_user', 1],
        [$user_uid, 1, 'Hello John! Yes, we can certainly set up a custom wholesale price structure for that volume. I will pass this to our Manager.', 'admin', 1],
        [$user_uid, 1, 'Thank you! I will look forward to the managers update.', 'wholesale_user', 0],
        
        [$exec_uid, 1, 'Hi Team, is there any delay on the oat milk shipments to Los Angeles CA?', 'wholesale_user', 1],
        [$exec_uid, 1, 'Hi, no delays reported. Your current order #2 is already in our packaging facility and will ship out tomorrow morning.', 'admin', 1],
        [$exec_uid, 1, 'Perfect, thank you for the fast response.', 'wholesale_user', 1]
    ];

    $chat_stmt = $pdo->prepare("INSERT INTO chats (user_id, admin_id, message, sender_role, is_read) VALUES (?, ?, ?, ?, ?)");
    foreach ($chats as $ch) {
        $chat_stmt->execute($ch);
    }
    echo "Seeded customer support chat history.\n";

    // 9. Seed Coupons
    $stmt_coupon = $pdo->prepare("INSERT INTO coupons (code, type, value, min_spend, max_discount, usage_limit, used_count, start_date, end_date, rules, target_wholesalers, is_active) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1)");
    $stmt_coupon->execute(['WELCOME10', 'percentage', 10.00, 50.00, null, 100, date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+30 days')), '10% off for all B2B accounts on orders above $50.', 'all']);
    $stmt_coupon->execute(['VIP100', 'fixed', 100.00, 500.00, null, 50, date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+10 days')), 'Exclusive $100 discount for Executives on orders above $500.', 'executive']);
    
    // Seed an expired coupon
    $stmt_coupon_exp = $pdo->prepare("INSERT INTO coupons (code, type, value, min_spend, max_discount, usage_limit, used_count, start_date, end_date, expiry_date, rules, target_wholesalers, is_active) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 1)");
    $stmt_coupon_exp->execute(['EXPIRED20', 'percentage', 20.00, 0.00, null, 10, date('Y-m-d H:i:s', strtotime('-10 days')), date('Y-m-d H:i:s', strtotime('-2 days')), date('Y-m-d H:i:s', strtotime('-2 days')), 'Expired test coupon.', 'all']);
    echo "Seeded coupon codes.\n";
 
    // 10. Seed Automatic Discounts
    $stmt_discount = $pdo->prepare("INSERT INTO discounts (name, discount_type, percent, amount, rules, target_wholesalers, is_active) VALUES (?, 'global', ?, ?, ?, 'all', 1)");
    $stmt_discount->execute(['Automated 5% Partner Discount', 5.00, 0.00, 'Automated 5% off subtotal']);
    
    // Seed product-specific discounts
    $stmt_discount_prod1 = $pdo->prepare("INSERT INTO discounts (name, discount_type, product_id, percent, amount, rules, target_wholesalers, is_active) VALUES (?, 'product_specific', ?, ?, ?, ?, 'all', 1)");
    $stmt_discount_prod1->execute(['10% Rice Discount', $product_ids['Organic Jasmine Rice (50 lb)'], 10.00, 0.00, '10% off jasmine rice']);
    
    $stmt_discount_prod2 = $pdo->prepare("INSERT INTO discounts (name, discount_type, product_id, percent, amount, rules, target_wholesalers, is_active) VALUES (?, 'product_specific', ?, ?, ?, ?, 'executive', 1)");
    $stmt_discount_prod2->execute(['Executive Oat Milk Special', $product_ids['Barista Oat Milk Case (12x32oz)'], 0.00, 5.00, '$5 off oat milk cases for Executives']);
    
    echo "Seeded global and product-specific automatic discounts.\n";
 
    // 11. Seed Promotions / Marketing Campaigns
    $stmt_promo = $pdo->prepare("INSERT INTO promotions (title, message, target_wholesalers, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt_promo->execute([
        'Summer Kitchen Blowout 10%', 
        'Get 10% off using coupon code WELCOME10 on all organic grains and plant alternatives this summer! Applicable for standard wholesalers and executive accounts.', 
        'all', 
        date('Y-m-d H:i:s', strtotime('-1 day')), 
        date('Y-m-d H:i:s', strtotime('+30 days'))
    ]);
    $stmt_promo->execute([
        'Executive Loyalty Crate', 
        'As an Executive partner, you can now apply coupon VIP100 at checkout to deduct $100 flat from any orders above $500.', 
        'executive', 
        date('Y-m-d H:i:s', strtotime('-1 day')), 
        date('Y-m-d H:i:s', strtotime('+10 days'))
    ]);
    echo "Seeded marketing promotions.\n";

    // 12. Seed Stripe Settings
    $stmt_setting = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES ('payment_stripe_enabled', '1', 'Enable Stripe card payments') ON DUPLICATE KEY UPDATE setting_value = '1'");
    $stmt_setting->execute();
    echo "Seeded settings (Stripe enabled).\n";

    $pdo->commit();
    echo "✔ Demo data seeding successfully committed to bolakausa_db!\n";

} catch (Exception $e) {
    echo "❌ Error seeding database: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
