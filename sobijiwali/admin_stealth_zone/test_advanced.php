<?php
/**
 * Test Script for Phase 7: Advanced Features
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/WalletManager.php';
require_once __DIR__ . '/../includes/WarehouseManager.php';
require_once __DIR__ . '/../includes/StripeClient.php';
require_once __DIR__ . '/../includes/SubscriptionManager.php';

echo "<h1>Advanced Features Test</h1>";

$wallet = new WalletManager();
$warehouse = new WarehouseManager();
$subscription = new SubscriptionManager();
$db = Database::getInstance();

$testUserId = 1; // Existing test user

try {
    // 1. Wallet Test
    echo "<h2>1. Wallet Test</h2>";
    echo "Initial Balance: $" . number_format($wallet->getBalance($testUserId), 2) . "<br>";
    
    echo "Adding $50.00 to wallet... ";
    $wallet->addFunds($testUserId, 50.00, "Manual Test Deposit");
    echo "New Balance: $" . number_format($wallet->getBalance($testUserId), 2) . "<br>";
    
    echo "Deducting $12.50 for a purchase... ";
    $res = $wallet->deductFunds($testUserId, 12.50, "Test Purchase");
    if ($res['success']) {
        echo "Success! New Balance: $" . number_format($wallet->getBalance($testUserId), 2) . "<br>";
    } else {
        echo "Failed: " . $res['message'] . "<br>";
    }

    // 2. Loyalty Reward Test
    echo "<h2>2. Loyalty Reward Test</h2>";
    // We'll create a new order to test the reward logic in shipOrder
    echo "Creating a mock order for loyalty test... ";
    $db->query("INSERT INTO orders (user_id, total_amount, status, payment_method) VALUES (?, ?, 'processing', 'wallet')", [$testUserId, 100.00]);
    $orderId = $db->lastInsertId();
    echo "Order #$orderId created with $100.00 total.<br>";
    
    echo "Shipping order to trigger loyalty reward... ";
    $warehouse->shipOrder($orderId);
    echo "Order shipped.<br>";
    
    echo "New Balance (should include $1.00 reward): $" . number_format($wallet->getBalance($testUserId), 2) . "<br>";
    
    // Check transaction history
    echo "Recent Wallet Transactions:<br>";
    $walletId = $db->query("SELECT id FROM wallets WHERE user_id = ?", [$testUserId])->fetch()['id'];
    $txs = $db->query("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 3", [$walletId])->fetchAll();
    foreach ($txs as $tx) {
        echo "- [{$tx['type']}] {$tx['description']}: " . ($tx['amount'] >= 0 ? "+" : "") . $tx['amount'] . "<br>";
    }

    // 3. Subscription Test
    echo "<h2>3. Subscription Test</h2>";
    echo "Creating weekly subscription for Variation #1... ";
    $subId = $subscription->createSubscription($testUserId, 1, 2, 7);
    echo "Done (ID: $subId)<br>";
    
    echo "Listing active subscriptions:<br>";
    $subs = $subscription->getUserSubscriptions($testUserId);
    foreach ($subs as $s) {
        echo "- {$s['product_name']}: {$s['quantity']} units, every {$s['frequency_days']} days. Next run: {$s['next_run_date']}<br>";
    }

    echo "Manually triggering due subscriptions (simulating cron)... ";
    // Force the test subscription to be 'due'
    $db->query("UPDATE subscriptions SET next_run_date = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?", [$subId]);
    $processed = $subscription->processDueSubscriptions();
    echo "$processed subscription(s) processed.<br>";
    
    $newSub = $db->query("SELECT next_run_date FROM subscriptions WHERE id = ?", [$subId])->fetch();
    echo "Updated next run date: " . $newSub['next_run_date'] . "<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
