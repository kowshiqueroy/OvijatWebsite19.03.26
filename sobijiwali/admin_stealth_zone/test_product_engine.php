<?php
/**
 * Test Script for Product Engine
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ProductManager.php';
require_once __DIR__ . '/../includes/ImageOptimizer.php';

echo "<h1>Product Engine Test</h1>";

$pm = new ProductManager();
$db = Database::getInstance();

try {
    // 1. Create a Category
    echo "Creating Category... ";
    $db->query("INSERT IGNORE INTO categories (name, slug) VALUES ('Vegetables', 'vegetables')");
    $categoryId = $db->query("SELECT id FROM categories WHERE slug = 'vegetables'")->fetch()['id'];
    echo "Done (ID: $categoryId)<br>";

    // 2. Create a Product
    echo "Creating Product... ";
    $productData = [
        'category_id' => $categoryId,
        'name' => 'Fresh Organic Tomato',
        'description' => 'Vine-ripened organic tomatoes, locally sourced.',
        'base_price' => 2.50
    ];
    $productId = $pm->createProduct($productData);
    echo "Done (ID: $productId)<br>";

    // 3. Add a Variation
    echo "Adding Variation... ";
    $sku = $pm->generateSKU('VEG', 'Tomato', '1KG');
    $variationData = [
        'sku' => $sku,
        'name_modifier' => '1 KG',
        'price_override' => null
    ];
    $variationId = $pm->addVariation($productId, $variationData);
    echo "Done (ID: $variationId, SKU: $sku)<br>";

    // 4. Add Inventory Batch
    echo "Adding Inventory Batch... ";
    $batchData = [
        'quantity' => 50,
        'cost_price' => 1.20
    ];
    $batchId = $pm->addInventoryBatch($variationId, $batchData);
    echo "Done (ID: $batchId)<br>";

    // 5. Retrieve Product
    echo "<h2>Retrieved Product Data:</h2>";
    $product = $pm->getProduct($productId);
    echo "<pre>";
    print_r($product);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
