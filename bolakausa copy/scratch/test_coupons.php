<?php
/**
 * Programmatic Coupon Rules Testing Suite
 */
require_once 'config/database.php';
require_once 'includes/auth_helper.php';

function test_coupon($pdo, $code, $user_role, $subtotal) {
    echo "Testing Code: '{$code}' | Role: '{$user_role}' | Subtotal: \${$subtotal}\n";
    
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        echo "  ❌ Result: Invalid or inactive coupon code.\n\n";
        return;
    }
    
    $now = date('Y-m-d H:i:s');
    if ($coupon['start_date'] && $coupon['start_date'] > $now) {
        echo "  ❌ Result: Coupon validity has not started yet. (Start: {$coupon['start_date']})\n\n";
        return;
    }
    if ($coupon['end_date'] && $coupon['end_date'] < $now) {
        echo "  ❌ Result: Coupon has expired. (End: {$coupon['end_date']})\n\n";
        return;
    }
    if ($coupon['expiry_date'] && $coupon['expiry_date'] < $now) {
        echo "  ❌ Result: Coupon has expired. (Expiry: {$coupon['expiry_date']})\n\n";
        return;
    }
    if ($coupon['used_count'] >= $coupon['usage_limit']) {
        echo "  ❌ Result: Coupon reached maximum usage limit. (Limit: {$coupon['usage_limit']})\n\n";
        return;
    }
    if ($subtotal < $coupon['min_spend']) {
        echo "  ❌ Result: Minimum spend of \$" . number_format($coupon['min_spend'], 2) . " is required.\n\n";
        return;
    }
    
    // Fetch user ID matching role for testing
    $stmt_u = $pdo->prepare("SELECT id FROM users WHERE role = ? AND is_deleted = 0 LIMIT 1");
    $stmt_u->execute([$user_role]);
    $uid = $stmt_u->fetchColumn();
    
    if (!is_wholesaler_targeted($pdo, $uid, $coupon['target_wholesalers'])) {
        echo "  ❌ Result: Coupon not applicable to account role '{$user_role}' (Allowed: {$coupon['target_wholesalers']}).\n\n";
        return;
    }
    
    // Valid! Calculate discount
    $discount = 0;
    if ($coupon['type'] === 'fixed') {
        $discount = (float)$coupon['value'];
    } elseif ($coupon['type'] === 'percentage') {
        $discount = ($subtotal * (float)$coupon['value']) / 100;
        if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
            $discount = (float)$coupon['max_discount'];
        }
    }
    if ($discount > $subtotal) $discount = $subtotal;
    
    echo "  ✔ Success! Applied Coupon.\n";
    echo "  ✔ Discount Calculated: \$" . number_format($discount, 2) . "\n\n";
}

echo "--- STARTING B2B COUPONS VALIDATION SUITE ---\n\n";

// Test Case 1: WELCOME10 with wholesale_user, subtotal $100 (should succeed, 10% discount -> $10)
test_coupon($pdo, 'WELCOME10', 'wholesale_user', 100.00);

// Test Case 2: WELCOME10 with wholesale_user, subtotal $30 (should fail, min spend is $50)
test_coupon($pdo, 'WELCOME10', 'wholesale_user', 30.00);

// Test Case 3: VIP100 with executive, subtotal $600 (should succeed, flat discount -> $100)
test_coupon($pdo, 'VIP100', 'executive', 600.00);

// Test Case 4: VIP100 with executive, subtotal $400 (should fail, min spend is $500)
test_coupon($pdo, 'VIP100', 'executive', 400.00);

// Test Case 5: VIP100 with wholesale_user, subtotal $600 (should fail, only applicable to executive)
test_coupon($pdo, 'VIP100', 'wholesale_user', 600.00);

// Test Case 6: EXPIRED20 with wholesale_user, subtotal $200 (should fail, expired)
test_coupon($pdo, 'EXPIRED20', 'wholesale_user', 200.00);

// Test Case 7: Fake Coupon
test_coupon($pdo, 'FAKE99', 'wholesale_user', 200.00);

echo "--- B2B COUPONS VALIDATION SUITE COMPLETE ---\n";
