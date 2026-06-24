<?php
/**
 * Stock Sync Utility
 */
require_once 'config/database.php';

try {
    echo "--- Current Stock Status ---\n";
    $products = $pdo->query("SELECT id, name, stock_qty FROM products ORDER BY id ASC")->fetchAll();
    
    foreach ($products as $p) {
        $stmt = $pdo->prepare("SELECT SUM(qty_remaining) FROM inventory_lots WHERE product_id = ? AND status = 'active'");
        $stmt->execute([$p['id']]);
        $lot_sum = (int)$stmt->fetchColumn();
        
        echo "Product #{$p['id']} '{$p['name']}': Catalog stock = {$p['stock_qty']}, LOT stock = {$lot_sum}\n";
        
        if ($p['stock_qty'] !== $lot_sum) {
            $update = $pdo->prepare("UPDATE products SET stock_qty = ? WHERE id = ?");
            $update->execute([$lot_sum, $p['id']]);
            echo "  ✔ Synchronized Catalog Stock to {$lot_sum}.\n";
        }
    }
    
    echo "✔ Stock synchronization complete.\n";
} catch (Exception $e) {
    echo "❌ Error during sync: " . $e->getMessage() . "\n";
}
