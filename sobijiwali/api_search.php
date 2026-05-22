<?php
/**
 * AJAX Catalog Search & Filter API
 */
require_once 'config/config.php';
require_once 'includes/Database.php';

$db = Database::getInstance();

$search = $_GET['q'] ?? '';
$category = $_GET['cat'] ?? '';
$minPrice = $_GET['min'] ?? 0;
$maxPrice = $_GET['max'] ?? 1000;

$sql = "SELECT p.*, i.file_path as primary_image, v.id as default_variation_id,
        v.wholesale_price, v.wholesale_min_qty,
        (SELECT SUM(quantity_remaining) FROM inventory_batches b JOIN product_variations v2 ON b.product_variation_id = v2.id WHERE v2.product_id = p.id) as total_stock
        FROM products p 
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = 1 
        LEFT JOIN product_variations v ON p.id = v.product_id 
        WHERE p.is_active = 1";

$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = (int)$category;
}

$sql .= " AND p.base_price BETWEEN ? AND ?";
$params[] = (float)$minPrice;
$params[] = (float)$maxPrice;

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$products = $db->query($sql, $params)->fetchAll();

header('Content-Type: application/json');
echo json_encode($products);
