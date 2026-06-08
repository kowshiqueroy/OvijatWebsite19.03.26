<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$id = $_GET['id'] ?? 0;

// Verify the product exists and has no sales
$product = fetch_one("SELECT id, name FROM products WHERE id = ? AND isDelete = 0", [$id]);

if (!$product) {
    redirect('modules/products/index.php', 'Product not found.', 'danger');
}

$sale_check = fetch_one("SELECT COUNT(*) as count FROM sales_items WHERE product_id = ? AND isDelete = 0", [$id]);

if ($sale_check['count'] > 0) {
    redirect('modules/products/index.php', 'Cannot delete product because it has associated sales records.', 'danger');
}

// Perform soft delete
db_query("UPDATE products SET isDelete = 1 WHERE id = ?", [$id]);

log_activity($_SESSION['user_id'], "Deleted product: " . $product['name']);
redirect('modules/products/index.php', 'Product deleted successfully.');
?>
