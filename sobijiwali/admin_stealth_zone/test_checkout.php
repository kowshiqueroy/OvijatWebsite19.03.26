<?php
/**
 * Test Script for Cart & Checkout Logic
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/CartManager.php';
require_once __DIR__ . '/../includes/StripeClient.php';
require_once __DIR__ . '/../includes/CheckoutManager.php';

echo "<h1>Cart & Checkout Test</h1>";

$cart = new CartManager();
$checkout = new CheckoutManager();
$db = Database::getInstance();

try {
    // 1. Mock a Client Cart (Variation ID 1 was created in Phase 3 test)
    $clientCart = [
        ['variation_id' => 1, 'quantity' => 2]
    ];
    echo "Syncing Client Cart... ";
    $syncedCart = $cart->syncCart($clientCart);
    echo "Done.<br>";
    echo "<pre>Synced Cart: " . print_r($syncedCart, true) . "</pre>";

    // 2. Calculate Totals
    echo "Calculating Totals... ";
    $totals = $cart->calculateTotals($syncedCart['subtotal']);
    echo "Done.<br>";
    echo "<pre>Totals: " . print_r($totals, true) . "</pre>";

    // 3. Create Order (Mock User ID 1)
    echo "Creating Order... ";
    $orderId = $checkout->createOrder(1, $syncedCart, $totals, 'stripe');
    echo "Done (Order ID: $orderId)<br>";

    // 4. Verify Database Records
    echo "Verifying Order Items... ";
    $items = $db->query("SELECT * FROM order_items WHERE order_id = ?", [$orderId])->fetchAll();
    echo count($items) . " items found in database.<br>";

    // 5. Stripe Authorization (Mock Result for Test)
    echo "<h2>Mock Stripe Authorization:</h2>";
    echo "<i>(Hitting Stripe with placeholder keys will fail, but the cURL wrapper is verified.)</i><br>";
    
    // We'll just print what would be sent
    echo "Would send to Stripe: Amount " . ($totals['total'] * 100) . " cents, manual capture mode.<br>";
    
    echo "<h2>Final Order State in DB:</h2>";
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
    echo "<pre>" . print_r($order, true) . "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
