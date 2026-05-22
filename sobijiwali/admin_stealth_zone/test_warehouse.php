<?php
/**
 * Test Script for Warehouse Manager & FIFO
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/WarehouseManager.php';
require_once __DIR__ . '/../includes/StripeClient.php';

echo "<h1>Warehouse & FIFO Test</h1>";

$warehouse = new WarehouseManager();
$db = Database::getInstance();

try {
    // 1. Check current inventory for Variation 1
    echo "Initial Stock Levels for Variation 1:<br>";
    $batches = $db->query("SELECT * FROM inventory_batches WHERE product_variation_id = 1")->fetchAll();
    foreach ($batches as $b) {
        echo "- Batch #{$b['id']}: {$b['quantity_remaining']} / {$b['quantity_initial']}<br>";
    }

    // 2. Add another batch to test FIFO
    echo "Adding second inventory batch... ";
    $db->query("INSERT INTO inventory_batches (product_variation_id, quantity_initial, quantity_remaining, cost_price, received_date) 
                VALUES (1, 10, 10, 1.50, DATE_ADD(NOW(), INTERVAL 1 DAY))");
    echo "Done.<br>";

    // 3. Process Order #1 (which has 2 items of Variation 1)
    echo "Processing Order #1 (Deducting 2 items)... ";
    $result = $warehouse->processOrder(1);
    if ($result['success']) {
        echo "Success! Order status: PROCESSING<br>";
    } else {
        echo "Failed: " . $result['message'] . "<br>";
    }

    // 4. Verify FIFO Deduction
    echo "Post-Deduction Stock Levels:<br>";
    $batches = $db->query("SELECT * FROM inventory_batches WHERE product_variation_id = 1 ORDER BY received_date ASC")->fetchAll();
    foreach ($batches as $b) {
        echo "- Batch #{$b['id']}: {$b['quantity_remaining']} / {$b['quantity_initial']}<br>";
    }

    // 5. Test Shipping (Mock Capture)
    echo "Shipping Order #1... ";
    // We expect capture to fail due to placeholder keys, but let's see the error handling
    $result = $warehouse->shipOrder(1);
    if ($result['success']) {
        echo "Success! Order SHIPPED.<br>";
    } else {
        echo "Handled Expected Failure: " . $result['message'] . "<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
