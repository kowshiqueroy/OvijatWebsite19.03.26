<?php
/**
 * migrate_to_branch.php
 * Migration: Add branch_id to categories and products tables
 * Run from browser: /inv/migrate_to_branch.php
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireRole('Admin');

echo "<h2>Branching Migration</h2>";

try {
    // Check if branch_id already exists in categories
    $cols = $pdo->query("SHOW COLUMNS FROM categories LIKE 'branch_id'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN branch_id INT DEFAULT 1");
        $pdo->exec("UPDATE categories SET branch_id = 1 WHERE branch_id IS NULL OR branch_id = 0");
        echo "<p>Categories updated - added branch_id</p>";
    } else {
        echo "<p>Categories already has branch_id</p>";
    }
    
    // Check if branch_id already exists in products
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'branch_id'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE products ADD COLUMN branch_id INT DEFAULT 1");
        $pdo->exec("UPDATE products SET branch_id = 1 WHERE branch_id IS NULL OR branch_id = 0");
        echo "<p>Products updated - added branch_id</p>";
    } else {
        echo "<p>Products already has branch_id</p>";
    }
    
    // Set default branch for customers if not set
    $pdo->exec("UPDATE customers SET branch_id = 1 WHERE branch_id IS NULL OR branch_id = 0");
    echo "<p>Customers default branch set</p>";
    
    // Set default branch for sales if not set
    $pdo->exec("UPDATE sales SET branch_id = 1 WHERE branch_id IS NULL OR branch_id = 0");
    echo "<p>Sales default branch set</p>";
    
    echo "<p style='color:green'><b>Migration complete! Now run setup.php to update table structure.</b></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>